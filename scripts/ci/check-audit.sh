#!/usr/bin/env bash
#
# Composer dependency-policy audit gate. It requires Composer >= 2.10 (the
# dependency-policy model: config.policy advisories / malware / abandoned) as a
# VERIFICATION-TIME capability only; it is never a consumer requirement. It
# refuses to run when the environment weakens the policy.
#
# Entry point: `composer check:audit`.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

for variable in COMPOSER_POLICY COMPOSER_NO_BLOCKING COMPOSER_POLICY_ADVISORIES_BLOCK \
    COMPOSER_POLICY_MALWARE_BLOCK COMPOSER_NO_AUDIT COMPOSER_AUDIT_ABANDONED; do
    if [ -n "${!variable:-}" ]; then
        echo "[i18n-audit] ERROR: ${variable} is set; the required audit policy must not be weakened" >&2
        exit 1
    fi
done

version="$(composer --version | sed -E 's/^Composer version ([0-9]+\.[0-9]+).*/\1/')"
major="${version%%.*}"
minor="${version##*.}"
if [ "${major}" -lt 2 ] || { [ "${major}" -eq 2 ] && [ "${minor}" -lt 10 ]; }; then
    echo "[i18n-audit] ERROR: Composer ${version} lacks the 2.10 dependency-policy model" >&2
    exit 1
fi

composer audit --no-interaction --abandoned=fail
