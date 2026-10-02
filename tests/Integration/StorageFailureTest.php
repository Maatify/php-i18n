<?php

/**
 * @copyright   ©2026 Maatify.dev
 * @Library     maatify/i18n
 * @Project     maatify:i18n
 * @author      Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @since       2026-10-01 00:00
 * @see         https://www.maatify.dev Maatify.dev
 * @link        https://github.com/Maatify/i18n view Project on GitHub
 * @note        Distributed in the hope that it will be useful - WITHOUT WARRANTY.
 */

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Consumer\Service\TranslationReadService;
use Maatify\I18n\Exception\I18nExceptionInterface;
use Maatify\I18n\Exception\I18nStorageException;
use Maatify\I18n\Repository\Mysql\MysqlDomainLanguageSummaryRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlI18nOperationalStatsRepository;
use Maatify\I18n\Repository\Mysql\MysqlKeyStatsRepository;
use Maatify\I18n\Repository\Mysql\MysqlScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationKeyRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationRepository;
use Maatify\I18n\Service\I18nGovernancePolicyService;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;
use Maatify\SharedCommon\Infrastructure\SystemClock;
use PDO;
use PDOException;

/**
 * GA-F06 on the real engine: broken storage must be distinguishable from a
 * genuine miss in EVERY read path, for thrown and for non-throwing failures.
 */
final class StorageFailureTest extends MysqlIntegrationTestCase
{
    private ?string $brokenSchema = null;

    /**
     * A connection whose current schema exists but owns NO I18n tables.
     */
    private function brokenConnection(int $errorMode): PDO
    {
        $name = self::schemaName() . '_broken';
        $this->pdo()->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '`');
        $this->brokenSchema = $name;

        $pdo = self::newConnection();
        $pdo->exec('USE `' . $name . '`');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, $errorMode);

        return $pdo;
    }

    protected function tearDown(): void
    {
        if ($this->brokenSchema !== null) {
            $this->pdo()->exec('DROP DATABASE IF EXISTS `' . $this->brokenSchema . '`');
            $this->brokenSchema = null;
        }

        parent::tearDown();
    }

    /**
     * @return list<array{string, callable(PDO): mixed}>
     */
    private static function reads(): array
    {
        $clock = new SystemClock(new \DateTimeZone('UTC'));

        return [
            ['scope.getByCode', static fn(PDO $p) => (new MysqlScopeRepository($p))->getByCode('ct')],
            ['scope.listAll', static fn(PDO $p) => (new MysqlScopeRepository($p))->listAll()],
            ['domain.getByCode', static fn(PDO $p) => (new MysqlDomainRepository($p))->getByCode('home')],
            ['domain.listByCodes', static fn(PDO $p) => (new MysqlDomainRepository($p))->listByCodes(['home'])],
            ['domainScope.isAllowed', static fn(PDO $p) => (new MysqlDomainScopeRepository($p))->isDomainAllowedForScope('ct', 'home')],
            ['domainScope.listDomains', static fn(PDO $p) => (new MysqlDomainScopeRepository($p))->listDomainsForScope('ct')],
            ['key.getById', static fn(PDO $p) => (new MysqlTranslationKeyRepository($p))->getById(1)],
            ['key.getByStructuredKey', static fn(PDO $p) => (new MysqlTranslationKeyRepository($p))->getByStructuredKey('ct', 'home', 'k')],
            ['key.listByScopeAndDomain', static fn(PDO $p) => (new MysqlTranslationKeyRepository($p))->listByScopeAndDomain('ct', 'home')],
            ['translation.getByLanguageAndKey', static fn(PDO $p) => (new MysqlTranslationRepository($p, $clock))->getByLanguageAndKey('ar', 1)],
            ['translation.exists', static fn(PDO $p) => (new MysqlTranslationRepository($p, $clock))->existsByLanguageAndKey('ar', 1)],
            ['summary.getRow', static fn(PDO $p) => (new MysqlDomainLanguageSummaryRepository($p))->getRow('ct', 'home', 'ar')],
            ['keyStats.get', static fn(PDO $p) => (new MysqlKeyStatsRepository($p))->getTranslatedCount(1)],
            ['stats.totalKeys', static fn(PDO $p) => (new MysqlI18nOperationalStatsRepository($p))->totalKeyCount()],
            ['policy.readable', static fn(PDO $p) => (new I18nGovernancePolicyService(
                new MysqlScopeRepository($p),
                new MysqlDomainRepository($p),
                new MysqlDomainScopeRepository($p),
            ))->isScopeAndDomainReadable('ct', 'home')],
            ['consumer.getValue', static fn(PDO $p) => (new TranslationReadService(
                new MysqlTranslationKeyRepository($p),
                new MysqlTranslationRepository($p, new SystemClock(new \DateTimeZone('UTC'))),
            ))->getValue('ar', 'ct', 'home', 'k')],
        ];
    }

    public function testGenuineMissesStayMissesOnWorkingStorage(): void
    {
        $pdo = $this->pdo();
        $clock = new SystemClock(new \DateTimeZone('UTC'));

        self::assertNull((new MysqlScopeRepository($pdo))->getByCode('nope'));
        self::assertNull((new MysqlScopeRepository($pdo))->getById(999999));
        self::assertNull((new MysqlDomainRepository($pdo))->getByCode('nope'));
        self::assertTrue((new MysqlDomainRepository($pdo))->listByCodes(['nope'])->isEmpty());
        self::assertFalse((new MysqlDomainScopeRepository($pdo))->isDomainAllowedForScope('nope', 'nope'));
        self::assertSame([], (new MysqlDomainScopeRepository($pdo))->listDomainsForScope('nope'));
        self::assertNull((new MysqlTranslationKeyRepository($pdo))->getById(999999));
        self::assertNull((new MysqlTranslationKeyRepository($pdo))->getByStructuredKey('ct', 'home', 'nope'));
        self::assertTrue((new MysqlTranslationKeyRepository($pdo))->listByScopeAndDomain('nope', 'nope')->isEmpty());
        self::assertNull((new MysqlTranslationRepository($pdo, $clock))->getByLanguageAndKey('ar', 999999));
        self::assertFalse((new MysqlTranslationRepository($pdo, $clock))->existsByLanguageAndKey('ar', 999999));
        self::assertNull((new MysqlDomainLanguageSummaryRepository($pdo))->getRow('ct', 'home', 'ar'));
        self::assertSame(0, (new MysqlKeyStatsRepository($pdo))->getTranslatedCount(999999));
        self::assertSame(0, (new MysqlI18nOperationalStatsRepository($pdo))->totalKeyCount());

        // governance miss => fail-soft false; consumer miss => null
        $policy = new I18nGovernancePolicyService(
            new MysqlScopeRepository($pdo),
            new MysqlDomainRepository($pdo),
            new MysqlDomainScopeRepository($pdo),
        );
        self::assertFalse($policy->isScopeAndDomainReadable('nope', 'nope'));
        self::assertNull($this->reader->getValue('ar', 'ct', 'home', 'nope'));
    }

    public function testThrownStorageFailuresPropagateUnchangedInEveryReadPath(): void
    {
        $broken = $this->brokenConnection(PDO::ERRMODE_EXCEPTION);

        foreach (self::reads() as [$name, $read]) {
            try {
                $read($broken);
                self::fail($name . ' swallowed a storage failure (looked like a miss)');
            } catch (PDOException $e) {
                // the original diagnostic contract: table does not exist
                self::assertSame('42S02', $e->getCode(), $name);
                self::assertNotInstanceOf(I18nExceptionInterface::class, $e, $name);
            }
        }
    }

    public function testNonThrowingStorageFailuresBecomeAPackageStorageException(): void
    {
        $broken = $this->brokenConnection(PDO::ERRMODE_SILENT);

        foreach (self::reads() as [$name, $read]) {
            try {
                $read($broken);
                self::fail($name . ' reported a non-throwing storage failure as a miss');
            } catch (I18nStorageException $e) {
                self::assertInstanceOf(I18nExceptionInterface::class, $e, $name);
            }
        }
    }

    public function testANonThrowingWriteFailureIsAStorageExceptionNotASilentSuccess(): void
    {
        $silent = self::newConnection();
        $silent->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $repository = new MysqlTranslationRepository($silent, new SystemClock(new \DateTimeZone('UTC')));

        // key 999999 does not exist -> the FK rejects the row at execute()
        $this->expectException(I18nStorageException::class);
        $repository->upsert('ar', 999999, 'value', null);
    }
}
