<div align="center">

# Maatify I18n

![Maatify.dev](https://www.maatify.dev/assets/img/img/maatify_logo_white.svg)

[![Status](https://img.shields.io/badge/Status-Release%20Candidate-blue)](CHANGELOG.md)
[![Version](https://img.shields.io/badge/Version-1.0.0--rc.1-blue)](https://packagist.org/packages/maatify/php-i18n)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-8892BF)](composer.json)
[![License](https://img.shields.io/badge/License-Proprietary-green)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%20Max-4E8CAE)](phpstan.neon)

[![Packagist](https://img.shields.io/badge/Packagist-Distribution-blue)](https://packagist.org/packages/maatify/php-i18n)
[![Monthly Downloads](https://img.shields.io/packagist/dm/maatify/php-i18n)](https://packagist.org/packages/maatify/php-i18n)
[![Total Downloads](https://img.shields.io/packagist/dt/maatify/php-i18n)](https://packagist.org/packages/maatify/php-i18n)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-blueviolet)](https://github.com/Maatify)
[![Install](https://img.shields.io/badge/Install-1.0.0--rc.1%40RC-blue)](#installation)

[![Usage Guide](https://img.shields.io/badge/Docs-Usage%20Guide-informational)](docs/guides/USAGE_GUIDE.md)
[![Examples](https://img.shields.io/badge/Docs-Examples-informational)](examples/)
[![Package Reference](https://img.shields.io/badge/Docs-Package%20Reference-informational)](I18N_PACKAGE_REFERENCE.md)
[![Book](https://img.shields.io/badge/Docs-Book-informational)](BOOK/INDEX.md)
[![Changelog](https://img.shields.io/badge/Docs-Changelog-informational)](CHANGELOG.md)
[![Security](https://img.shields.io/badge/Docs-Security-informational)](SECURITY.md)
[![Contributing](https://img.shields.io/badge/Docs-Contributing-informational)](CONTRIBUTING.md)

`maatify/php-i18n` is a governed, database-driven translation layer for PHP: structured keys, exact-scope translation values in MySQL, fail-soft runtime reads, management reads for admin screens, and operational coverage facts. Language identity stays with the Host: I18n stores an exact, nullable language code and never applies a fallback.

Published Release Candidate: `1.0.0-rc.1`, distributed through [Packagist](https://packagist.org/packages/maatify/php-i18n). No Published Stable release or Stable support line exists. See [Installation](#installation) for the exact consumer command.

Release Target: `1.0.0-rc.1` · Publication State: Published pre-release · Lifecycle Status: Release Candidate.

</div>

---

## Key Features

- **Governed structured keys:** a key is `scope.domain.key_part` and can only exist inside an active, assigned `(scope, domain)`.
- **Exact language scopes:** reads and writes address one exact `language_code` (or `NULL`, the unlocalized scope). No fallback, default or wildcard inside I18n; fallback is Host policy.
- **Fail-soft reads, fail-hard writes:** a runtime miss returns `null` or an empty DTO; a write violating governance throws a typed exception.
- **Synchronous derived state:** per-domain, per-language summary and per-key counters are updated in the same transaction and are rebuildable.
- **Package-owned persistence:** seven `maa_i18n_*` tables; no foreign key to, and no join with, any Host table.
- **Shared transaction semantics:** transactions, ordering and pagination come from `maatify/persistence`; an outer transaction on the same connection is joined, never committed or rolled back by I18n.
- **Container-free Core:** plain constructor wiring; an optional PHP-DI adapter is available but never required.

## Requirements

[composer.json](composer.json) is the source of truth for requirements and dependencies; this table reflects it.

| Requirement | Constraint |
|---|---|
| PHP | `^8.4` |
| Extensions | `ext-mbstring`, `ext-pdo`, `ext-pdo_mysql` |
| `maatify/exceptions` | `^1.1` |
| `maatify/persistence` | `^1.4` |
| `maatify/shared-common` | `^1.0` |
| Database | MySQL (InnoDB, `utf8mb4`); MySQL 8.4 is the version exercised by the package verification |
| Optional | `php-di/php-di` and `psr/container` only for the optional PHP-DI adapter (`suggest`) |

## Installation

Install the exact published Release Candidate from Packagist:

```bash
composer require maatify/php-i18n:1.0.0-rc.1@RC
```

Then create the database objects once, on a fresh database, from [schema/schema.i18n.sql](schema/schema.i18n.sql). The file begins with `DROP TABLE IF EXISTS` for the seven tables, so never apply it over existing I18n data.

## Quick Usage

```php
$scopes->create(new CreateScopeCommand('web', 'Website'));
$domains->create(new CreateDomainCommand('home', 'Home page'));
$assignments->assign('web', 'home');

$keyId = $writer->createKey(new CreateKeyCommand('web', 'home', 'title'));
$writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'en',
    keyId: $keyId,
    value: 'Welcome',
    type: null,
));
$richKeyId = $writer->createKey(new CreateKeyCommand('web', 'home', 'rich-copy'));
$writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'en',
    keyId: $richKeyId,
    value: '<p>Formatted copy</p>',
    type: 'client.rich-copy', // A token defined by this consumer; I18n assigns it no behavior.
));

$reader->getValue('en', 'web', 'home', 'title');   // 'Welcome'
$reader->getValue('ar', 'web', 'home', 'title');   // null: exact miss, no fallback
$reader->getTranslation('en', 'web', 'home', 'rich-copy'); // value + nullable type
```

Complete, runnable wiring: [examples/01-core-wiring-and-first-translation.php](examples/01-core-wiring-and-first-translation.php). Walkthroughs: [docs/guides/USAGE_GUIDE.md](docs/guides/USAGE_GUIDE.md).

## Public Runtime API

An overview only. The complete inventory (signatures, DTO fields, exceptions, sort keys) is [I18N_PACKAGE_REFERENCE.md](I18N_PACKAGE_REFERENCE.md#5-public-runtime-api).

| Area | Types |
|---|---|
| Runtime reads (fail-soft) | `TranslationReadService` (`getValue()`, `getTranslation()`), `TranslationDomainReadService` (`getDomainValues()`, `getDomainTranslations()`), value-only and typed consumer DTOs |
| Translation writes | `TranslationWriteService` (keys, translations, language-code re-key) |
| Governance management | `I18nScopeManagementService`, `I18nDomainManagementService`, `I18nScopeDomainManagementService` |
| Management reads | `I18nManagementReadService`, `I18nScopeReadService`, `I18nDomainReadService` with `*Criteria` inputs and paginated results |
| Operational reads | `I18nOperationalReadService` (exact-code counts and coverage) |
| Policy and maintenance | `I18nGovernancePolicyService`, `MissingCounterService`, `I18nStatsRebuilder` |
| Inputs and results | `*Command` intents, `*Criteria` queries, `*DTO` results, `LanguageCode`, `I18nPolicyModeEnum` |
| Persistence | repository contracts (`Repository\*Interface`) and their MySQL implementations |
| Optional integration | `Adapter\PhpDi\I18nBindings` |

## Critical Runtime Behavior

- **Exact scope:** `getValue('ar', ...)` reads `ar` only. `null` reads the unlocalized scope only. A miss never retries elsewhere.
- **Empty is a value:** the empty string is an authoritative translation, not a miss.
- **Type is opaque metadata:** any valid non-null string is an exact consumer-defined token. The Package defines no type vocabulary and does not assign behavior, render or sanitize values.
- **Codes are not normalized:** `'ar'` and `'AR'` are different; a code must be 1-16 characters and not whitespace-only. Whether a code is a real language is Host policy.
- **Derived state:** summary tables are maintained inside the write transaction; `I18nStatsRebuilder::fullRebuild()` repairs drift.
- **No caching, no key deletion, no fallback.**

## Exception and Error Propagation

Every exception defined by the package implements `Maatify\I18n\Exception\I18nExceptionInterface`. Writes and identity lookups throw typed exceptions; runtime reads do not throw for data problems. `PDOException` and other external throwables propagate unchanged; a non-throwing PDO failure state becomes `I18nStorageException`. Catalog: [Reference 6.2](I18N_PACKAGE_REFERENCE.md#62-exception-catalog).

## Security and Trust Boundaries

- Query values are bound parameters; table and column identifiers and sort columns come from constants and a fixed sort whitelist, never from input. Command and criteria inputs are validated for emptiness and length before storage is touched.
- Authorization, authentication, rate limiting and who may call management services are Host concerns.
- Translation values are opaque strings: escaping them for HTML, JSON or any other sink is the consumer's job.
- The schema file is destructive on an existing database (see Installation).
- See [SECURITY.md](SECURITY.md) for the vulnerability reporting and support policy.

## Examples

Six maintained examples cover every material capability and run against a disposable MySQL in CI. See the capability map in the [Usage Guide](docs/guides/USAGE_GUIDE.md#2-capability-map) and the [examples/](examples/) directory.

## Schema

[schema/schema.i18n.sql](schema/schema.i18n.sql) is the fresh-install schema authority: `maa_i18n_scopes`, `maa_i18n_domains`, `maa_i18n_domain_scopes`, `maa_i18n_keys`, `maa_i18n_translations`, `maa_i18n_domain_language_summary` (derived), `maa_i18n_key_stats` (derived). Ownership and semantics: [Reference section 8](I18N_PACKAGE_REFERENCE.md#8-persistence-and-schema).

## Documentation

| Document | Role |
|---|---|
| [I18N_PACKAGE_REFERENCE.md](I18N_PACKAGE_REFERENCE.md) | the canonical public, runtime and behavioral contract |
| [docs/guides/USAGE_GUIDE.md](docs/guides/USAGE_GUIDE.md) | integration walkthroughs |
| [examples/](examples/) | maintained, executable examples |
| [CHANGELOG.md](CHANGELOG.md) | published `1.0.0-rc.1` Release Candidate and its actual release date |
| [ARCHITECTURE.md](ARCHITECTURE.md) | component boundaries |
| [BOOK/INDEX.md](BOOK/INDEX.md) | conceptual, deep documentation; never overrides the Reference |
| [llms.txt](llms.txt) | navigation for AI consumers |
| [SECURITY.md](SECURITY.md) | vulnerability reporting and support policy |
| [CONTRIBUTING.md](CONTRIBUTING.md) | contribution boundaries and local verification |
| [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) | community participation expectations |

## Quality Status

| Gate | State |
|---|---|
| PHPStan level `max` (src, tests, examples, consumer harness) | enforced, zero errors, no baseline, no suppressions |
| Unit suite (no Docker) and real-MySQL Integration suite | enforced on PHP 8.4 and 8.5 |
| Dependency compatibility | latest-compatible and lowest-supported resolutions both verified |
| Consumer Verification Harness | repository-path dependency in an external Composer root, production autoload, real MySQL, two clean runs; published-RC Harness verification is the next separate step |
| Examples | every example smoke-executed |
| Composer audit and platform requirements | enforced |
| Release | Published Release Candidate `1.0.0-rc.1`; no Published Stable release or Stable support line. See [CHANGELOG.md](CHANGELOG.md) |

## Development and Testing

Every CI gate invokes a local command from the standalone repository root, so each can be reproduced locally with the same verification contract. Prerequisites: PHP 8.4+, Composer, `actionlint` available on PATH for `composer check:workflows`, and, for the real-MySQL gates, Docker with Compose v2. `composer check:audit` needs Composer 2.10 or newer (a verification-time capability, not a consumer requirement). The disposable MySQL is defined once in [docker/mysql-integration/compose.yaml](docker/mysql-integration/compose.yaml) and driven by [scripts/ci/with-mysql.sh](scripts/ci/with-mysql.sh); Integration, examples and the consumer harness all reuse it, with run-scoped temporary credentials and teardown.

```bash
composer install
composer verify          # canonical Package gates on the currently resolved dependencies
```

| Gate | Local command | CI job (`I18n Package CI`) |
|---|---|---|
| Composer validation | `composer check:composer` | Quality |
| Platform requirements | `composer check:platform` | Quality, Dependencies |
| Strict production autoload | `composer check:autoload` | Quality |
| PHP syntax | `composer check:syntax` | Quality |
| PHPStan max | `composer analyse` | Quality, Dependencies |
| PHP-FIG PER Coding Style 3.1 | `composer check:style` | Quality, Dependencies |
| Whitespace | `composer check:whitespace` | Quality |
| Documentation consistency | `composer check:docs` | Quality |
| Unit | `composer test:unit` | Unit (PHP 8.4, 8.5), Dependencies |
| Real-MySQL Integration | `composer test:integration` | Integration (PHP 8.4, 8.5), Dependencies |
| Examples smoke | `composer check:examples` | Examples (PHP 8.4, 8.5) |
| Consumer Verification | `composer verify:consumer` | Consumer Verification (PHP 8.4, 8.5) |
| Composer audit | `composer check:audit` | Audit |
| Workflow lint | `composer check:workflows` (actionlint over standalone repository workflows; actionlint must already be available on PATH) | Workflow Lint |

`composer verify` runs the canonical Package verification gate set on the dependency versions currently installed or resolved, including Style and Workflow Lint. CI runs latest-compatible and lowest-supported dependency resolutions as separate compatibility modes; neither mode is part of `composer verify`, and `composer verify` does not run `composer update`.

The final aggregate job of the workflow is `I18n Package CI Gate`.

## License

Proprietary. See [LICENSE](LICENSE).

## Author

Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>
Backend Lead & Technical Architect<br>
[https://www.maatify.dev](https://www.maatify.dev)

---

<div align="center">

[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)

</div>
