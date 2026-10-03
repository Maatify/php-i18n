<?php

/**
 * @copyright   ©2026 Maatify.dev
 * @Library     maatify/php-i18n
 * @Project     maatify:php-i18n
 * @author      Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @since       2026-10-01 00:00
 * @see         https://www.maatify.dev Maatify.dev
 * @link        https://github.com/Maatify/php-i18n view Project on GitHub
 * @note        Distributed in the hope that it will be useful - WITHOUT WARRANTY.
 */

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Enum\I18nPolicyModeEnum;
use Maatify\I18n\Exception\DomainInUseException;
use Maatify\I18n\Exception\ScopeAlreadyExistsException;
use Maatify\I18n\Exception\ScopeInUseException;
use Maatify\I18n\Exception\ScopeNotFoundException;
use Maatify\I18n\Exception\TranslationKeyAlreadyExistsException;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Tests\Support\Concurrency\Runtime;
use Maatify\I18n\Tests\Support\Concurrency\WorkerProcess;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;
use PDO;

/**
 * GA-F08 / GA-F09: concurrency proof with REAL MySQL, REAL separate PDO
 * connections and REAL separate PHP processes. Nothing here is mocked.
 *
 * Interleavings are made deterministic by holding a caller-owned transaction in
 * this process (it owns connection A and holds the row locks the Package takes)
 * while a worker process (connection B) is started and observed to be BLOCKED
 * on the lock (performance_schema.data_lock_waits) before connection A
 * proceeds. No sleeps decide an outcome.
 */
final class ConcurrencyTest extends MysqlIntegrationTestCase
{
    /** @var list<WorkerProcess> */
    private array $workers = [];

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->close();
        }
        $this->workers = [];

        parent::tearDown();
    }

    /**
     * @param array<string, string|int> $args
     */
    private function worker(string $operation, array $args, string $mode = 'strict', ?string $barrier = null): WorkerProcess
    {
        $worker = WorkerProcess::start(self::schemaName(), $operation, $args, $mode, $barrier);
        $this->workers[] = $worker;

        return $worker;
    }

    /**
     * Waits (bounded) until a worker session is blocked on a row lock held by
     * another session.
     */
    private function awaitLockWait(): void
    {
        $observer = self::newConnection();
        $deadline = microtime(true) + 20.0;

        do {
            $stmt = $observer->query('SELECT COUNT(*) FROM performance_schema.data_lock_waits');
            self::assertNotFalse($stmt);
            if ((int) $stmt->fetchColumn() > 0) {
                return;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);

        self::fail('the worker never blocked on a lock: the interleaving was not exercised');
    }

    private function seedGovernance(): void
    {
        $this->pdo()->exec("INSERT INTO maa_i18n_scopes (code, name) VALUES ('s1', 'S1')");
        $this->pdo()->exec("INSERT INTO maa_i18n_domains (code, name) VALUES ('d1', 'D1'), ('d2', 'D2')");
    }

    private function idOf(string $table, string $code): int
    {
        return $this->scalarInt('SELECT id FROM ' . $table . " WHERE code = '" . $code . "'");
    }

    // ── A. code change vs. concurrent usage creation ───────────────────────

    public function testScopeCodeChangeCannotOrphanAConcurrentAssignment_usageWinsTheLock(): void
    {
        $this->seedGovernance();
        $scopeId = $this->idOf('maa_i18n_scopes', 's1');

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        // connection A (this process): the assignment decided "scope s1 is allowed"
        // and holds its SHARE lock; the mapping becomes visible only on commit.
        $this->scopeDomainManagement->assign('s1', 'd1');

        $worker = $this->worker('changeScopeCode', ['id' => $scopeId, 'newCode' => 's1-renamed']);
        $this->awaitLockWait();

        $pdo->commit();
        $outcome = $worker->outcome();

        self::assertFalse($outcome['ok']);
        self::assertSame(ScopeInUseException::class, $outcome['class'] ?? null);
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_scopes WHERE code = 's1'"));
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_domain_scopes WHERE scope_code = 's1'"));
        self::assertSame(0, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_domain_scopes m LEFT JOIN maa_i18n_scopes s ON s.code = m.scope_code WHERE s.id IS NULL AND m.scope_code <> \'ct\''));
    }

    public function testConcurrentAssignmentSeesTheRenamedScope_codeChangeWinsTheLock(): void
    {
        $this->seedGovernance();
        $scopeId = $this->idOf('maa_i18n_scopes', 's1');

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        // connection A: the code change holds the UPDATE lock on the scope row
        $this->scopeManagement->changeCode($scopeId, 's1-renamed');

        $worker = $this->worker('assign', ['scope' => 's1', 'domain' => 'd1']);
        $this->awaitLockWait();

        $pdo->commit();
        $outcome = $worker->outcome();

        self::assertFalse($outcome['ok']);
        self::assertSame(ScopeNotFoundException::class, $outcome['class'] ?? null);
        self::assertSame(0, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_domain_scopes WHERE scope_code = 's1'"));
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_scopes WHERE code = 's1-renamed'"));
    }

    public function testScopeCodeChangeCannotOrphanAConcurrentKeyCreate(): void
    {
        // PERMISSIVE mode lets a key reference a scope that has no mapping yet.
        $this->pdo()->exec("INSERT INTO maa_i18n_scopes (code, name) VALUES ('s1', 'S1')");
        $scopeId = $this->idOf('maa_i18n_scopes', 's1');

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        $permissive = Runtime::build($pdo, I18nPolicyModeEnum::PERMISSIVE);
        $keyId = $permissive->writer->createKey(new \Maatify\I18n\Management\Command\CreateKeyCommand('s1', 'unknown-domain', 'k'));

        $worker = $this->worker('changeScopeCode', ['id' => $scopeId, 'newCode' => 's1-renamed']);
        $this->awaitLockWait();

        $pdo->commit();
        $outcome = $worker->outcome();

        self::assertFalse($outcome['ok']);
        self::assertSame(ScopeInUseException::class, $outcome['class'] ?? null);
        self::assertGreaterThan(0, $keyId);
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_keys WHERE scope = 's1'"));
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_scopes WHERE code = 's1'"));
    }

    public function testDomainCodeChangeCannotOrphanAConcurrentKeyCreate(): void
    {
        $this->seedGovernance();
        $domainId = $this->idOf('maa_i18n_domains', 'd2');

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        $permissive = Runtime::build($pdo, I18nPolicyModeEnum::PERMISSIVE);
        $permissive->writer->createKey(new \Maatify\I18n\Management\Command\CreateKeyCommand('unknown-scope', 'd2', 'k'));

        $worker = $this->worker('changeDomainCode', ['id' => $domainId, 'newCode' => 'd2-renamed']);
        $this->awaitLockWait();

        $pdo->commit();
        $outcome = $worker->outcome();

        self::assertFalse($outcome['ok']);
        self::assertSame(DomainInUseException::class, $outcome['class'] ?? null);
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_keys WHERE domain = 'd2'"));
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_domains WHERE code = 'd2'"));
    }

    // ── B. duplicate-key race classification ───────────────────────────────

    public function testAKeyDuplicateRacePastThePrecheckSurfacesAsTheSemanticException(): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        // A inserted the identity but has not committed: B's pre-check cannot see
        // it, B's INSERT blocks on the unique index and then loses the race.
        $this->createKey('ct', 'home', 'dup');

        $worker = $this->worker('createKey', ['scope' => 'ct', 'domain' => 'home', 'key' => 'dup']);
        $this->awaitLockWait();

        $pdo->commit();
        $outcome = $worker->outcome();

        self::assertFalse($outcome['ok']);
        self::assertSame(
            TranslationKeyAlreadyExistsException::class,
            $outcome['class'] ?? null,
            'a raw PDOException must never leak from a duplicate race',
        );
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_keys WHERE key_part = 'dup'"));
    }

    public function testAScopeDuplicateRaceSurfacesAsTheSemanticException(): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        $this->scopeManagement->create(new CreateScopeCommand('race', 'Race'));

        $worker = $this->worker('createScope', ['code' => 'race', 'name' => 'Race']);
        $this->awaitLockWait();

        $pdo->commit();
        $outcome = $worker->outcome();

        self::assertFalse($outcome['ok']);
        self::assertSame(ScopeAlreadyExistsException::class, $outcome['class'] ?? null);
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_scopes WHERE code = 'race'"));
    }

    public function testManyProcessesCreatingTheSameKeyYieldExactlyOneWinnerAndSemanticLosers(): void
    {
        $barrier = 'i18n_it_go_' . bin2hex(random_bytes(4));
        $holder = self::newConnection();
        $holder->prepare('SELECT GET_LOCK(:n, 5)')->execute(['n' => $barrier]);

        $workers = [];
        for ($i = 0; $i < 6; $i++) {
            $workers[] = $this->worker('createKey', ['scope' => 'ct', 'domain' => 'home', 'key' => 'same'], 'strict', $barrier);
        }
        usleep(300_000); // every worker is parked on GET_LOCK
        $holder->prepare('SELECT RELEASE_LOCK(:n)')->execute(['n' => $barrier]);

        $wins = 0;
        $semanticLosses = 0;
        $other = [];
        foreach ($workers as $worker) {
            $outcome = $worker->outcome();
            if ($outcome['ok']) {
                $wins++;
            } elseif (($outcome['class'] ?? null) === TranslationKeyAlreadyExistsException::class) {
                $semanticLosses++;
            } else {
                $other[] = ($outcome['class'] ?? '?') . ': ' . ($outcome['message'] ?? '');
            }
        }

        self::assertSame([], $other, 'no unclassified failure may escape');
        self::assertSame(1, $wins);
        self::assertSame(5, $semanticLosses);
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_keys WHERE key_part = 'same'"));
        // derived state is consistent with exactly one key
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_key_stats"));
    }

    // ── C. ordering under concurrency ──────────────────────────────────────

    /**
     * @param list<WorkerProcess> $workers
     *
     * @return list<array{ok: bool, class?: string, message?: string, value?: mixed}>
     */
    private function releaseTogether(string $barrier, \PDO $holder, array $workers): array
    {
        usleep(300_000); // every worker is parked on GET_LOCK
        $holder->prepare('SELECT RELEASE_LOCK(:n)')->execute(['n' => $barrier]);

        $outcomes = [];
        foreach ($workers as $worker) {
            $outcomes[] = $worker->outcome();
        }

        return $outcomes;
    }

    /**
     * @return list<int>
     */
    private function scopePositions(): array
    {
        $stmt = $this->pdo()->query('SELECT sort_order FROM maa_i18n_scopes ORDER BY sort_order ASC');
        self::assertNotFalse($stmt);

        /** @var list<int|string> $values */
        $values = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $positions = [];
        foreach ($values as $value) {
            $positions[] = is_numeric($value) ? (int) $value : -1;
        }

        return $positions;
    }

    public function testConcurrentCreatesAllocateDistinctContiguousDisplayPositions(): void
    {
        // base seeds one scope ('ct', position 0): the ordering is not empty, so
        // the last row is the lock every creator contends on.
        $barrier = 'i18n_it_go_' . bin2hex(random_bytes(4));
        $holder = self::newConnection();
        $holder->prepare('SELECT GET_LOCK(:n, 5)')->execute(['n' => $barrier]);

        $workers = [];
        for ($i = 0; $i < 8; $i++) {
            $workers[] = $this->worker('createScope', ['code' => 'c' . $i, 'name' => 'C' . $i], 'strict', $barrier);
        }

        foreach ($this->releaseTogether($barrier, $holder, $workers) as $outcome) {
            self::assertTrue($outcome['ok'], 'create failed: ' . json_encode($outcome));
        }

        self::assertSame(range(0, 8), $this->scopePositions());
    }

    public function testConcurrentFirstCreatesIntoAnEmptyOrderingNeverCorruptTheOrder(): void
    {
        // Documented caveat: an EMPTY ordering has no row to lock, so InnoDB may
        // pick one concurrent creator as a deadlock victim (SQLSTATE 40001,
        // propagated UNCHANGED for the caller to retry). It must never produce
        // duplicate positions or an unclassified failure.
        $this->pdo()->exec('DELETE FROM maa_i18n_domain_scopes');
        $this->pdo()->exec('DELETE FROM maa_i18n_scopes');

        $barrier = 'i18n_it_go_' . bin2hex(random_bytes(4));
        $holder = self::newConnection();
        $holder->prepare('SELECT GET_LOCK(:n, 5)')->execute(['n' => $barrier]);

        $workers = [];
        for ($i = 0; $i < 4; $i++) {
            $workers[] = $this->worker('createScope', ['code' => 'e' . $i, 'name' => 'E' . $i], 'strict', $barrier);
        }

        $created = 0;
        foreach ($this->releaseTogether($barrier, $holder, $workers) as $outcome) {
            if ($outcome['ok']) {
                $created++;
                continue;
            }

            self::assertSame('PDOException', $outcome['class'] ?? null);
            self::assertStringContainsString('1213', $outcome['message'] ?? '', 'only a deadlock-victim error is acceptable');
        }

        $positions = $this->scopePositions();
        self::assertCount($created, $positions);
        self::assertSame($positions, array_values(array_unique($positions)), 'no duplicated display position');
    }
}
