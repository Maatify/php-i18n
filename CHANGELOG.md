# Changelog

This file records package contents by release target. The package's initial contents are allocated to `1.0.0-rc.1`.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The `1.0.0-rc.1` Release Candidate was published on 2026-10-03 through Packagist. No Stable release or Stable support line exists.

## [1.0.0-rc.1] - 2026-10-03

### Added

- Nullable exact translation `type` metadata with opaque consumer-defined tokens and rich single/domain consumer reads; nullable `type` is part of the fresh-install schema, and value-only reads remain available.
- Governed, structured translation keys (`scope.domain.key_part`) with scope, domain and assignment management.
- Exact-scope translation values keyed by a Host-owned, nullable `language_code`; runtime reads never fall back.
- Fail-soft runtime reads (`TranslationReadService`, `TranslationDomainReadService`) and fail-hard writes with a typed exception catalog.
- Management reads with paginated, criteria-driven lists and an operational read surface of exact-code counts and coverage.
- Synchronous derived summary and per-key counters, with a deterministic full rebuild.
- Explicit language-code re-key (`TranslationWriteService::rekeyLanguageCode`).
- Package-owned MySQL schema of seven `maa_i18n_*` tables.
- Transactions, ordering and pagination through `maatify/persistence`; joining an outer transaction on the same connection.
- Optional PHP-DI integration (`Adapter\PhpDi\I18nBindings`); the Core needs no container.
- Canonical Package Reference, Usage Guide, executable examples and AI-consumer navigation (`llms.txt`).
- Package verification: Unit and real-MySQL Integration suites, a Consumer Verification Harness, example smoke execution, and a package CI workflow.

[1.0.0-rc.1]: https://github.com/Maatify/php-i18n/releases/tag/v1.0.0-rc.1
