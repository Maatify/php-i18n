#!/usr/bin/env bash
#
# Canonical owner of the I18n real-MySQL lifecycle. It is the single
# orchestration used by the Integration suite, the standalone examples and the
# Consumer Verification Harness; all of them consume the one Compose definition
# in docker/mysql-integration/compose.yaml.
#
#   validate prerequisites -> compose up (health-gated) -> discover endpoint
#   -> export I18N_IT_* -> run the given command -> residue check -> teardown (-v)
#
# Usage: scripts/ci/with-mysql.sh <command> [args...]
#   The command runs with the Package root as working directory and sees:
#   I18N_IT_DB_HOST, I18N_IT_DB_PORT, I18N_IT_DB_USER, I18N_IT_DB_PASS (run-scoped
#   and temporary) and I18N_IT_REQUIRED=1. Every run gets its own Compose project
#   and tmpfs state, so independent runs always start from a fresh database.
#
# Exit status contract (proved by tests/Unit/IntegrationLifecycleStatusTest.php):
#   command 0 / cleanup 0   -> 0
#   command N / cleanup 0   -> N
#   command 0 / cleanup M   -> M (non-zero; cleanup failure is never hidden)
#   command N / cleanup M   -> N (original failure preserved)
#   SIGINT -> 130, SIGTERM -> 143 (the command is stopped, teardown still runs)
#
# I18N_IT_DOCKER_BIN exists only so the regression proof can substitute a
# deterministic fake; it defaults to `docker`.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
COMPOSE_FILE="${ROOT}/docker/mysql-integration/compose.yaml"
DOCKER_BIN="${I18N_IT_DOCKER_BIN:-docker}"

fail() { echo "[i18n-it] ERROR: $*" >&2; exit 1; }

[ "$#" -ge 1 ] || fail "usage: with-mysql.sh <command> [args...]"

# --- prerequisites -----------------------------------------------------------
command -v "${DOCKER_BIN}" >/dev/null 2>&1 || fail "docker is required for the real-MySQL lifecycle"
"${DOCKER_BIN}" compose version >/dev/null 2>&1 || fail "docker compose (v2) is required for the real-MySQL lifecycle"
"${DOCKER_BIN}" info >/dev/null 2>&1 || fail "the Docker daemon is not reachable"
command -v "$1" >/dev/null 2>&1 || [ -x "$1" ] || fail "$1 is not executable; run composer install first"
command -v openssl >/dev/null 2>&1 || fail "openssl is required to generate the run-scoped password"

# --- run-scoped identity and credentials (never persisted) --------------------
RUN_ID="$(openssl rand -hex 6)"
export COMPOSE_PROJECT_NAME="i18n-it-${RUN_ID}"
export I18N_IT_DB_PASS
I18N_IT_DB_PASS="$(openssl rand -hex 16)"

compose() { "${DOCKER_BIN}" compose -f "${COMPOSE_FILE}" "$@"; }

VERIFY_PID=""

cleanup() {
    local original_status=$?
    local cleanup_status=0
    trap - EXIT INT TERM

    if [ "${original_status}" -ne 0 ]; then
        compose logs --no-color --tail 50 mysql >&2 2>/dev/null || true
    fi

    compose down -v --remove-orphans >/dev/null 2>&1 || cleanup_status=$?
    if [ "${cleanup_status}" -ne 0 ]; then
        echo "[i18n-it] ERROR: cleanup failed for ${COMPOSE_PROJECT_NAME} (status ${cleanup_status})" >&2
    fi

    # The original failure always wins; otherwise cleanup decides.
    if [ "${original_status}" -ne 0 ]; then
        exit "${original_status}"
    fi
    exit "${cleanup_status}"
}

on_signal() {
    local status=$1
    if [ -n "${VERIFY_PID}" ]; then
        kill -TERM "${VERIFY_PID}" 2>/dev/null || true
        wait "${VERIFY_PID}" 2>/dev/null || true
    fi
    exit "${status}"
}

trap cleanup EXIT
trap 'on_signal 130' INT
trap 'on_signal 143' TERM

# --- start + readiness (service healthcheck, no sleeps) -----------------------
echo "[i18n-it] starting ${COMPOSE_PROJECT_NAME}"
compose up -d --wait --wait-timeout 180 mysql

# --- effective endpoint -------------------------------------------------------
ENDPOINT="$(compose port mysql 3306 | head -n 1)"
[ -n "${ENDPOINT}" ] || fail "could not discover the published MySQL endpoint"
export I18N_IT_DB_HOST="127.0.0.1"
export I18N_IT_DB_PORT="${ENDPOINT##*:}"
export I18N_IT_DB_USER="root"
export I18N_IT_REQUIRED="1"

# --- run the command ------------------------------------------------------------
# Backgrounded + waited so INT/TERM interrupt immediately instead of after the run.
cd "${ROOT}"
"$@" &
VERIFY_PID=$!
verify_status=0
wait "${VERIFY_PID}" || verify_status=$?
VERIFY_PID=""
[ "${verify_status}" -eq 0 ] || exit "${verify_status}"

# --- residue check: the command must drop every schema it created ---------------
RESIDUE="$(compose exec -T -e MYSQL_PWD="${I18N_IT_DB_PASS}" mysql \
    mysql -h127.0.0.1 -uroot -N -B -e "SHOW DATABASES LIKE 'i18n\\_it\\_%'")"
[ -z "${RESIDUE}" ] || fail "the command left schemas behind: ${RESIDUE}"
echo "[i18n-it] command passed; no residue"
