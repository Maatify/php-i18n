#!/usr/bin/env bash
#
# Smoke-executes every standalone example in examples/ against a real MySQL,
# using the Package-owned lifecycle (scripts/ci/with-mysql.sh) and the Package
# production autoload. A non-zero exit of any example fails the gate.
#
# Entry point: `composer check:examples`.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${I18N_PHP_BIN:-php}"

if [ "${1:-}" != "--inner" ]; then
    exec bash "${ROOT}/scripts/ci/with-mysql.sh" bash "${BASH_SOURCE[0]}" --inner
fi

cd "${ROOT}"
shopt -s nullglob
examples=(examples/[0-9][0-9]-*.php)
[ "${#examples[@]}" -gt 0 ] || { echo "[i18n-examples] ERROR: no examples found" >&2; exit 1; }

for example in "${examples[@]}"; do
    echo "[i18n-examples] ${example}"
    "${PHP_BIN}" "${example}"
done
echo "[i18n-examples] ${#examples[@]} examples passed"
