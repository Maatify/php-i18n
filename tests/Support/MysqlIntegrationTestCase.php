<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Support;

use DateTimeZone;
use Maatify\I18n\Enum\I18nPolicyModeEnum;
use Maatify\I18n\Repository\Mysql\MysqlDomainLanguageSummaryRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlI18nOperationalStatsRepository;
use Maatify\I18n\Repository\Mysql\MysqlKeyStatsRepository;
use Maatify\I18n\Repository\Mysql\MysqlScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationKeyRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationQueryRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationRepository;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\RenameKeyCommand;
use Maatify\I18n\Management\Command\UpsertTranslationCommand;
use Maatify\I18n\Management\Service\I18nDomainManagementService;
use Maatify\I18n\Management\Service\I18nManagementReadService;
use Maatify\I18n\Management\Service\I18nOperationalReadService;
use Maatify\I18n\Management\Service\I18nScopeDomainManagementService;
use Maatify\I18n\Management\Service\I18nScopeManagementService;
use Maatify\I18n\Service\I18nGovernancePolicyService;
use Maatify\I18n\Management\Service\I18nStatsRebuilder;
use Maatify\I18n\Service\MissingCounterService;
use Maatify\I18n\Consumer\Service\TranslationDomainReadService;
use Maatify\I18n\Consumer\Service\TranslationReadService;
use Maatify\I18n\Management\Service\TranslationWriteService;
use Maatify\Persistence\Pdo\Transaction\PdoTransactionRunner;
use Maatify\SharedCommon\Infrastructure\SystemClock;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Base for real-MySQL tests. Creates one random temporary schema per test
 * class from `schema/schema.i18n.sql` (only the I18n tables — no `languages`
 * table exists) and drops exactly that schema afterwards.
 *
 * Seeds one active scope `ct`, one active domain `home`, mapped together.
 */
abstract class MysqlIntegrationTestCase extends TestCase
{
    protected const TABLES = [
        'maa_i18n_domain_language_summary',
        'maa_i18n_key_stats',
        'maa_i18n_translations',
        'maa_i18n_keys',
        'maa_i18n_domain_scopes',
        'maa_i18n_domains',
        'maa_i18n_scopes',
    ];

    protected static ?PDO $pdo = null;
    private static string $schema = '';

    protected MysqlScopeRepository $scopes;
    protected MysqlDomainRepository $domains;
    protected MysqlDomainScopeRepository $domainScopes;
    protected I18nGovernancePolicyService $policy;
    protected MysqlTranslationKeyRepository $keys;
    protected MysqlTranslationRepository $translations;
    protected MysqlDomainLanguageSummaryRepository $summary;
    protected MysqlKeyStatsRepository $keyStats;
    protected PdoTransactionRunner $tx;
    protected MysqlTranslationQueryRepository $queries;
    protected MysqlI18nOperationalStatsRepository $stats;
    protected I18nScopeManagementService $scopeManagement;
    protected I18nDomainManagementService $domainManagement;
    protected I18nScopeDomainManagementService $scopeDomainManagement;
    protected I18nManagementReadService $managementRead;
    protected I18nOperationalReadService $operationalRead;
    protected TranslationWriteService $writer;
    protected TranslationReadService $reader;
    protected TranslationDomainReadService $domainReader;
    protected I18nStatsRebuilder $rebuilder;

    public static function setUpBeforeClass(): void
    {
        $credentials = MysqlTestEnvironment::credentials();
        if ($credentials === null) {
            if (MysqlTestEnvironment::isRequired()) {
                self::fail('I18N_IT_REQUIRED=1 but I18N_IT_DB_* credentials are not set.');
            }
            self::markTestSkipped('I18N_IT_DB_* env credentials not set — integration tests are opt-in.');
        }

        try {
            $pdo = MysqlTestEnvironment::connect($credentials);
        } catch (PDOException $e) {
            if (MysqlTestEnvironment::isRequired()) {
                self::fail('Required MySQL is unreachable: ' . $e->getMessage());
            }
            self::markTestSkipped('MySQL is not reachable: ' . $e->getMessage());
        }

        self::$schema = MysqlTestEnvironment::randomSchemaName('t');
        $pdo->exec('CREATE DATABASE `' . self::$schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . self::$schema . '`');

        $sql = file_get_contents(dirname(__DIR__, 2) . '/schema/schema.i18n.sql');
        self::assertIsString($sql);
        $pdo->exec($sql);

        self::$pdo = $pdo;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$schema !== '' && self::$pdo !== null) {
            self::$pdo->exec('DROP DATABASE IF EXISTS `' . self::$schema . '`');
        }
        self::$pdo = null;
        self::$schema = '';
    }

    protected function setUp(): void
    {
        $pdo = $this->pdo();

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::TABLES as $table) {
            $pdo->exec('DELETE FROM ' . $table);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        $pdo->exec("INSERT INTO maa_i18n_scopes (code, name) VALUES ('ct', 'Website')");
        $pdo->exec("INSERT INTO maa_i18n_domains (code, name) VALUES ('home', 'Home')");
        $pdo->exec("INSERT INTO maa_i18n_domain_scopes (scope_code, domain_code) VALUES ('ct', 'home')");

        $clock = new SystemClock(new DateTimeZone('UTC'));
        $this->scopes = new MysqlScopeRepository($pdo);
        $this->domains = new MysqlDomainRepository($pdo);
        $this->domainScopes = new MysqlDomainScopeRepository($pdo);
        $this->policy = $this->policyFor(I18nPolicyModeEnum::STRICT);

        $this->keys = new MysqlTranslationKeyRepository($pdo);
        $this->translations = new MysqlTranslationRepository($pdo, $clock);
        $this->summary = new MysqlDomainLanguageSummaryRepository($pdo);
        $this->keyStats = new MysqlKeyStatsRepository($pdo);
        $this->tx = new PdoTransactionRunner($pdo);
        $this->queries = new MysqlTranslationQueryRepository($pdo);
        $this->stats = new MysqlI18nOperationalStatsRepository($pdo);

        $counter = new MissingCounterService($this->summary, $this->keys, $this->keyStats);
        $this->writer = new TranslationWriteService($this->tx, $this->keys, $this->translations, $this->policy, $counter);
        $this->reader = new TranslationReadService($this->keys, $this->translations);
        $this->domainReader = new TranslationDomainReadService($this->keys, $this->translations, $this->policy);
        $this->rebuilder = new I18nStatsRebuilder($this->tx, $this->summary, $this->keyStats);
        $this->scopeManagement = new I18nScopeManagementService($this->tx, $this->scopes, $this->domainScopes, $this->keys);
        $this->domainManagement = new I18nDomainManagementService($this->tx, $this->domains, $this->domainScopes, $this->keys);
        $this->scopeDomainManagement = new I18nScopeDomainManagementService($this->tx, $this->scopes, $this->domains, $this->domainScopes);
        $this->managementRead = new I18nManagementReadService($this->scopes, $this->domains, $this->domainScopes, $this->keys, $this->queries);
        $this->operationalRead = new I18nOperationalReadService($this->stats);
    }

    protected function createKey(string $scope, string $domain, string $key, ?string $description = null): int
    {
        return $this->writer->createKey(new CreateKeyCommand($scope, $domain, $key, $description));
    }

    protected function renameKey(int $keyId, string $scope, string $domain, string $key): void
    {
        $this->writer->renameKey(new RenameKeyCommand($keyId, $scope, $domain, $key));
    }

    protected function upsert(?string $languageCode, int $keyId, string $value, ?string $type): int
    {
        return $this->writer->upsertTranslation(new UpsertTranslationCommand(
            languageCode: $languageCode,
            keyId: $keyId,
            value: $value,
            type: $type,
        ));
    }

    /**
     * Name of the random temporary schema of this test class.
     */
    protected static function schemaName(): string
    {
        return self::$schema;
    }

    /**
     * A NEW real connection (own server session) to the same temporary
     * schema. Used to prove locking / concurrency semantics.
     */
    protected static function newConnection(): PDO
    {
        $credentials = MysqlTestEnvironment::credentials();
        self::assertNotNull($credentials);

        $pdo = MysqlTestEnvironment::connect($credentials);
        $pdo->exec('USE `' . self::$schema . '`');

        return $pdo;
    }

    protected function pdo(): PDO
    {
        self::assertInstanceOf(PDO::class, self::$pdo);

        return self::$pdo;
    }

    protected function policyFor(I18nPolicyModeEnum $mode): I18nGovernancePolicyService
    {
        return new I18nGovernancePolicyService($this->scopes, $this->domains, $this->domainScopes, $mode);
    }

    protected function scalarInt(string $sql): int
    {
        $stmt = $this->pdo()->query($sql);
        self::assertNotFalse($stmt);
        $value = $stmt->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }
}
