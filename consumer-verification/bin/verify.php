<?php

/**
 * External-consumer proof for maatify/php-i18n.
 *
 * This script is the entire consumer: it runs from a Composer root that is
 * separate from the Package root, resolves the Package as a Composer
 * dependency, and talks to it only through its production autoload and its
 * documented public API against a real MySQL. It prints one deterministic
 * line per check, so two clean runs can be compared byte for byte.
 *
 * Required environment (provided by the Package-owned MySQL lifecycle,
 * scripts/ci/with-mysql.sh): I18N_IT_DB_HOST, I18N_IT_DB_PORT,
 * I18N_IT_DB_USER, I18N_IT_DB_PASS.
 */

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Maatify\I18n\Consumer\Service\TranslationDomainReadService;
use Maatify\I18n\Consumer\Service\TranslationReadService;
use Maatify\I18n\Enum\I18nPolicyModeEnum;
use Maatify\I18n\Exception\I18nExceptionInterface;
use Maatify\I18n\Exception\InvalidLanguageCodeException;
use Maatify\I18n\Exception\LanguageCodeAlreadyInUseException;
use Maatify\I18n\Exception\ScopeNotAllowedException;
use Maatify\I18n\Exception\TranslationKeyAlreadyExistsException;
use Maatify\I18n\Exception\TranslationKeyNotFoundException;
use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Management\Command\UpsertTranslationCommand;
use Maatify\I18n\Management\Criteria\DomainTranslationGridCriteria;
use Maatify\I18n\Management\Service\I18nDomainManagementService;
use Maatify\I18n\Management\Service\I18nManagementReadService;
use Maatify\I18n\Management\Service\I18nOperationalReadService;
use Maatify\I18n\Management\Service\I18nScopeDomainManagementService;
use Maatify\I18n\Management\Service\I18nScopeManagementService;
use Maatify\I18n\Management\Service\I18nStatsRebuilder;
use Maatify\I18n\Management\Service\TranslationWriteService;
use Maatify\I18n\Repository\Mysql\MysqlDomainLanguageSummaryRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlI18nOperationalStatsRepository;
use Maatify\I18n\Repository\Mysql\MysqlKeyStatsRepository;
use Maatify\I18n\Repository\Mysql\MysqlScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationKeyRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationQueryRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationRepository;
use Maatify\I18n\Service\I18nGovernancePolicyService;
use Maatify\I18n\Service\MissingCounterService;
use Maatify\Persistence\Pdo\Transaction\PdoTransactionRunner;
use Maatify\SharedCommon\Infrastructure\SystemClock;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

/**
 * Counts passed checks and prints each as one deterministic output line.
 */
final class Checks
{
    private static int $count = 0;

    public static function passed(string $name): void
    {
        self::$count++;
        echo sprintf("OK %02d %s\n", self::$count, $name);
    }

    public static function count(): int
    {
        return self::$count;
    }
}

function same(string $name, mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "Check failed: %s\n  expected: %s\n  actual:   %s",
            $name,
            var_export($expected, true),
            var_export($actual, true),
        ));
    }

    Checks::passed($name);
}

/**
 * @param class-string<Throwable> $class
 */
function throws(string $name, string $class, callable $action): void
{
    try {
        $action();
    } catch (Throwable $e) {
        if (!$e instanceof $class) {
            throw new RuntimeException(sprintf(
                'Check failed: %s (expected %s, got %s: %s)',
                $name,
                $class,
                $e::class,
                $e->getMessage(),
            ), 0, $e);
        }

        same($name, true, $e instanceof I18nExceptionInterface);

        return;
    }

    throw new RuntimeException(sprintf('Check failed: %s (nothing was thrown)', $name));
}

/**
 * @return list<array<mixed>>
 */
function rows(PDO $pdo, string $sql): array
{
    $statement = $pdo->query($sql);
    if ($statement === false) {
        throw new RuntimeException('Query failed: ' . $sql);
    }

    $result = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!is_array($row)) {
            throw new RuntimeException('Unexpected row shape for: ' . $sql);
        }
        $result[] = $row;
    }

    return $result;
}

function env(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        throw new RuntimeException(sprintf(
            'Missing %s; run this harness through scripts/ci/run-consumer-verification.sh.',
            $name,
        ));
    }

    return $value;
}

// ---------------------------------------------------------------------------
// 1. Hermetic dependency boundary: production autoload, no Host, no test code.
// ---------------------------------------------------------------------------

same('PHP-DI is not installed (Core needs no container)', false, class_exists('DI\ContainerBuilder'));
same('psr/container is not installed', false, interface_exists('Psr\Container\ContainerInterface'));
same(
    'Package tests are not autoloadable',
    false,
    class_exists('Maatify\I18n\Tests\Support\MysqlTestEnvironment'),
);

$loaders = ClassLoader::getRegisteredLoaders();
same('exactly one Composer autoloader is registered', 1, count($loaders));
$prefixes = array_keys(array_values($loaders)[0]->getPrefixesPsr4());
sort($prefixes);
same(
    'autoload prefixes are exactly the Package and its Maatify dependencies',
    ([
        'Maatify\Exceptions\\',
        'Maatify\I18n\\',
        'Maatify\Persistence\\',
        'Maatify\SharedCommon\\',
    ]),
    $prefixes,
);

$installed = $root . '/vendor/maatify/php-i18n';
same('Package is installed by Composer as a mirrored copy, not a symlink', false, is_link($installed));
same(
    'Package classes resolve from the consumer vendor directory',
    realpath($installed . '/src/Management/Service/TranslationWriteService.php'),
    (new ReflectionClass(TranslationWriteService::class))->getFileName(),
);

// ---------------------------------------------------------------------------
// 2. Real MySQL: a fresh canonical Package schema in a disposable database.
// ---------------------------------------------------------------------------

$schemaName = 'i18n_it_cv_' . bin2hex(random_bytes(6));
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', env('I18N_IT_DB_HOST'), (int) env('I18N_IT_DB_PORT')),
    env('I18N_IT_DB_USER'),
    env('I18N_IT_DB_PASS'),
    ([
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]),
);

try {
    $pdo->exec('CREATE DATABASE `' . $schemaName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . $schemaName . '`');

    $sql = file_get_contents($installed . '/schema/schema.i18n.sql');
    if ($sql === false) {
        throw new RuntimeException('The installed Package does not ship schema/schema.i18n.sql.');
    }
    $pdo->exec($sql);

    $tables = array_map(
        static fn(array $row): mixed => array_values($row)[0],
        rows($pdo, 'SHOW TABLES'),
    );
    sort($tables);
    same(
        'the canonical schema creates exactly the seven Package-owned tables',
        ([
            'maa_i18n_domain_language_summary',
            'maa_i18n_domain_scopes',
            'maa_i18n_domains',
            'maa_i18n_key_stats',
            'maa_i18n_keys',
            'maa_i18n_scopes',
            'maa_i18n_translations',
        ]),
        $tables,
    );

    // -----------------------------------------------------------------------
    // 3. Direct Core construction (no container).
    // -----------------------------------------------------------------------

    $clock = new SystemClock(new DateTimeZone('UTC'));
    $scopes = new MysqlScopeRepository($pdo);
    $domains = new MysqlDomainRepository($pdo);
    $domainScopes = new MysqlDomainScopeRepository($pdo);
    $keys = new MysqlTranslationKeyRepository($pdo);
    $translations = new MysqlTranslationRepository($pdo, $clock);
    $summary = new MysqlDomainLanguageSummaryRepository($pdo);
    $keyStats = new MysqlKeyStatsRepository($pdo);
    $queries = new MysqlTranslationQueryRepository($pdo);
    $stats = new MysqlI18nOperationalStatsRepository($pdo);
    $tx = new PdoTransactionRunner($pdo);

    $policy = new I18nGovernancePolicyService($scopes, $domains, $domainScopes, I18nPolicyModeEnum::STRICT);
    $counter = new MissingCounterService($summary, $keys, $keyStats);

    $scopeManagement = new I18nScopeManagementService($tx, $scopes, $domainScopes, $keys);
    $domainManagement = new I18nDomainManagementService($tx, $domains, $domainScopes, $keys);
    $assignments = new I18nScopeDomainManagementService($tx, $scopes, $domains, $domainScopes);
    $writer = new TranslationWriteService($tx, $keys, $translations, $policy, $counter);
    $reader = new TranslationReadService($keys, $translations);
    $domainReader = new TranslationDomainReadService($keys, $translations, $policy);
    $managementRead = new I18nManagementReadService($scopes, $domains, $domainScopes, $keys, $queries);
    $operationalRead = new I18nOperationalReadService($stats);
    $rebuilder = new I18nStatsRebuilder($tx, $summary, $keyStats);

    Checks::passed('Core services are constructed directly from the public API');

    // -----------------------------------------------------------------------
    // 4. Governance: scope, domain and their assignment.
    // -----------------------------------------------------------------------

    $scopeManagement->create(new CreateScopeCommand('web', 'Website'));
    $domainManagement->create(new CreateDomainCommand('home', 'Home'));

    throws(
        'a key outside governed usage is refused',
        ScopeNotAllowedException::class,
        static fn() => $writer->createKey(new CreateKeyCommand('nope', 'home', 'title')),
    );

    $assignments->assign('web', 'home');
    same('the domain is assigned to the scope', true, $managementRead->isDomainAssigned('web', 'home'));

    // -----------------------------------------------------------------------
    // 5. Keys and exact translation writes.
    // -----------------------------------------------------------------------

    $keyId = $writer->createKey(new CreateKeyCommand('web', 'home', 'title', 'Home page title'));
    same('a created key has a positive identity', true, $keyId > 0);
    same('the key reads back by identity', 'title', $managementRead->getKey($keyId)->key);

    throws(
        'a duplicate structured key is refused',
        TranslationKeyAlreadyExistsException::class,
        static fn() => $writer->createKey(new CreateKeyCommand('web', 'home', 'title')),
    );

    $writer->upsertTranslation(new UpsertTranslationCommand('ar', $keyId, 'مرحبا'));
    $writer->upsertTranslation(new UpsertTranslationCommand('en', $keyId, 'Welcome'));
    $writer->upsertTranslation(new UpsertTranslationCommand('fr', $keyId, ''));
    $writer->upsertTranslation(new UpsertTranslationCommand(null, $keyId, 'neutral'));
    $writer->upsertTranslation(new UpsertTranslationCommand('en', $keyId, 'Welcome back'));

    // -----------------------------------------------------------------------
    // 6. Exact reads: observable results.
    // -----------------------------------------------------------------------

    same('exact read: ar', 'مرحبا', $reader->getValue('ar', 'web', 'home', 'title'));
    same('exact read: updated value wins', 'Welcome back', $reader->getValue('en', 'web', 'home', 'title'));
    same('exact read: empty string is an authoritative value', '', $reader->getValue('fr', 'web', 'home', 'title'));
    same('exact read: null code reads the unlocalized scope', 'neutral', $reader->getValue(null, 'web', 'home', 'title'));
    same('exact missing: unknown code owns no row (no fallback)', null, $reader->getValue('de', 'web', 'home', 'title'));
    same('exact missing: codes are case-sensitive', null, $reader->getValue('AR', 'web', 'home', 'title'));
    same('exact missing: unknown key', null, $reader->getValue('ar', 'web', 'home', 'missing'));
    same('fail-soft: invalid code reads as missing', null, $reader->getValue('   ', 'web', 'home', 'title'));
    same(
        'bulk domain read returns the exact scope only',
        ['title' => 'مرحبا'],
        $domainReader->getDomainValues('ar', 'web', 'home')->all(),
    );
    same(
        'bulk domain read of an ungoverned domain is empty',
        [],
        $domainReader->getDomainValues('ar', 'web', 'nope')->all(),
    );

    // -----------------------------------------------------------------------
    // 7. Failure contract and persisted/derived state.
    // -----------------------------------------------------------------------

    throws(
        'unknown key identity on read',
        TranslationKeyNotFoundException::class,
        static fn() => $managementRead->getKey(999999),
    );
    throws(
        'unknown key identity on write',
        TranslationKeyNotFoundException::class,
        static fn() => $writer->upsertTranslation(new UpsertTranslationCommand('ar', 999999, 'x')),
    );
    throws(
        'an invalid code is refused at command construction',
        InvalidLanguageCodeException::class,
        static fn() => new UpsertTranslationCommand(str_repeat('x', 17), $keyId, 'x'),
    );

    $persisted = rows(
        $pdo,
        'SELECT language_code, value FROM maa_i18n_translations ORDER BY language_code_identity',
    );
    same(
        'persisted rows are exactly the four exact scopes',
        ([
            ['language_code' => null, 'value' => 'neutral'],
            ['language_code' => 'ar', 'value' => 'مرحبا'],
            ['language_code' => 'en', 'value' => 'Welcome back'],
            ['language_code' => 'fr', 'value' => ''],
        ]),
        $persisted,
    );

    same('operational read: total keys', 1, $operationalRead->totalKeyCount());
    same('operational read: derived summary rows', 4, $operationalRead->summaryRowCount());

    $coverage = $operationalRead->scopeKeyCoverage('web');
    same('operational read: scope coverage total', 1, $coverage->totalKeys);

    $grid = $managementRead->pageDomainTranslationGrid(new DomainTranslationGridCriteria(
        'web',
        'home',
        ['ar', 'de'],
    ));
    $gridValues = [];
    foreach ($grid->data as $row) {
        $gridValues[$row->languageCode] = $row->value;
    }
    ksort($gridValues);
    same('management read: grid marks a missing translation as null', ['ar' => 'مرحبا', 'de' => null], $gridValues);

    // -----------------------------------------------------------------------
    // 8. Transaction semantics and language-code identity migration.
    // -----------------------------------------------------------------------

    $pdo->beginTransaction();
    $rolledBackKey = $writer->createKey(new CreateKeyCommand('web', 'home', 'transient'));
    same('inside the caller transaction the write is visible', 'transient', $managementRead->getKey($rolledBackKey)->key);
    $pdo->rollBack();
    throws(
        'a caller rollback removes the participating write',
        TranslationKeyNotFoundException::class,
        static fn() => $managementRead->getKey($rolledBackKey),
    );
    same('the caller rollback left the committed state intact', 1, $operationalRead->totalKeyCount());

    same('re-key moves every row of the old code', 1, $writer->rekeyLanguageCode('ar', 'ar-EG'));
    same('re-key: old code is empty afterwards', null, $reader->getValue('ar', 'web', 'home', 'title'));
    same('re-key: new code owns the value', 'مرحبا', $reader->getValue('ar-EG', 'web', 'home', 'title'));
    throws(
        're-key onto an occupied code is refused',
        LanguageCodeAlreadyInUseException::class,
        static fn() => $writer->rekeyLanguageCode('ar-EG', 'en'),
    );

    // -----------------------------------------------------------------------
    // 9. Derived state is rebuildable and deterministic.
    // -----------------------------------------------------------------------

    $before = $operationalRead->summaryRowCount();
    $rebuilder->fullRebuild();
    same('full rebuild reproduces the derived summary', $before, $operationalRead->summaryRowCount());
} finally {
    $pdo->exec('DROP DATABASE IF EXISTS `' . $schemaName . '`');
}

same(
    'teardown: the disposable database is gone',
    [],
    rows($pdo, "SHOW DATABASES LIKE '" . $schemaName . "'"),
);

echo sprintf("CONSUMER VERIFICATION PASSED (%d checks)\n", Checks::count());
