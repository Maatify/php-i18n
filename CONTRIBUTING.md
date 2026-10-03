# Contributing to maatify/php-i18n

## Package and boundaries

This repository maintains the reusable Composer package maatify/php-i18n. It owns the I18n runtime, its schema, and its persistence behavior. The Host owns authentication, authorization, language selection and fallback policy, and application-specific integration.

Contributions may include focused bug fixes, documentation improvements, examples, and compatible enhancements. Keep changes within the package boundary and describe the user-visible behavior they affect.

## Local verification

Use PHP 8.4 or newer and Composer. Docker Compose v2 is required for real-MySQL verification. Install actionlint on PATH to run the workflow lint gate. The Composer audit gate requires Composer 2.10 or newer.

From the repository root:

    composer install
    composer verify

The canonical verification scripts are maintained in composer.json. The full verification command includes the Unit and real-MySQL Integration suites, examples, consumer verification, static and style checks, documentation checks, workflow lint, and Composer audit. Focused test entry points are composer test:unit and composer test:integration. Integration uses the repository-owned disposable MySQL setup; do not use production or Host application data.

## Pull Requests

Keep each change bounded. Update related tests and documentation with the behavior change, avoid unrelated edits, and never include credentials, secrets, tracked composer.lock, or tracked vendor/ files. A GitHub Issue is not required for every change.

## Architecture discussions

Discuss a material design change before implementation when it affects the public contract, persistence or schema, transaction or concurrency semantics, exception behavior, or the package/Host boundary. Follow the repository's decision governance for changes that require a recorded decision.

## Security reports

Use the private reporting route in [SECURITY.md](SECURITY.md) for vulnerability reports.

## composer.lock policy

This is a reusable library. Do not commit composer.lock. It may be generated temporarily for local or CI dependency resolution and must be removed before delivery.
