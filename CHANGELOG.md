# Changelog

All notable changes to `maatify/php-i18n` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The package is in development and has not been published: there is no tag, release or Packagist publication yet, so no released version is listed below.

## [Unreleased]

### Added

- Nullable exact translation `type` metadata, the `wysiwyg` token, rich single/domain consumer reads, and an additive pre-S1 MySQL schema migration; value-only reads remain available.
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
