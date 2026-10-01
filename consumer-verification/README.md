# Consumer Verification Harness

External-consumer proof for `maatify/php-i18n`. It is verification tooling, not part of the package and not part of its Public Runtime API.

## What it proves

A consumer that is **not** the package repository can install the package and use it:

- **Separate Composer root:** this directory has its own `composer.json`. The package is resolved from the Artifact Root (`..`) as a Composer `path` dependency with `symlink: false`, and installed in production mode (`--no-dev`), so the package's `require-dev` is never available.
- **Production autoload only:** `bin/verify.php` loads `vendor/autoload.php` of this root. It never includes files from `src/`, and it does not use the package's `autoload-dev`, tests, bootstrap or fixtures, nor any Host namespace or autoload.
- **No hidden requirement:** PHP-DI and `psr/container` are absent, and the Core is built by plain construction.
- **Real persistence:** the canonical schema shipped with the installed package is applied to a disposable MySQL database, and a realistic workflow runs through the public API: governance, key creation, exact translation writes and reads, exact missing behavior, the exception contract, transaction participation, language-code re-key, derived-state rebuild, and teardown.
- **Repeatable:** every run starts from a clean consumer state (no `vendor/`, no `composer.lock`, a fresh database), and the deterministic result lines of two runs must be identical.

## Run it

From the package root (`Modules/I18n`), with Docker Compose v2 available:

```bash
composer verify:consumer
```

The MySQL lifecycle is the package-owned one (`scripts/ci/with-mysql.sh`, `docker/mysql-integration/compose.yaml`), shared with the Integration suite and the examples. `I18N_CV_RUNS` sets the number of clean runs (minimum 2) and `I18N_CV_KEEP=1` keeps the last consumer `vendor/` for inspection.

The consumer `vendor/` and `composer.lock` are never tracked.
