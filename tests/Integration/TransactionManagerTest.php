<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use LogicException;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;
use RuntimeException;

final class TransactionManagerTest extends MysqlIntegrationTestCase
{
    public function testSuccessfulOwnedTransactionPreservesResultAndCommits(): void
    {
        $pdo = $this->pdo();
        $seenInTransaction = null;

        $result = $this->tx->run(function () use ($pdo, &$seenInTransaction): string {
            $seenInTransaction = $pdo->inTransaction();
            $pdo->exec("INSERT INTO maa_i18n_scopes (code, name) VALUES ('tx', 'Tx')");

            return 'result';
        });

        self::assertSame('result', $result);
        self::assertTrue($seenInTransaction);
        self::assertFalse($pdo->inTransaction());
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_scopes WHERE code = 'tx'"));
    }

    public function testFailureRollsBackAndRethrowsTheSameThrowable(): void
    {
        $pdo = $this->pdo();
        $original = new RuntimeException('boom');

        $caught = null;
        try {
            $this->tx->run(static function () use ($pdo, $original): int {
                $pdo->exec("INSERT INTO maa_i18n_scopes (code, name) VALUES ('tx', 'Tx')");

                throw $original;
            });
        } catch (\Throwable $e) {
            $caught = $e;
        }

        self::assertSame($original, $caught, 'the original throwable must be rethrown unwrapped');

        self::assertFalse($pdo->inTransaction());
        self::assertSame(0, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_scopes WHERE code = 'tx'"));
    }

    public function testExistingOuterTransactionIsNeitherCommittedNorRolledBackOnSuccess(): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        $result = $this->tx->run(static function () use ($pdo): int {
            $pdo->exec("INSERT INTO maa_i18n_scopes (code, name) VALUES ('tx', 'Tx')");

            return 7;
        });

        self::assertSame(7, $result);
        self::assertTrue($pdo->inTransaction(), 'run() must leave the caller-owned transaction open');

        // Not committed: the caller can still roll it back and nothing persists.
        $pdo->rollBack();
        self::assertSame(0, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_scopes WHERE code = 'tx'"));
    }

    public function testExistingOuterTransactionStaysOwnedByCallerOnFailure(): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        $original = new LogicException('inner failure');

        $caught = null;
        try {
            $this->tx->run(static function () use ($pdo, $original): int {
                $pdo->exec("INSERT INTO maa_i18n_scopes (code, name) VALUES ('tx', 'Tx')");

                throw $original;
            });
        } catch (\Throwable $e) {
            $caught = $e;
        }

        self::assertSame($original, $caught, 'the original throwable must be rethrown unwrapped');

        self::assertTrue($pdo->inTransaction(), 'run() must not roll back a caller-owned transaction');
        // The insert made inside the caller's transaction is still pending (visible to it).
        self::assertSame(1, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_scopes WHERE code = 'tx'"));

        $pdo->rollBack();
        self::assertSame(0, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_scopes WHERE code = 'tx'"));
    }
}
