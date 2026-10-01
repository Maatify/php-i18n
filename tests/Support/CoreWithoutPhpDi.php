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

/**
 * Child process of PhpDiOptionalIntegrationTest: builds the I18n CORE with PHP-DI
 * and PSR-11 made unavailable (their autoloading is blocked), exactly like a
 * consumer that never installed the `suggest` packages.
 *
 * Prints "CORE-OK" on success; any failure prints the reason and exits non-zero.
 */

use Maatify\I18n\Consumer\Service\TranslationReadService;
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

$root = dirname(__DIR__, 2);

require $root . '/vendor/autoload.php';

// From here on the optional integration packages "do not exist".
spl_autoload_register(
    static function (string $class): void {
        if (str_starts_with($class, 'DI\\') || str_starts_with($class, 'Psr\\Container\\')) {
            throw new RuntimeException('BLOCKED optional dependency: ' . $class);
        }
    },
    true,
    true,
);

// Control: the block is effective - the optional packages really cannot load.
$blocked = 0;
foreach (['DI\\ContainerBuilder', 'Psr\\Container\\ContainerInterface'] as $optional) {
    try {
        class_exists($optional);
    } catch (RuntimeException) {
        $blocked++;
    }
}

if ($blocked !== 2) {
    fwrite(STDERR, "control failed: the optional packages are still loadable\n");
    exit(3);
}

// Every Core class (everything outside Adapter/) must load without PHP-DI / PSR-11.
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
$loaded = 0;
/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $relative = substr($file->getPathname(), strlen($root . '/src/'), -4);
    if (str_starts_with($relative, 'Adapter/')) {
        continue;
    }

    $class = 'Maatify\\I18n\\' . str_replace('/', '\\', $relative);
    if (!class_exists($class) && !interface_exists($class) && !enum_exists($class)) {
        fwrite(STDERR, 'cannot load core type ' . $class . "\n");
        exit(4);
    }
    $loaded++;
}

// Construct the whole core graph by plain `new` (no container).
$pdo = (new ReflectionClass(PDO::class))->newInstanceWithoutConstructor();
$tx = new PdoTransactionRunner($pdo);
$scopes = new MysqlScopeRepository($pdo);
$domains = new MysqlDomainRepository($pdo);
$domainScopes = new MysqlDomainScopeRepository($pdo);
$keys = new MysqlTranslationKeyRepository($pdo);
$translations = new MysqlTranslationRepository($pdo, new SystemClock(new DateTimeZone('UTC')));
$summary = new MysqlDomainLanguageSummaryRepository($pdo);
$keyStats = new MysqlKeyStatsRepository($pdo);
$queries = new MysqlTranslationQueryRepository($pdo);
$stats = new MysqlI18nOperationalStatsRepository($pdo);
$policy = new I18nGovernancePolicyService($scopes, $domains, $domainScopes);
$services = [
    new TranslationReadService($keys, $translations),
    new TranslationWriteService($tx, $keys, $translations, $policy, new MissingCounterService($summary, $keys, $keyStats)),
    new I18nScopeManagementService($tx, $scopes, $domainScopes, $keys),
    new I18nDomainManagementService($tx, $domains, $domainScopes, $keys),
    new I18nScopeDomainManagementService($tx, $scopes, $domains, $domainScopes),
    new I18nManagementReadService($scopes, $domains, $domainScopes, $keys, $queries),
    new I18nOperationalReadService($stats),
    new I18nStatsRebuilder($tx, $summary, $keyStats),
];

echo 'CORE-OK ' . $loaded . ' types, ' . count($services) . " services\n";
