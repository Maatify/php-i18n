# I18n Usage Guide

How to integrate `maatify/php-i18n` into an application. This guide presents the contract; it does not define one. The canonical contract and the complete Public Runtime API inventory live in [I18N_PACKAGE_REFERENCE.md](../../I18N_PACKAGE_REFERENCE.md).

> **State:** development, unpublished. The package is not on Packagist and has no tag or release; today it is consumed as an embedded Base Module (`Modules/I18n`). Composer requirements and dependencies are in [composer.json](../../composer.json).

## 1. Fit, Requirements and Boundaries

**Use I18n when** you want database-driven, governed translations: keys are structured as `scope.domain.key_part`, a key can only exist inside an assigned `(scope, domain)`, and values are stored per exact language code in MySQL.

**You need:**

- PHP `^8.4` with `ext-pdo`, `ext-pdo_mysql` and `ext-mbstring`;
- a MySQL database and a `PDO` connection with `PDO::ERRMODE_EXCEPTION`;
- the canonical schema applied once to a fresh database: [schema/schema.i18n.sql](../../schema/schema.i18n.sql) (it starts with `DROP TABLE IF EXISTS` for the seven `maa_i18n_*` tables, so never apply it over live data). For an existing pre-S1 database, apply the additive [translation type migration](../../schema/migrations/2026-10-02-translation-type.sql) through your migration process;
- a clock implementing `Maatify\SharedCommon\Contracts\ClockInterface` (for example `SystemClock`).

**I18n does not:** own languages or locale selection, fall back to another language, cache, load files, delete keys, or ship a migration framework. It ships the S1 schema migration asset; the Host controls when to apply it. ([Reference section 11](../../I18N_PACKAGE_REFERENCE.md#11-host-owned-responsibilities))

**Primary calls:**

| You want to | Call |
|---|---|
| read one value | `TranslationReadService::getValue()` |
| read one value with its optional type | `TranslationReadService::getTranslation()` |
| read a whole domain | `TranslationDomainReadService::getDomainValues()` |
| read a whole domain with each row's optional type | `TranslationDomainReadService::getDomainTranslations()` |
| define scopes, domains, assignments | the three governance management services |
| create keys, write values | `TranslationWriteService` |
| build an admin screen | `I18nManagementReadService` |
| show coverage numbers | `I18nOperationalReadService` |
| rename a language code | `TranslationWriteService::rekeyLanguageCode()` |

## 2. Capability Map

| Capability | Walkthrough | Example |
|---|---|---|
| Wire the Core without a container | [3](#3-wire-the-core) | [01](../../examples/01-core-wiring-and-first-translation.php) |
| Govern scopes and domains | [4](#4-govern-scopes-and-domains) | [02](../../examples/02-governance-management.php) |
| Create keys and write translations | [5](#5-create-keys-and-write-translations) | [01](../../examples/01-core-wiring-and-first-translation.php) |
| Exact runtime reads and Host-owned fallback | [6](#6-read-translations) | [03](../../examples/03-exact-reads-and-host-fallback.php) |
| Management reads and pagination | [7](#7-management-reads) | [04](../../examples/04-management-reads-and-operational-facts.php) |
| Operational facts and derived-state repair | [8](#8-operational-facts-and-derived-state-repair) | [04](../../examples/04-management-reads-and-operational-facts.php) |
| Transactions and language-code re-key | [9](#9-transactions-and-language-code-changes) | [05](../../examples/05-language-code-rekey-and-transactions.php) |
| Optional PHP-DI integration | [10](#10-optional-php-di-integration) | [06](../../examples/06-optional-php-di.php) |

The examples run against a disposable MySQL (`composer check:examples`) and are smoke-executed in CI, so they are kept true.

## 3. Wire the Core

**Input:** a `PDO`, a clock. **Public call:** construct repositories, then services. **Result:** ready-to-use services. **Boundary:** the connection is yours; use the same `PDO` everywhere you want atomicity.

```php
use Maatify\I18n\Consumer\Service\TranslationDomainReadService;
use Maatify\I18n\Consumer\Service\TranslationReadService;
use Maatify\I18n\Enum\I18nPolicyModeEnum;
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

$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$clock = new SystemClock(new DateTimeZone('UTC'));

$scopeRepository = new MysqlScopeRepository($pdo);
$domainRepository = new MysqlDomainRepository($pdo);
$domainScopeRepository = new MysqlDomainScopeRepository($pdo);
$keyRepository = new MysqlTranslationKeyRepository($pdo);
$translationRepository = new MysqlTranslationRepository($pdo, $clock);
$transactions = new PdoTransactionRunner($pdo);          // same connection

$policy = new I18nGovernancePolicyService($scopeRepository, $domainRepository, $domainScopeRepository, I18nPolicyModeEnum::STRICT);
$counter = new MissingCounterService(
    new MysqlDomainLanguageSummaryRepository($pdo),
    $keyRepository,
    new MysqlKeyStatsRepository($pdo),
);

$writer = new TranslationWriteService($transactions, $keyRepository, $translationRepository, $policy, $counter);
$reader = new TranslationReadService($keyRepository, $translationRepository);
$domainReader = new TranslationDomainReadService($keyRepository, $translationRepository, $policy);
```

`STRICT` is the production policy mode. The complete wiring, including the governance and management services, is in [example 01](../../examples/01-core-wiring-and-first-translation.php). No container is required.

## 4. Govern Scopes and Domains

**Input:** `CreateScopeCommand`, `CreateDomainCommand`, codes. **Public call:** `I18nScopeManagementService`, `I18nDomainManagementService`, `I18nScopeDomainManagementService`. **Result:** ids and persisted governance state. **Boundary:** each call is one I18n transaction, or joins yours.

```php
$scopes->create(new CreateScopeCommand('web', 'Website'));
$domains->create(new CreateDomainCommand('home', 'Home page'));
$assignments->assign('web', 'home');          // a key may now exist in (web, home)
```

Also available: `updateMetadata`, `setActive`, `moveToPosition` (display order is appended on create and only changed through this call) and `changeCode`, which succeeds only while the code is unused (`ScopeInUseException` / `DomainInUseException` otherwise). An inactive scope or domain refuses new keys.

## 5. Create Keys and Write Translations

**Input:** `CreateKeyCommand`, `UpsertTranslationCommand`. **Public call:** `TranslationWriteService`. **Result:** key id, translation id. **Boundary:** governance is enforced first; the derived summary is updated in the same transaction.

```php
$keyId = $writer->createKey(new CreateKeyCommand('web', 'home', 'title', 'Home page title'));

$writer->upsertTranslation(new UpsertTranslationCommand(languageCode: 'en', keyId: $keyId, value: 'Welcome', type: null));
$writer->upsertTranslation(new UpsertTranslationCommand(languageCode: 'ar', keyId: $keyId, value: 'مرحبا', type: null));
$writer->upsertTranslation(new UpsertTranslationCommand(languageCode: null, keyId: $keyId, value: 'Neutral', type: null));  // unlocalized scope
$writer->upsertTranslation(new UpsertTranslationCommand(languageCode: 'fr', keyId: $keyId, value: '', type: null));         // authoritative empty value

$richKeyId = $writer->createKey(new CreateKeyCommand('web', 'home', 'rich-copy'));
$writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'en',
    keyId: $richKeyId,
    value: '<p>Formatted copy</p>',
    type: 'client.rich-copy', // Example token defined by this consumer; I18n assigns it no behavior.
));
```

Every write supplies `type` explicitly. Use `null` when the consumer declares no type, or pass any valid consumer-defined token. `client.rich-copy` is only this example consumer's choice; the Package accepts valid tokens without a built-in vocabulary and stores the value and token as-is. It does not render or sanitize the value.

`renameKey` renames and/or moves a key and keeps its id and translations; `updateKeyDescription` changes the description; `deleteTranslation` removes one exact row. There is no key deletion. Failures are typed: `TranslationKeyAlreadyExistsException`, `ScopeNotAllowedException`, `DomainNotAllowedException`, `DomainScopeViolationException`, `TranslationKeyNotFoundException`, `InvalidLanguageCodeException` (full catalog: [Reference 6.2](../../I18N_PACKAGE_REFERENCE.md#62-exception-catalog)).

## 6. Read Translations

**Input:** exact language code (or `null`), scope, domain, key. **Public call:** `getValue` / `getTranslation` and `getDomainValues` / `getDomainTranslations`. **Result:** the exact string or value-only DTO, or a rich DTO containing both value and type. **Boundary:** reads are fail-soft and never fall back.

```php
$reader->getValue('ar', 'web', 'home', 'title');          // 'مرحبا'
$reader->getValue('de', 'web', 'home', 'title');          // null: 'de' owns no row, nothing falls back
$reader->getValue('fr', 'web', 'home', 'title');          // '' : an empty value is a real value
$reader->getValue(null, 'web', 'home', 'title');          // 'Neutral': the unlocalized scope only
$reader->getValue('   ', 'web', 'home', 'title');         // null: an invalid code reads as a miss
$reader->getTranslation('en', 'web', 'home', 'rich-copy'); // TranslationValueDTO: value + 'client.rich-copy'

$domainReader->getDomainValues('ar', 'web', 'home')->all();   // ['title' => 'مرحبا']
$domainReader->getDomainTranslations('en', 'web', 'home')->get('rich-copy'); // value + type
```

The value-only methods keep their existing result shapes. Rich reads distinguish a missing row (`null` from the single read or an absent domain key) from an existing row whose type is `null`. An empty value remains present. Every non-null token is opaque and consumer-defined; I18n gives it no special meaning. Escaping and sanitization for the output context remain consumer responsibilities.

Rules to remember: codes are case-sensitive (`'ar'` is not `'AR'`); `'ar-EG'` does not fall back to `'ar'`; a bulk read of a domain that is not readable under the policy is empty.

**Fallback is yours.** If your product wants `ar-EG` then `ar` then `en`, write that chain around `getValue` (see [example 03](../../examples/03-exact-reads-and-host-fallback.php)); treat `''` as an answer, not as a miss. Cache the result of `getDomainValues` (it reads each key separately) and invalidate after writes.

## 7. Management Reads

**Input:** `*Criteria` objects carrying a `PageRequest`; you supply the exact language codes to measure. **Public call:** `I18nManagementReadService`. **Result:** `PageResult` of DTOs. **Boundary:** I18n never reads your language table.

```php
$page = $managementRead->searchKeys(new KeyListCriteria(
    'web',
    page: new PageRequest(page: 1, perPage: 20, sortBy: 'key_part', sortDirection: 'asc'),
));

$grid = $managementRead->pageDomainTranslationGrid(new DomainTranslationGridCriteria('web', 'home', ['ar', 'fr']));
// every key x every supplied code; existing rows include type, missing rows have null ID/value/type
```

Details of identities (`getScope`, `getDomain`, `getKey` throw `*NotFoundException`), the available lists and their allowed sort keys are in [Reference 5.4](../../I18N_PACKAGE_REFERENCE.md#54-management-reads-managementservice).

## 8. Operational Facts and Derived-State Repair

**Input:** a scope code and an exact language code. **Public call:** `I18nOperationalReadService`. **Result:** counts and coverage DTOs by exact code. **Boundary:** you compose names, order and "languages with zero translations".

```php
$total = $operationalRead->totalKeyCount();
$translated = $operationalRead->translatedCountByLanguageCode();   // no entry means zero for that code
$coverage = $operationalRead->domainCoverage('web', 'ar');
```

The derived summary tables are rebuildable from keys and translations. After a manual database edit or a bad import, `I18nStatsRebuilder::fullRebuild()` repairs them in one transaction.

## 9. Transactions and Language-Code Changes

**Input:** the old and the new code. **Public call:** `rekeyLanguageCode`. **Result:** the number of re-keyed translations. **Boundary:** open your own transaction on the same connection; I18n joins it and never commits or rolls it back.

```php
$pdo->beginTransaction();
// 1. rename the code in YOUR language registry (your SQL)
$rekeyed = $writer->rekeyLanguageCode('ar', 'ar-EG');
$pdo->commit();   // or $pdo->rollBack(): both sides roll back together
```

`rekeyLanguageCode` refuses with `LanguageCodeAlreadyInUseException` when the target code already owns translations (nothing is merged). The same joining rule applies to every I18n write: wrap several I18n calls and your own writes in one transaction to make them atomic ([Reference section 7](../../I18N_PACKAGE_REFERENCE.md#7-transactions-and-concurrency)).

## 10. Optional PHP-DI Integration

**Prerequisite:** `composer require php-di/php-di` in your application (it brings `psr/container`). The Core never needs it.

```php
$builder = new DI\ContainerBuilder();
Maatify\I18n\Adapter\PhpDi\I18nBindings::register($builder);   // repositories + transaction runner
$builder->addDefinitions([
    PDO::class => $pdo,
    Maatify\SharedCommon\Contracts\ClockInterface::class => $clock,
]);
$container = $builder->build();

$writer = $container->get(Maatify\I18n\Management\Service\TranslationWriteService::class);   // autowired
```

## 11. Troubleshooting

| Symptom | Check |
|---|---|
| a read returns `null` | the exact code the rows were stored with (`'ar'` is not `'AR'`, `null` is not any code); the key exists in exactly that `scope`, `domain`, `key_part`; the `(scope, domain)` is active and assigned (only matters for bulk reads); nothing falls back, so apply your own chain |
| a key cannot be created | the scope and the domain exist and are active, and the domain is assigned to the scope |
| a code change is refused | the scope or domain code is still used by an assignment or a key |
| a changed value does not show | I18n does not cache; invalidate your own cache after writes |
| counts look wrong after a manual database edit | run `I18nStatsRebuilder::fullRebuild()` |
| `LogicException` about a locking read | a repository locking read needs an active transaction; use the services, which open one |

## 12. Where Next

[I18N_PACKAGE_REFERENCE.md](../../I18N_PACKAGE_REFERENCE.md) for every contract, [examples/](../../examples/) for runnable code, [BOOK/INDEX.md](../../BOOK/INDEX.md) for the concepts behind the design, and [README.md](../../README.md) for installation state and verification commands.
