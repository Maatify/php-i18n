#!/usr/bin/env bash
# Deterministic docker shim for the lifecycle exit-status regression proof.
# I18N_IT_FAKE_UP_STATUS / I18N_IT_FAKE_DOWN_STATUS drive `compose up` / `compose down`.
# I18N_IT_FAKE_MARKER_DIR receives a `down` file when teardown ran.
set -u

if [ "${1:-}" = "info" ]; then exit 0; fi
[ "${1:-}" = "compose" ] || exit 0
shift

if [ "${1:-}" = "version" ]; then exit 0; fi
if [ "${1:-}" = "-f" ]; then shift 2; fi

case "${1:-}" in
    up)   exit "${I18N_IT_FAKE_UP_STATUS:-0}" ;;
    port) echo "127.0.0.1:3306"; exit 0 ;;
    down)
        [ -z "${I18N_IT_FAKE_MARKER_DIR:-}" ] || : >"${I18N_IT_FAKE_MARKER_DIR}/down"
        exit "${I18N_IT_FAKE_DOWN_STATUS:-0}"
        ;;
    *)    exit 0 ;;
esac
