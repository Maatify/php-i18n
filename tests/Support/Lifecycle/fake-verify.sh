#!/usr/bin/env bash
# Deterministic stand-in for the PHPUnit Integration run.
# I18N_IT_FAKE_VERIFY_STATUS is the exit status; with I18N_IT_FAKE_VERIFY_HANG=1
# it signals readiness through the marker directory and then blocks until killed.
set -u

if [ "${I18N_IT_FAKE_VERIFY_HANG:-0}" = "1" ]; then
    : >"${I18N_IT_FAKE_MARKER_DIR}/verify-started"
    sleep 60 &
    sleeper=$!
    trap 'kill "${sleeper}" 2>/dev/null; exit 143' TERM
    wait "${sleeper}"
fi

exit "${I18N_IT_FAKE_VERIFY_STATUS:-0}"
