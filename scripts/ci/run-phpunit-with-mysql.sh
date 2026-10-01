#!/usr/bin/env bash
#
# Integration entry: runs the PHPUnit Integration suite inside the canonical
# real-MySQL lifecycle owned by scripts/ci/with-mysql.sh.
#
# Entry point: `composer test:integration` (and `composer test`).
# Extra arguments are forwarded to PHPUnit.
#
# I18N_IT_VERIFY_BIN exists only so the regression proof
# (tests/Unit/IntegrationLifecycleStatusTest.php) can substitute a deterministic
# fake for PHPUnit; it defaults to vendor/bin/phpunit.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
VERIFY_BIN="${I18N_IT_VERIFY_BIN:-${ROOT}/vendor/bin/phpunit}"

if [ ! -x "${VERIFY_BIN}" ]; then
    echo "[i18n-it] ERROR: ${VERIFY_BIN} missing; run composer install first" >&2
    exit 1
fi

exec bash "${ROOT}/scripts/ci/with-mysql.sh" \
    "${VERIFY_BIN}" --configuration phpunit.xml.dist --testsuite Integration "$@"
