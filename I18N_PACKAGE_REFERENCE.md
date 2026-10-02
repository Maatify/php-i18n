# I18n Package Reference

The canonical reference for the stable public, runtime and behavioral contract of `maatify/php-i18n`, including the complete Public Runtime API inventory.

- **Where to start:** [README.md](README.md) (overview, installation, quick usage) and [docs/guides/USAGE_GUIDE.md](docs/guides/USAGE_GUIDE.md) (integration walkthroughs).
- **What this file does not own:** Composer facts (package identity, PHP and extension requirements, dependencies, autoload, scripts) are owned by [composer.json](composer.json). They are summarized here only where they explain runtime behavior.

## 1. Package Facts

| Fact | Value |
|---|---|
| Composer identity | `maatify/php-i18n` |
| Root namespace | `Maatify\I18n\` (PSR-4, `src/`) |
| Distribution state | **Development, unpublished.** No tag, no release and no Packagist publication exist. |
| Form today | Embedded Base Module Artifact Root (`Modules/I18n` of a Host repository). Everything in this file applies to that root. |
| PHP | `^8.4` ([composer.json](composer.json)) |
| Persistence | Package-owned MySQL schema of seven `maa_i18n_*` tables ([schema/schema.i18n.sql](schema/schema.i18n.sql)) through `PDO` |
| Shared mechanics | Transactions, display ordering and pagination come from `maatify/persistence`; the clock contract comes from `maatify/shared-common`; exceptions extend `maatify/exceptions` |
| Optional integration | PHP-DI adapter `Maatify\I18n\Adapter\PhpDi\I18nBindings`; `php-di/php-di` and `psr/container` are Composer `suggest` entries, never runtime requirements |

## 2. Scope and Non-Goals

I18n is the **translation layer**: governed, structured translation keys; exact-scope translation values; fail-soft runtime reads; management reads; operational counts and coverage facts.

It deliberately does **not**:

- own a language registry, languages table, locale selection, text direction, active flags or fallback configuration (Host concerns, see [section 4](#4-language-code-contract) and [section 11](#11-host-owned-responsibilities));
- apply any fallback, default or wildcard when a translation is missing;
- load files (PHP arrays, JSON, gettext) or render templates; values are opaque strings;
- cache anything (the Host caches and invalidates);
- delete keys (no `deleteKey` exists);
- ship a migration framework, a CLI, framework bindings or HTTP surfaces;
- manage the lifecycle of the database connection.

## 3. Source Topology and Ownership

**Source topology: Single Capability.** I18n itself is the capability; there are no artificial sub-domains. Placement under `src/` is by responsibility:

| Directory | Responsibility |
|---|---|
| `Consumer/` | Runtime exact reads (`Service/`) and their result DTO (`DTO/`) |
| `Management/` | `Command/` (write intents), `Criteria/` (list/search inputs), `Service/` (management writes, management reads, operational reads, stats rebuild, translation writes) |
| `Service/` | Package-wide governance policy and derived-state maintenance |
| `Repository/` | Persistence contracts; `Repository/Mysql/` holds their PDO/MySQL implementations |
| `Adapter/PhpDi/` | The optional PHP-DI wiring |
| `DTO/` | Result DTOs (`final readonly`, `JsonSerializable`) |
| `Enum/`, `Exception/`, `ValueObject/` | Enums, exceptions, the `LanguageCode` and `TranslationType` value objects |

### 3.1 What the Package owns and enforces

| Concept | Table | Package-enforced invariant |
|---|---|---|
| Scope | `maa_i18n_scopes` | unique `code`; appended display order; code change only while unused |
| Domain | `maa_i18n_domains` | unique `code`; appended display order; code change only while unused |
| Domain-scope assignment | `maa_i18n_domain_scopes` | unique `(scope_code, domain_code)` |
| Translation key | `maa_i18n_keys` | unique `(scope, domain, key_part)`; usable only inside an assigned, active `(scope, domain)` under `STRICT` policy |
| Translation | `maa_i18n_translations` | unique `(key_id, language_code_identity)`; at most one value per exact language scope of a key; nullable `type` is not identity |
| Domain language summary (derived) | `maa_i18n_domain_language_summary` | rebuildable from keys + translations; maintained synchronously |
| Key statistics (derived) | `maa_i18n_key_stats` | exactly one row per key; rebuildable |

### 3.2 What the Host owns

The language registry, the identity and lifecycle of languages, ID-to-code resolution, locale selection, fallback, semantic validation of language codes, caching, authorization, and the database connection. I18n stores a language code as an opaque external identity: it never interprets, validates semantically, creates or deletes a language.

### 3.3 Field classes per persisted entity

| Entity | Stable identity | Mutable business fields | Lifecycle | Ordering |
|---|---|---|---|---|
| Scope / Domain | `id`; `code` (changeable only through `changeCode` while unused) | `name`, `description` | `is_active` (no soft delete, no delete) | `sort_order`, only through `moveToPosition` |
| Assignment | `(scope_code, domain_code)` | none | assign / unassign (hard delete of the mapping row) | none |
| Key | `id`; `(scope, domain, key_part)` | `description`; `(scope, domain, key_part)` through `renameKey` | none (keys are never deleted by the Package) | none |
| Translation | `id`; `(key_id, exact language code)` | `value`, nullable exact `type`; the code only through `rekeyLanguageCode` | created by upsert, hard-deleted by `deleteTranslation` | none |

Generic updates never change stable identity: metadata updates cannot change a `code`, and a translation upsert cannot change its language code.

## 4. Language-Code Contract

The language is an exact, nullable, Host-owned `language_code` ([ADR-019](dcos/ADR-019-host-owned-exact-language-code-in-i18n.md)).

- `null` is the **exact unlocalized scope**. It is not a wildcard, a default or "all languages".
- A non-null code is the **exact scope of that code**. Reading `'ar'` never returns an `'ar-EG'`, `'en'` or `null`-scope value; reading `null` never returns a language row.
- **No fallback, default or wildcard** exists anywhere in the Package.
- **No normalization:** codes are stored as given; the column collation is binary, so `'ar'` and `'AR'` are different identities.
- **Technical validity only:** a non-null code must not be empty, must not be whitespace-only and must be at most 16 characters (`LanguageCode::MAX_LENGTH`). Whether the code names a real, active or supported language is Host policy and is never checked.
- **Invalid code:** writes and command construction throw `InvalidLanguageCodeException`; reads (`TranslationReadService`, `TranslationDomainReadService`) are fail-soft and return `null` or an empty DTO.
- **Empty value:** the empty string is a valid, authoritative translation value. It is not a missing translation.
- **Identity:** `(key_id, language_code)` is unique and NULL-safe through the generated `language_code_identity` column.

### 4.1 Nullable translation type (ADR-020)

`type` is optional metadata stored on each authoritative translation row. `NULL` means no type metadata is declared. Every valid non-null value is an exact, opaque, consumer-defined token: it must not be empty or whitespace-only and may contain at most 32 characters. The Package does not trim, lowercase or otherwise normalize it. Consumers define the meaning of their own tokens, such as `client.rich-copy`.

The type is independent of `language_code`, translation identity, fallback and missing/completeness semantics. `language_code = NULL` remains the exact unlocalized scope; `value = ''` remains a present authoritative translation; an existing row with `type = NULL` is distinct from a missing row. Type-only updates do not change translated/missing counts, summary rows or key statistics. Language-code re-keying preserves the stored type.

The Package does not define, reserve, enumerate, whitelist, interpret, normalize or assign behavior to any `type` value. Translation values remain opaque strings. I18n does not trust, render, sanitize or transform HTML, and consumers own output-context escaping and sanitization.

## 5. Public Runtime API

The Public Runtime API is exactly the set of types listed in this section. Everything else under `src/`, and everything outside `src/` (tests, examples, consumer harness, scripts, Docker files), is not part of it ([section 5.12](#512-not-public-api)).

All services, commands, criteria and DTOs are `final readonly`. Services are constructed explicitly (constructor injection of repository contracts and a `TransactionRunnerInterface`); the optional PHP-DI adapter is described in [section 5.11](#511-repository-contracts-mysql-implementations-and-php-di-adapter).

### 5.1 Consumer reads (`Consumer/`)

Fail-soft: no exception for a data miss.

| Type | Method | Result |
|---|---|---|
| `Consumer\Service\TranslationReadService` | `getValue(?string $languageCode, string $scope, string $domain, string $key): ?string` | the exact value, `''` when the stored value is empty, `null` for an unknown key, a missing exact translation or an invalid code |
| `Consumer\Service\TranslationReadService` | `getTranslation(?string $languageCode, string $scope, string $domain, string $key): ?TranslationValueDTO` | exact `value` and nullable `type`; `null` for an unknown key, missing exact translation or invalid code |
| `Consumer\Service\TranslationDomainReadService` | `getDomainValues(?string $languageCode, string $scope, string $domain): TranslationDomainValuesDTO` | `key_part => value` of the exact scope; empty when the `(scope, domain)` is not readable under the policy, has no keys, or the code is invalid |
| `Consumer\Service\TranslationDomainReadService` | `getDomainTranslations(?string $languageCode, string $scope, string $domain): TranslationDomainTranslationsDTO` | `key_part => TranslationValueDTO` for rows in the exact scope; missing rows are absent |
| `Consumer\DTO\TranslationValueDTO` | `string $value`, `?string $type` | one exact translation; content remains opaque |
| `Consumer\DTO\TranslationDomainValuesDTO` | `get(string $keyPart): ?string`, `all(): array`, `$values` | the value-only compatibility result |
| `Consumer\DTO\TranslationDomainTranslationsDTO` | `get(string $keyPart): ?TranslationValueDTO`, `all(): array`, `$translations` | the rich typed bulk result |

Constructors: `TranslationReadService(TranslationKeyRepositoryInterface, TranslationRepositoryInterface)`; `TranslationDomainReadService(TranslationKeyRepositoryInterface, TranslationRepositoryInterface, I18nGovernancePolicyService)`.

`getDomainValues` and `getDomainTranslations` read the translation of each key individually (N+1 by design); the Host is expected to cache the desired result. `getValue` remains value-only; `getTranslation` is the rich single-row read. None of these methods falls back or interprets `type`.

### 5.2 Translation writes (`Management\Service\TranslationWriteService`)

Constructor: `(TransactionRunnerInterface, TranslationKeyRepositoryInterface, TranslationRepositoryInterface, I18nGovernancePolicyService, MissingCounterService)`. Fail-hard.

| Method | Result | Throws |
|---|---|---|
| `createKey(CreateKeyCommand): int` | id of the new key | `ScopeNotAllowedException`, `DomainNotAllowedException`, `DomainScopeViolationException`, `TranslationKeyAlreadyExistsException`, `TranslationKeyCreateFailedException` |
| `renameKey(RenameKeyCommand): void` | renames and/or moves a key to another assigned `(scope, domain)`; id and translations are preserved | governance exceptions above, `TranslationKeyNotFoundException`, `TranslationKeyAlreadyExistsException` |
| `updateKeyDescription(int $keyId, string $description): void` | | `TranslationKeyNotFoundException` |
| `upsertTranslation(UpsertTranslationCommand): int` | id of the translation row; a new row refreshes derived layers, while a type-only update does not alter counts | `TranslationKeyNotFoundException`, `TranslationUpsertFailedException` |
| `deleteTranslation(?string $languageCode, int $keyId): void` | deletes the exact scope's row if present; deleting a missing row is a no-op | `InvalidLanguageCodeException`, `TranslationKeyNotFoundException` |
| `rekeyLanguageCode(string $oldCode, string $newCode): int` | number of re-keyed translations; `0` when both codes are equal | `InvalidLanguageCodeException`, `LanguageCodeAlreadyInUseException` |

`rekeyLanguageCode` moves every translation of `$oldCode` to `$newCode`, recomputes the affected derived summary rows, and refuses (nothing merged, nothing lost) when `$newCode` already owns translations. The Host performs its own language rename in the same transaction ([section 7](#7-transactions-and-concurrency)).

### 5.3 Governance management (`Management\Service`)

All constructors take a `TransactionRunnerInterface` first. Fail-hard.

| Type | Methods |
|---|---|
| `I18nScopeManagementService(tx, ScopeRepositoryInterface, DomainScopeRepositoryInterface, TranslationKeyRepositoryInterface)` | `create(CreateScopeCommand): int`, `updateMetadata(UpdateScopeMetadataCommand): void`, `setActive(int $id, bool $isActive): void`, `changeCode(int $id, string $newCode): void`, `moveToPosition(int $id, int $position): void` |
| `I18nDomainManagementService(tx, DomainRepositoryInterface, DomainScopeRepositoryInterface, TranslationKeyRepositoryInterface)` | the same five methods for domains |
| `I18nScopeDomainManagementService(tx, ScopeRepositoryInterface, DomainRepositoryInterface, DomainScopeRepositoryInterface)` | `assign(string $scopeCode, string $domainCode): void`, `unassign(string $scopeCode, string $domainCode): void` |

- `create` returns the new id and appends the display position (a position is never an input).
- `changeCode` throws `I18nInvalidArgumentException` for an empty value or one longer than 32 (scope) or 64 (domain) characters; `ScopeInUseException` / `DomainInUseException` when the current code is used by an assignment or a key; `ScopeAlreadyExistsException` / `DomainAlreadyExistsException` when the new code is taken; `ScopeNotFoundException` / `DomainNotFoundException` for an unknown id.
- `moveToPosition` is delegated to `maatify/persistence` ordering (positions are clamped); an unknown id throws `ScopeNotFoundException` / `DomainNotFoundException`.
- `assign` / `unassign` throw `ScopeNotFoundException`, `DomainNotFoundException`, and respectively `DomainScopeAlreadyAssignedException` / `DomainScopeNotAssignedException`.
- `updateMetadata` and `setActive` throw `ScopeNotFoundException` / `DomainNotFoundException` for an unknown id.

### 5.4 Management reads (`Management\Service`)

Read-only. A requested identity that does not exist is an exception; a list over an unknown parent is empty.

`I18nManagementReadService(ScopeRepositoryInterface, DomainRepositoryInterface, DomainScopeRepositoryInterface, TranslationKeyRepositoryInterface, TranslationQueryRepositoryInterface)`:

| Method | Result | Throws |
|---|---|---|
| `getScope(int $id)`, `getScopeByCode(string $code)` | `ScopeDTO` | `ScopeNotFoundException` |
| `searchScopes(ScopeListCriteria)` | `PageResult<ScopeDTO>` | |
| `getDomain(int $id)` | `DomainDTO` | `DomainNotFoundException` |
| `searchDomains(DomainListCriteria)` | `PageResult<DomainDTO>` | |
| `searchScopeDomains(ScopeDomainListCriteria)` | `PageResult<DomainAssignmentDTO>` (every domain with its `assigned` flag for one scope) | |
| `isDomainAssigned(string $scopeCode, string $domainCode)` | `bool` | |
| `listDomainOptionsForScope(string $scopeCode)` | `DomainOptionCollectionDTO` (`code`, `name` of the assigned domains, for selectors) | |
| `getKey(int $keyId)` | `TranslationKeyDTO` | `TranslationKeyNotFoundException` |
| `searchKeys(KeyListCriteria)` | `PageResult<TranslationKeyDTO>` | |
| `pageDomainKeySummaries(DomainKeySummaryCriteria)` | `PageResult<KeyTranslationSummaryDTO>` | |
| `pageDomainTranslationGrid(DomainTranslationGridCriteria)` | `PageResult<TranslationGridRowDTO>` | |
| `pageLanguageTranslationValues(LanguageTranslationValuesCriteria)` | `PageResult<LanguageTranslationValueDTO>` | |

Also public:

- `I18nScopeReadService(ScopeRepositoryInterface)`: `listScopes(): ScopeCollectionDTO`, `listActiveScopes(): ScopeCollectionDTO` (bounded governance lists in display order).
- `I18nDomainReadService(DomainRepositoryInterface, DomainScopeRepositoryInterface)`: `listDomainsForScope(string $scopeCode): DomainCollectionDTO` (fail-soft: empty for an unknown scope; active domains assigned to the scope).

`PageResult` and `PageRequest` (`Maatify\Persistence\Pdo\Pagination`) are owned by `maatify/persistence`; I18n only supplies filters, search, count alignment, row mapping and the sort keys below.

| List | Allowed `sortBy` keys | Default sort |
|---|---|---|
| scopes, domains, scope-domain assignments | `id`, `code`, `name`, `is_active`, `sort_order`, `created_at` | `sort_order` ascending, tie-break `id` |
| keys of a scope | `id`, `domain`, `key_part`, `created_at` | `id` |
| per-key summaries | `id`, `key_part`, `missing_count` | `key_part` ascending |
| translation grid | `key_part`, `language_code` | `key_part` ascending |
| per-language values | `key_id`, `scope`, `domain`, `key_part` | `key_id` ascending |

Pagination normalization (page and page-size bounds, unknown sort key fallback) follows `maatify/persistence`.

Every language-facing criteria input is an **exact code supplied by the Host**. I18n never reads a Host language table: the Host decides which codes are measured and composes names, ordering and activity itself.

### 5.5 Operational reads (`Management\Service\I18nOperationalReadService`)

Constructor: `(I18nOperationalStatsRepositoryInterface)`. Read-only; the Host composes these exact-code facts with its own language data and does not reconstruct them by SQL over Package tables.

| Method | Result |
|---|---|
| `totalKeyCount(): int` | number of translation keys |
| `translatedCountByLanguageCode(): list<I18nLanguageCodeCountDTO>` | translated keys per exact scope from the derived summary; a scope with no translation has no entry (its count is `0`); the unlocalized scope appears with `languageCode === null` |
| `keyCountByScope(): list<I18nStatCountDTO>` | keys per scope, most keys first, for scopes that have keys (`label` is the scope `name`) |
| `summaryRowCount(): int` | rows held by the derived language summary (rebuild reporting) |
| `scopeKeyCoverage(string $scopeCode): ScopeKeyCoverageDTO` | `totalKeys` of the domains assigned to the scope and the translated count per exact non-null code (`translatedByLanguage`) |
| `domainCoverage(string $scopeCode, string $languageCode): list<DomainCoverageDTO>` | per-domain `totalKeys` / `translatedCount` of one scope for one exact code, most missing first then display order; domains of the scope that have keys only. Throws `I18nInvalidArgumentException` for an empty `$languageCode` |

Classification: I18n is **In Scope** for Operational Read / Reporting because it owns persisted governance, key and translation state; the surface above is its stable read contract. Intentionally unsupported: time-window or per-user dimensions, per-language names or ordering, and "languages with zero translations" (the Host language universe is unknown to I18n).

### 5.6 Governance policy and derived-state services

| Type | Role |
|---|---|
| `Service\I18nGovernancePolicyService(ScopeRepositoryInterface, DomainRepositoryInterface, DomainScopeRepositoryInterface, I18nPolicyModeEnum $mode = STRICT)` | `assertScopeAndDomainAllowed(string $scope, string $domain): void`, `assertScopeAndDomainAllowedForUsage(...)` (takes SHARE locks; requires an active transaction), `isScopeAndDomainReadable(...): bool`. `STRICT` (default and the production setting) requires an existing, active scope, an existing, active domain and an assignment; `PERMISSIVE` lets a physically missing scope or domain pass but still enforces `is_active` and the assignment when both rows exist (migration and development only). Throws `ScopeNotAllowedException`, `DomainNotAllowedException`, `DomainScopeViolationException` |
| `Service\MissingCounterService(DomainLanguageSummaryRepositoryInterface, TranslationKeyRepositoryInterface, KeyStatsRepositoryInterface)` | Keeps the derived layers correct inside the caller's transaction: `onKeyCreated`, `onKeyDeleted`, `onTranslationCreated`, `onTranslationDeleted`, `onLanguageCodeRekeyed`, `onKeyMoved`. It is composed into `TranslationWriteService`; call it directly only when you implement your own write path |
| `Management\Service\I18nStatsRebuilder(TransactionRunnerInterface, DomainLanguageSummaryRepositoryInterface, KeyStatsRepositoryInterface)` | `fullRebuild(): void` clears and rebuilds both derived tables from keys and translations in one transaction (SQL-driven, idempotent). Operational recovery only |

### 5.7 Commands (`Management\Command`)

Self-validating `final readonly` intents. Invalid input throws `I18nInvalidArgumentException` (or `InvalidLanguageCodeException` for a language code) at construction, before any storage is touched.

| Command | Fields (limits) |
|---|---|
| `CreateScopeCommand` | `code` (1-32), `name` (1-64), `?description = null`, `isActive = true` |
| `CreateDomainCommand` | `code` (1-64), `name` (1-128), `?description = null`, `isActive = true` |
| `UpdateScopeMetadataCommand` | `id` (> 0), `?name` (1-64), `?description`; `null` leaves a field untouched, at least one must change |
| `UpdateDomainMetadataCommand` | `id` (> 0), `?name` (1-128), `?description`; same rule |
| `CreateKeyCommand` | `scope` (1-32), `domain` (1-64), `key` (1-128), `?description` (<= 255) |
| `RenameKeyCommand` | `keyId` (> 0), `scope`, `domain`, `key` (same limits as `CreateKeyCommand`) |
| `UpsertTranslationCommand` | `?languageCode` (exact; `null` = unlocalized), `keyId` (> 0), `value` (any string, including `''`), `?type` (required argument; `null` or a validated exact token of at most 32 characters) |

### 5.8 Criteria (`Management\Criteria`)

All carry a `PageRequest $page = new PageRequest()`. Empty required codes throw `I18nInvalidArgumentException`.

| Criteria | Inputs |
|---|---|
| `ScopeListCriteria`, `DomainListCriteria` | `globalSearch`, `id`, `code`, `name`, `isActive` |
| `ScopeDomainListCriteria` | `scopeCode` (required), `globalSearch`, `id`, `code`, `name`, `isActive`, `assigned` |
| `KeyListCriteria` | `scopeCode` (required), `globalSearch`, `id`, `domainLike`, `keyPartLike` |
| `DomainKeySummaryCriteria` | `scopeCode`, `domainCode`, `languageCodes` (the exact codes to measure), `globalSearch`, `keyId`, `keyPart`, `onlyMissing` |
| `DomainTranslationGridCriteria` | `scopeCode`, `domainCode`, `languageCodes`, `globalSearch`, `globalSearchLanguageCodes` (codes whose Host metadata matched a free-text search), `keyId`, `keyPartLike`, `valueLike` |
| `LanguageTranslationValuesCriteria` | `languageCode` (required, exact), `globalSearch`, `id`, `scopeLike`, `domainLike`, `keyPartLike`, `valueLike` |

### 5.9 Result DTOs (`DTO\`, `Consumer\DTO\`)

`final readonly`, `JsonSerializable`; collection DTOs also implement `IteratorAggregate` and expose `items` and `isEmpty()`.

| DTO | Fields |
|---|---|
| `ScopeDTO` | `id`, `code`, `name`, `?description`, `isActive`, `sortOrder`, `createdAt` |
| `DomainDTO` | `id`, `code`, `name`, `?description`, `isActive`, `sortOrder`, `createdAt` |
| `DomainAssignmentDTO` | `id`, `code`, `name`, `?description`, `isActive`, `sortOrder`, `assigned` |
| `DomainOptionDTO` | `code`, `name` |
| `ScopeCollectionDTO`, `DomainCollectionDTO`, `DomainOptionCollectionDTO`, `TranslationKeyCollectionDTO`, `TranslationCollectionDTO` | `items` |
| `TranslationKeyDTO` | `id`, `scope`, `domain`, `key` (the key part), `?description`, `createdAt` |
| `TranslationDTO` | `id`, `keyId`, `?languageCode`, `value`, `?type`, `createdAt`, `?updatedAt` |
| `TranslationUpsertResultDTO` | `id`, `created` |
| `KeyTranslationSummaryDTO` | `id`, `keyPart`, `?description`, `totalLanguages` (number of distinct supplied codes), `missingCount` |
| `TranslationGridRowDTO` | `?translationId`, `keyId`, `keyPart`, `?description`, `languageCode`, `?value`, `?type` (`null` value and ID = missing translation) |
| `LanguageTranslationValueDTO` | `keyId`, `scope`, `domain`, `keyPart`, `?translationId`, `?value`, `?type`, `createdAt`, `?updatedAt` |
| `Consumer\DTO\TranslationValueDTO` | `value`, `?type` |
| `Consumer\DTO\TranslationDomainTranslationsDTO` | `translations: array<string, TranslationValueDTO>`; `get()`, `all()` |
| `I18nLanguageCodeCountDTO` | `?languageCode`, `count` |
| `I18nStatCountDTO` | `label`, `count` |
| `ScopeKeyCoverageDTO` | `totalKeys`, `translatedByLanguage` (`list<I18nLanguageCodeCountDTO>`) |
| `DomainCoverageDTO` | `domainId`, `domainCode`, `domainName`, `totalKeys`, `translatedCount` |
| `Consumer\DTO\TranslationDomainValuesDTO` | `values`; `get()`, `all()` |

### 5.10 Enums and value object

- `Enum\I18nPolicyModeEnum`: `STRICT`, `PERMISSIVE`.
- `Enum\LockModeEnum`: `NONE`, `SHARE`, `UPDATE`; the row-lock mode of a repository locking read. Anything other than `NONE` requires an active transaction (otherwise a `LogicException` is thrown).
- `Enum\I18nErrorCodeEnum`: the string error codes of the Package exceptions ([section 6.2](#62-exception-catalog)).
- `ValueObject\LanguageCode`: `fromNullable(?string): self` (throws `InvalidLanguageCodeException`), `value(): ?string`, `isUnlocalized(): bool`, `identity(): string`, `MAX_LENGTH = 16`.
- `ValueObject\TranslationType`: `fromNullable(?string): self` (throws `I18nInvalidArgumentException`), `value(): ?string`, `MAX_LENGTH = 32`; validates only the technical token contract and preserves the exact supplied token.

### 5.11 Repository contracts, MySQL implementations and PHP-DI adapter

**Contracts (`Repository\*Interface`)** are public so a Host can compose, decorate or substitute persistence: `ScopeRepositoryInterface`, `DomainRepositoryInterface`, `DomainScopeRepositoryInterface`, `TranslationKeyRepositoryInterface`, `TranslationRepositoryInterface`, `TranslationQueryRepositoryInterface`, `DomainLanguageSummaryRepositoryInterface`, `KeyStatsRepositoryInterface`, `I18nOperationalStatsRepositoryInterface`. Their failure contract: a genuine "no row" is `null`, empty or `false`; a storage failure never masquerades as one (see [section 6.3](#63-storage-failure-contract)).

**MySQL implementations (`Repository\Mysql\Mysql*Repository`)** are the supported implementations of those contracts: `MysqlScopeRepository`, `MysqlDomainRepository`, `MysqlDomainScopeRepository`, `MysqlTranslationKeyRepository`, `MysqlTranslationRepository`, `MysqlTranslationQueryRepository`, `MysqlDomainLanguageSummaryRepository`, `MysqlKeyStatsRepository`, `MysqlI18nOperationalStatsRepository`. Constructors: every repository takes `PDO $pdo`; `MysqlTranslationRepository` additionally takes `Maatify\SharedCommon\Contracts\ClockInterface $clock`; `MysqlTranslationKeyRepository` and `MysqlTranslationQueryRepository` accept an optional `PdoPaginator` as a second argument.

**Optional PHP-DI adapter (`Adapter\PhpDi\I18nBindings`)**: `I18nBindings::register(DI\ContainerBuilder $builder): void` registers `TransactionRunnerInterface` and the nine repository contracts above. The container must provide `PDO` and `ClockInterface`; services are autowired. The adapter requires `php-di/php-di` and `psr/container` in the Host; the Core never loads either (proved by the Consumer Verification Harness, which runs without them).

### 5.12 Not public API

Not supported as consumer contract, even though visible: `Repository\Mysql\PdoGateway`, `Repository\Mysql\Row`, `Repository\Mysql\MysqlGovernanceTableSupport`, `Exception\I18nErrorPolicy` (infrastructure of the exception hierarchy), the SQL text and query shapes of the repositories, `tests/`, `examples/`, `consumer-verification/`, `scripts/`, `docker/`, and any `@internal` or undocumented member. Visible in the repository does not mean supported.

## 6. Behavioral Contracts

### 6.1 Read versus write semantics

| | Writes | Reads |
|---|---|---|
| Strategy | **Fail-hard** | **Fail-soft** (runtime reads) |
| Data problems | typed `I18nExceptionInterface` exceptions | `null`, `''` as a real value, or an empty DTO |
| Governance | enforced by `I18nGovernancePolicyService` | a non-readable `(scope, domain)` yields an empty bulk result |
| Management reads | n/a | a requested identity that does not exist throws a `*NotFoundException`; a list over an unknown parent is empty |

### 6.2 Exception catalog

Every exception defined by the Package implements `Maatify\I18n\Exception\I18nExceptionInterface` (extends `Throwable`), extends a `maatify/exceptions` base through an abstract I18n category class, and carries a stable `I18nErrorCodeEnum` code. External throwables, notably `PDOException`, propagate unchanged and are not forced to implement the marker.

| Exception | Category | Error code | Raised by |
|---|---|---|---|
| `ScopeNotAllowedException` | business rule | `SCOPE_NOT_ALLOWED` | governance policy |
| `DomainNotAllowedException` | business rule | `DOMAIN_NOT_ALLOWED` | governance policy |
| `DomainScopeViolationException` | business rule | `DOMAIN_SCOPE_VIOLATION` | governance policy |
| `InvalidLanguageCodeException` | business rule | `LANGUAGE_CODE_INVALID` | `LanguageCode`, `UpsertTranslationCommand`, `deleteTranslation`, `rekeyLanguageCode` |
| `I18nInvalidArgumentException` | validation | n/a | commands, criteria, `changeCode`, `domainCoverage` |
| `TranslationKeyAlreadyExistsException` | conflict | `TRANSLATION_KEY_ALREADY_EXISTS` | `createKey`, `renameKey` |
| `LanguageCodeAlreadyInUseException` | conflict | `LANGUAGE_CODE_ALREADY_IN_USE` | `rekeyLanguageCode` |
| `ScopeAlreadyExistsException` / `DomainAlreadyExistsException` | conflict | `SCOPE_ALREADY_EXISTS` / `DOMAIN_ALREADY_EXISTS` | `create`, `changeCode` |
| `ScopeInUseException` / `DomainInUseException` | conflict | `SCOPE_IN_USE` / `DOMAIN_IN_USE` | `changeCode` |
| `DomainScopeAlreadyAssignedException` | conflict | `DOMAIN_SCOPE_ALREADY_ASSIGNED` | `assign` |
| `TranslationKeyNotFoundException` | not found | `TRANSLATION_KEY_NOT_FOUND` | key and translation writes, `getKey` |
| `ScopeNotFoundException` / `DomainNotFoundException` | not found | `SCOPE_NOT_FOUND` / `DOMAIN_NOT_FOUND` | scope and domain management and management reads, `assign`, `unassign` |
| `DomainScopeNotAssignedException` | not found | `DOMAIN_SCOPE_NOT_ASSIGNED` | `unassign` |
| `TranslationKeyCreateFailedException` | system | `TRANSLATION_KEY_CREATE_FAILED` | `createKey` (repository returned no identity) |
| `TranslationUpsertFailedException` | system | `TRANSLATION_UPSERT_FAILED` | `upsertTranslation` (repository returned no identity) |
| `I18nStorageException` | system | `STORAGE_FAILURE` | repositories, see [6.3](#63-storage-failure-contract) |
| `TranslationUpdateFailedException`, `TranslationWriteFailedException` | system | `TRANSLATION_UPDATE_FAILED`, `TRANSLATION_WRITE_FAILED` | declared; the current services do not raise them |

Category base classes, catchable to handle a whole category: `I18nBusinessRuleException`, `I18nConflictException`, `I18nNotFoundException`, `I18nSystemException` (all abstract and implementing the marker).

Duplicate races end in the Package exception, never a raw PDO error: the database `UNIQUE` constraint is the final authority and a MySQL duplicate-key violation (driver code 1062 only) is classified into the matching conflict exception; any other failure propagates unchanged.

### 6.3 Storage failure contract

`PDO::ERRMODE_EXCEPTION` is the supported mode. A thrown `PDOException` propagates unchanged. A non-throwing failure state (`prepare`, `execute` or `fetch` failing under another error mode) becomes `I18nStorageException`. Neither can masquerade as a miss, an empty list or a governance denial.

## 7. Transactions and Concurrency

- **Transaction owner:** the I18n management and write services run each operation through `maatify/persistence`'s `TransactionRunnerInterface` (`PdoTransactionRunner` is the supported implementation).
- **No outer transaction:** the runner owns `BEGIN` / `COMMIT` / `ROLLBACK`.
- **Outer transaction supported:** when the connection already has an active transaction, I18n participates and never commits or rolls back the caller's transaction; the original `Throwable` is preserved. Atomic composition with Host data requires the **same `PDO` connection**.
- **Derived layers** are written in the same transaction as the authoritative change.
- **Locks:** a mutation that creates usage of a scope or domain (create key, move key, assign) takes SHARE locks on the governance rows in the fixed order scope, domain, mapping. A code change takes the UPDATE lock on its row and checks usage with locking reads before changing the code. "Unused, then usage created, then code changed" therefore cannot orphan a key. Repository locking reads require an active transaction.
- **Ordering:** creates take a caller-owned ordering lock before allocating the next position. An empty ordering has no row to lock, so concurrent first creates may make InnoDB pick a deadlock victim (SQLSTATE 40001), which propagates unchanged; the Host may retry.
- **`moveToPosition`** is delegated to `maatify/persistence` ordering and manages its own consistency.
- **Reads** do not open transactions and take no locks.

## 8. Persistence and Schema

- **Authority:** [schema/schema.i18n.sql](schema/schema.i18n.sql) is the fresh-install schema authority: seven tables, an `id` primary key on each, column comments and documented policies. A Host copy of it is a projection, never a second design source.
- **Fresh installation:** apply the file to a fresh database, for example with `PDO::exec` over its contents. **It begins with `DROP TABLE IF EXISTS` for all seven tables**, so applying it to a database that already holds I18n data destroys that data.
- **S1 upgrade:** [schema/migrations/2026-10-02-translation-type.sql](schema/migrations/2026-10-02-translation-type.sql) is an additive migration from the exact pre-S1 schema identified in its header. It adds nullable `type`; existing rows receive `NULL`. The Package ships this schema-evolution asset but no migration framework; the Host controls when it is applied.
- **Translation type column:** `VARCHAR(32) NULL COLLATE utf8mb4_bin`, protected by a check constraint, with no index. It is not part of translation identity, uniqueness or generated language identity.
- **Engine:** MySQL with InnoDB, `utf8mb4`, generated stored columns, `CHECK` constraints and `COLLATE utf8mb4_bin` on language codes (MySQL 8.4 is the version exercised by the Package verification).
- **Tables:** `maa_i18n_scopes`, `maa_i18n_domains`, `maa_i18n_domain_scopes`, `maa_i18n_keys`, `maa_i18n_translations`, `maa_i18n_domain_language_summary` (derived), `maa_i18n_key_stats` (derived).
- **Host independence:** no foreign key to, and no join with, any Host table. Scope and domain codes are referenced by code, not by foreign key ([ADR-018](dcos/ADR-018-string-codes-instead-of-fk-in-i18n.md)); the Package serializes code change versus new usage with row locks instead.
- **No soft delete.** Translations hard-delete; per-key stats cascade from their key.
- **Derived tables** are non-authoritative and rebuildable; `I18nStatsRebuilder::fullRebuild()` is the canonical recovery from drift.
- **Time:** the Package never changes the global timezone; the translation repository takes its clock from the shared `ClockInterface`; the Host owns timezone policy.

## 9. Consumer Workflow

```text
Host input -> Public API -> Domain service -> Integration boundary -> Observable result
```

| Step | Example |
|---|---|
| Host input | scope `web`, domain `home`, key `title`, language code `ar`, value `...` |
| Public API | `CreateScopeCommand`, `CreateDomainCommand`, `assign`, `CreateKeyCommand`, `UpsertTranslationCommand` through the management and write services |
| Domain service | governance policy, then key/translation write, then derived-layer maintenance, in one transaction |
| Integration boundary | your `PDO` (MySQL) through the Package repositories |
| Observable result | `getValue('ar', 'web', 'home', 'title')` returns the value; `null` for an exact miss; management and operational reads show keys, grids and counts |

The runnable form of this workflow is [examples/01-core-wiring-and-first-translation.php](examples/01-core-wiring-and-first-translation.php); the external-consumer proof is [consumer-verification/](consumer-verification/).

## 10. Verification Evidence

| Evidence | Where |
|---|---|
| Unit and Real MySQL Integration suites | `tests/`, run by `composer test` |
| External-consumer proof (separate Composer root, production autoload, real MySQL, two clean runs) | `consumer-verification/`, run by `composer verify:consumer` |
| Smoke-executed examples | `examples/`, run by `composer check:examples` |
| Package CI | `.github/workflows/ci-i18n-package.yml` of the Host repository, targeting this Artifact Root |

The local command behind every CI gate is listed in [README.md](README.md#development-and-testing).

## 11. Host-Owned Responsibilities

The Host owns, and I18n never does:

- the language registry and everything about a language except its exact code;
- resolving any Host identity (for example a language ID in a route) to an exact code **before** calling I18n; IDs never cross the I18n boundary;
- locale selection and any fallback chain;
- semantic validation of language codes (I18n stores any technically valid code, including an unknown one; a code with no Host language is an orphan by Host policy, not an I18n error);
- renaming a language code atomically: call `TranslationWriteService::rekeyLanguageCode()` in the same transaction, on the same connection, as the Host's own rename; renaming the code in the Host alone leaves I18n rows under the old code;
- composing language names, icons and activity with the exact-code counts of the operational and management reads;
- caching and cache invalidation after writes;
- authorization, and the database connection and its error mode.

## 12. Related Documents

- [ADR-018](dcos/ADR-018-string-codes-instead-of-fk-in-i18n.md): string codes instead of foreign keys for scope and domain.
- [ADR-019](dcos/ADR-019-host-owned-exact-language-code-in-i18n.md): Host-owned exact language code.
- [ARCHITECTURE.md](ARCHITECTURE.md): component boundaries.
- [BOOK.md](BOOK.md) and [BOOK/INDEX.md](BOOK/INDEX.md): conceptual and deep documentation (it never overrides this reference).
