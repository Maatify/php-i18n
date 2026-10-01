#!/usr/bin/env bash
#
# PHP syntax gate: `php -l` on every Package-owned PHP file (runtime, tests,
# examples, consumer harness). Any failure fails the gate; nothing is skipped.
#
# Entry point: `composer check:syntax`.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${I18N_PHP_BIN:-php}"
cd "${ROOT}"

count=0
while IFS= read -r -d '' file; do
    "${PHP_BIN}" -l "${file}" >/dev/null || { echo "[i18n-syntax] ERROR: ${file}" >&2; exit 1; }
    count=$((count + 1))
done < <(find src tests examples consumer-verification/bin scripts -type f -name '*.php' -print0 | sort -z)

[ "${count}" -gt 0 ] || { echo "[i18n-syntax] ERROR: no PHP files found" >&2; exit 1; }
echo "[i18n-syntax] ${count} PHP files: no syntax errors"
