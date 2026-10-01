<?php

/**
 * Example 01 - Core wiring and the first translation.
 *
 * Capability: construct the Core without any container, bootstrap governance,
 * create a key, write exact translations and read them back.
 *
 *   Input -> Public Call -> Result -> Boundary
 *   scope/domain/key/value -> create / assign / createKey / upsertTranslation / getValue
 *   -> exact value or null -> your PDO (MySQL, PDO::ERRMODE_EXCEPTION)
 *
 * Usage guide: docs/guides/USAGE_GUIDE.md (section "Wire the Core")
 */

declare(strict_types=1);

require __DIR__ . '/_support/bootstrap.php';

use Maatify\I18n\Consumer\Service\TranslationDomainReadService;
use Maatify\I18n\Consumer\Service\TranslationReadService;
use Maatify\I18n\Enum\I18nPolicyModeEnum;
use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Management\Command\UpsertTranslationCommand;
use Maatify\I18n\Management\Service\I18nDomainManagementService;
use Maatify\I18n\Management\Service\I18nScopeDomainManagementService;
use Maatify\I18n\Management\Service\I18nScopeManagementService;
use Maatify\I18n\Management\Service\TranslationWriteService;
use Maatify\I18n\Repository\Mysql\MysqlDomainLanguageSummaryRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlKeyStatsRepository;
use Maatify\I18n\Repository\Mysql\MysqlScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationKeyRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationRepository;
use Maatify\I18n\Service\I18nGovernancePolicyService;
use Maatify\I18n\Service\MissingCounterService;
use Maatify\Persistence\Pdo\Transaction\PdoTransactionRunner;
use Maatify\SharedCommon\Infrastructure\SystemClock;

echo 'Example 01 - Core wiring and the first translation', PHP_EOL;

// The connection is yours. Here it points at a disposable database that already
// holds the canonical Package schema (schema/schema.i18n.sql).
$pdo = example_pdo();

// 1. Repositories: PDO implementations of the public repository contracts.
$clock = new SystemClock(new DateTimeZone('UTC'));
$scopeRepository = new MysqlScopeRepository($pdo);
$domainRepository = new MysqlDomainRepository($pdo);
$domainScopeRepository = new MysqlDomainScopeRepository($pdo);
$keyRepository = new MysqlTranslationKeyRepository($pdo);
$translationRepository = new MysqlTranslationRepository($pdo, $clock);
$summaryRepository = new MysqlDomainLanguageSummaryRepository($pdo);
$keyStatsRepository = new MysqlKeyStatsRepository($pdo);

// 2. Transactions come from maatify/persistence, on the SAME connection.
$transactions = new PdoTransactionRunner($pdo);

// 3. Services.
$policy = new I18nGovernancePolicyService(
    $scopeRepository,
    $domainRepository,
    $domainScopeRepository,
    I18nPolicyModeEnum::STRICT,
);
$counter = new MissingCounterService($summaryRepository, $keyRepository, $keyStatsRepository);

$scopes = new I18nScopeManagementService($transactions, $scopeRepository, $domainScopeRepository, $keyRepository);
$domains = new I18nDomainManagementService($transactions, $domainRepository, $domainScopeRepository, $keyRepository);
$assignments = new I18nScopeDomainManagementService(
    $transactions,
    $scopeRepository,
    $domainRepository,
    $domainScopeRepository,
);
$writer = new TranslationWriteService($transactions, $keyRepository, $translationRepository, $policy, $counter);
$reader = new TranslationReadService($keyRepository, $translationRepository);
$domainReader = new TranslationDomainReadService($keyRepository, $translationRepository, $policy);

// 4. Governance first: a key can only exist inside an assigned (scope, domain).
$scopes->create(new CreateScopeCommand('web', 'Website'));
$domains->create(new CreateDomainCommand('home', 'Home page'));
$assignments->assign('web', 'home');

// 5. A key, then exact translations of it.
$keyId = $writer->createKey(new CreateKeyCommand('web', 'home', 'title', 'Title of the home page'));
$writer->upsertTranslation(new UpsertTranslationCommand('en', $keyId, 'Welcome'));
$writer->upsertTranslation(new UpsertTranslationCommand('ar', $keyId, 'مرحبا'));

// 6. Exact reads. The language code is whatever your application uses.
example_expect('reads the English value', 'Welcome', $reader->getValue('en', 'web', 'home', 'title'));
example_expect('reads the Arabic value', 'مرحبا', $reader->getValue('ar', 'web', 'home', 'title'));
example_expect('a language without a row is exactly missing', null, $reader->getValue('fr', 'web', 'home', 'title'));
example_expect(
    'a whole domain is read in one call',
    ['title' => 'Welcome'],
    $domainReader->getDomainValues('en', 'web', 'home')->all(),
);
