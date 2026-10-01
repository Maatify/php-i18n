#!/usr/bin/env bash
#
# Runs the Consumer Verification Harness (consumer-verification/) end to end.
#
# Each run starts from a clean consumer state, so it never relies on a previous
# vendor/, composer.lock, database or generated file:
#   remove consumer vendor + lock
#   -> composer update --no-dev   (the Package is resolved from `..` as a Composer
#                                  path dependency, symlink: false; Package
#                                  require-dev is never available)
#   -> composer check-platform-reqs
#   -> scripts/ci/with-mysql.sh php consumer-verification/bin/verify.php
#      (the Package-owned MySQL lifecycle: fresh database per run, teardown, residue check)
#
# The deterministic result lines of every run must be identical.
#
# Entry point: `composer verify:consumer`.
# I18N_CV_RUNS     number of clean runs (default 2, minimum 2)
# I18N_CV_KEEP=1   keep the last consumer vendor/composer.lock for inspection
# I18N_PHP_BIN     PHP binary used by the harness (default: php)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CONSUMER="${ROOT}/consumer-verification"
RUNS="${I18N_CV_RUNS:-2}"
PHP_BIN="${I18N_PHP_BIN:-php}"

[ "${RUNS}" -ge 2 ] 2>/dev/null || { echo "[i18n-cv] ERROR: at least two clean runs are required" >&2; exit 1; }

WORK="$(mktemp -d)"

clean_consumer() {
    rm -rf "${CONSUMER}/vendor" "${CONSUMER}/composer.lock"
}

finish() {
    local status=$?
    trap - EXIT
    rm -rf "${WORK}"
    if [ "${I18N_CV_KEEP:-0}" != "1" ]; then
        clean_consumer
    fi
    exit "${status}"
}
trap finish EXIT

for run in $(seq 1 "${RUNS}"); do
    echo "[i18n-cv] run ${run}/${RUNS}: clean consumer state"
    clean_consumer

    ( cd "${CONSUMER}" && composer update --no-dev --no-interaction --no-progress --prefer-dist )
    ( cd "${CONSUMER}" && composer check-platform-reqs --no-dev )

    bash "${ROOT}/scripts/ci/with-mysql.sh" "${PHP_BIN}" "${CONSUMER}/bin/verify.php" | tee "${WORK}/raw-${run}.log"

    grep -E '^(OK [0-9]+ |CONSUMER VERIFICATION PASSED)' "${WORK}/raw-${run}.log" >"${WORK}/result-${run}.txt" \
        || { echo "[i18n-cv] ERROR: run ${run} produced no result lines" >&2; exit 1; }
    grep -q '^CONSUMER VERIFICATION PASSED' "${WORK}/result-${run}.txt" \
        || { echo "[i18n-cv] ERROR: run ${run} did not finish" >&2; exit 1; }
done

for run in $(seq 2 "${RUNS}"); do
    if ! diff -u "${WORK}/result-1.txt" "${WORK}/result-${run}.txt"; then
        echo "[i18n-cv] ERROR: run ${run} differs from run 1 (non-deterministic result)" >&2
        exit 1
    fi
done

echo "[i18n-cv] ${RUNS} clean runs passed with identical results: $(tail -n 1 "${WORK}/result-1.txt")"
