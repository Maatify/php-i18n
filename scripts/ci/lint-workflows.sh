#!/usr/bin/env bash
#
# Workflow lint gate: actionlint over standalone repository workflows.
# actionlint must be pre-provisioned and available on PATH. Non-mutating.
#
# Entry point: `composer check:workflows`.
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "${ROOT}"

ACTIONLINT="$(command -v actionlint)" || { echo "[i18n-workflows] ERROR: actionlint is required but was not found on PATH" >&2; exit 1; }

shopt -s nullglob
files=(.github/workflows/*.yml .github/workflows/*.yaml)
[ "${#files[@]}" -gt 0 ] || { echo "[i18n-workflows] ERROR: no workflow files found" >&2; exit 1; }

"${ACTIONLINT}" -color "${files[@]}"
echo "[i18n-workflows] ${#files[@]} workflow files lint clean"
