# Architecture: Maatify I18n

This document describes the architectural boundaries and components of the I18n module (`maatify/php-i18n`). The canonical public, runtime and behavioral contract, including the complete Public Runtime API inventory, is the [Package Reference](I18N_PACKAGE_REFERENCE.md); this file explains how the parts fit together.

## 1. Database Schema

The module owns the tables that manage the translation layer.
It has **no dependency on any Host language table**: language identity is an
exact, nullable, Host-owned `language_code` (see [ADR-019](dcos/ADR-019-host-owned-exact-language-code-in-i18n.md)).

### `maa_i18n_scopes`
*   **Purpose:** Top-level boundaries (e.g., `admin`, `client`).
*   **Columns:** `id`, `code`, `name`, `description`, `sort_order`, `is_active`, `created_at`.

### `maa_i18n_domains`
*   **Purpose:** Functional areas (e.g., `auth`, `products`).
*   **Columns:** `id`, `code`, `name`, `description`, `sort_order`, `is_active`, `created_at`.

### `maa_i18n_domain_scopes`
*   **Purpose:** Governance Mapping (Scope <-> Domain).
*   **Columns:** `id`, `scope_code` (VARCHAR), `domain_code` (VARCHAR), `created_at`.
*   **Constraint:** Unique `(scope_code, domain_code)`.

### `maa_i18n_keys`
*   **Purpose:** Registry of valid translation keys.
*   **Columns:** `id`, `scope` (VARCHAR), `domain` (VARCHAR), `key_part`, `description`, `created_at`.
*   **Constraint:** Unique `(scope, domain, key_part)`.

### `maa_i18n_translations`
*   **Purpose:** Text values, one row per exact `(key_id, language_code)`.
*   **Columns:** `id`, `key_id` (FK -> maa_i18n_keys), `language_code` (`VARCHAR(16) NULL`, binary collation), `language_code_identity` (generated `COALESCE(language_code, '')`), `value`, `created_at`, `updated_at`.
*   **Constraint:** Unique `(key_id, language_code_identity)` (NULL-safe); CHECK: `language_code` is NULL or non-empty/non-whitespace and <= 16 chars.
*   **Semantics:** `NULL` = exact unlocalized scope; a code = exact scope of that code. No fallback, default or wildcard. No FK/JOIN to any Host language table.

### `maa_i18n_domain_language_summary`
*   **Purpose:** Synchronous exact-scope aggregation (Derived, non-authoritative).
*   **Columns:** `id`, `scope`, `domain`, `language_code` (nullable), `language_code_identity` (generated), `total_keys`, `translated_count`, `missing_count`.
*   **Constraint:** Unique `(scope, domain, language_code_identity)`.
*   **Semantics:** a row exists only when the exact scope has at least one translation. I18n never knows the Host language list; a Host language with no row is `translated = 0` (the Host composes `missing = total_keys`).

### `maa_i18n_key_stats`
*   **Purpose:** Per-key translated-row counter (Derived). Language-agnostic.
*   **Columns:** `id` (PK), `key_id` (unique FK -> maa_i18n_keys: exactly one stats row per key), `translated_count`, `updated_at`.

### Schema authority
`schema/schema.i18n.sql` is the only schema authority: seven tables, an `id` primary key on each, meaningful column comments, documented policies. A Host deployment copy is a projection, never a second design source.

## 1.1 Source topology

**Single Capability** - I18n is the capability; there are no artificial sub-domains. Placement is by responsibility: `Consumer/`, `Management/` (`Command/`, `Criteria/`, `Service/`), `Service/`, `Repository/` (+ `Repository/Mysql/`), `Adapter/PhpDi/`, `DTO/`, `Enum/`, `Exception/`, `ValueObject/`.

## 2. Service Layer

### `I18nGovernancePolicyService`
*   **Role:** The Gatekeeper.
*   **Responsibility:** Enforces that Scopes and Domains exist and are mapped before keys can be created. A mutation that creates usage takes the governance rows' SHARE locks first (`assertScopeAndDomainAllowedForUsage`).

### Management services (`Management/Service`)
*   `I18nScopeManagementService`, `I18nDomainManagementService`: create (appended to the display order), metadata, active state, **code change**, ordering. A code change locks the governance row `FOR UPDATE`, checks usage with locking reads, then changes the code in one transaction; every usage-creating mutation takes the compatible SHARE locks (scope -> domain -> mapping), so "unused -> usage created -> code changed" can never orphan a reference. Ordering is `maatify/persistence` `ScopedOrderingManager`.
*   `I18nScopeDomainManagementService`: assign / unassign with deterministic lock order; the UNIQUE `(scope_code, domain_code)` is the duplicate authority.
*   `TranslationWriteService`: key create / rename / description, translation upsert / delete, language-code re-key. Duplicate-key races end in `TranslationKeyAlreadyExistsException` (MySQL driver code 1062 only; any other failure propagates unchanged).
*   `I18nScopeReadService`, `I18nDomainReadService`: fail-soft bounded governance lists (all / active scopes; domains assigned to a scope).
*   `I18nManagementReadService`: details and **paginated** lists (`PageResult` of `maatify/persistence`; I18n owns only filters / search / mapping) of scopes, domains, assignments, keys, per-key translation summaries, translation grids, per-language values. Every language-facing input is an exact code supplied by the Host.
*   `I18nOperationalReadService`: package-owned counts and coverage facts (key totals, exact-code translated counts, per-scope / per-domain coverage, derived-row count) so the Host never reconstructs I18n semantics by SQL.
*   Inputs with several fields are `Command`s (self-validating, `final readonly`); list / search inputs are `Criteria`.

### Transactions, concurrency, storage failures
*   **Transaction owner:** the I18n service, through `maatify/persistence` `TransactionRunnerInterface` (`PdoTransactionRunner`): no outer TX -> the runner owns begin / commit / rollback; active outer TX -> participate only, never commit or roll back the caller's, original `Throwable` preserved. Atomic composition requires the same PDO connection.
*   **Ordering concurrency:** creates take the caller-owned ordering lock (same index order as persistence' move lock). An EMPTY ordering has no row to lock: concurrent first creates may make InnoDB choose a deadlock victim (SQLSTATE 40001, propagated unchanged).
*   **Failure contract:** a genuine "no row" is `null` / empty / `false`. A thrown `PDOException` propagates unchanged; a non-throwing PDO failure state (`prepare` / `execute` / `fetch` failing under a non-exception error mode) becomes `I18nStorageException`. Neither can masquerade as a miss, an empty list or a policy denial. `PDO::ERRMODE_EXCEPTION` is the supported mode.
*   Every package exception implements `Maatify\I18n\Exception\I18nExceptionInterface`; external `PDOException`s are not forced to.

### `TranslationWriteService`
*   **Role:** The Writer.
*   **Responsibility:**
    *   Creates/Renames Keys.
    *   Upserts / Deletes Translations of an exact `?string $languageCode`.
    *   `rekeyLanguageCode(old, new)`: explicit, transactional language-code identity migration (called by the Host when it renames a language code).
*   **Behavior:** Fail-Hard (Throws Exceptions). Validates only the technical code contract (non-empty, <= 16), never the language semantically.

### `TranslationReadService`
*   **Role:** The Reader (Single Value).
*   **Responsibility:** Fetches one key for an exact language scope. **No fallback**: `null` code reads the unlocalized scope only, a code reads that code only.
*   **Behavior:** Fail-Soft (Returns null).

### `TranslationDomainReadService`
*   **Role:** The Reader (Bulk).
*   **Responsibility:** Fetches entire domains for UI loading, exact scope only (no fallback).
*   **Behavior:** Fail-Soft (Returns empty DTO).

### `MissingCounterService`
*   **Role:** The Aggregator.
*   **Responsibility:** Synchronously keeps `maa_i18n_domain_language_summary` (exact-scope rows) and `maa_i18n_key_stats` correct during writes.
*   **Behavior:** Strong Consistency.

### `I18nStatsRebuilder`
*   **Role:** The Repairman (single rebuild owner).
*   **Responsibility:** In one transaction, clears and rebuilds `maa_i18n_domain_language_summary` and `maa_i18n_key_stats` from `maa_i18n_keys` + `maa_i18n_translations` only (SQL-driven, deterministic, no Host language table).
*   **Usage:** Operational recovery only; the Host decides when and how to trigger it.

## 3. Consistency Model

The module utilizes a **Strong Consistency** model.
*   Writes to `maa_i18n_keys` or `maa_i18n_translations` trigger synchronous updates to `maa_i18n_domain_language_summary`.
*   No background queues or eventual consistency.

## 4. Dependencies

*   **Internal:** none on language data. No language registry repository, service, DTO or exception; no FK/JOIN to any language table.
*   **External:** PDO (Database), `maatify/shared-common` (Clock), `maatify/exceptions`, `maatify/persistence` `^1.4` (transactions, ordering, pagination).
*   **Optional:** `php-di/php-di` + `psr/container` only for `Adapter/PhpDi/I18nBindings` (Composer `suggest`; the Core never loads them).

## 5. Host Responsibilities (ADR-019)

The Host owns: the language registry, ID -> code resolution, locale selection, fallback, semantic validation of codes, and composition of language names/icons/active flags with I18n exact-scope counts. When a Host renames a language code it must call `TranslationWriteService::rekeyLanguageCode()` in the same transaction as its own rename; I18n never edits Host language data.
