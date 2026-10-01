#!/usr/bin/env bash
#
# Whitespace gate over every Package file that is tracked or untracked-but-not-
# ignored (so it also works before the first commit): no trailing whitespace,
# no CR line endings, a final newline in every non-empty text file. It does not
# depend on a diff base, unlike `git diff --check`.
#
# Entry point: `composer check:whitespace`.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

status=0

if git grep --untracked -nIE '[[:blank:]]+$' -- . ':!vendor' ':!consumer-verification/vendor'; then
    echo "[i18n-whitespace] ERROR: trailing whitespace (listed above)" >&2
    status=1
fi

if git grep --untracked -nIl $'\r' -- . ':!vendor' ':!consumer-verification/vendor'; then
    echo "[i18n-whitespace] ERROR: CR line endings (files listed above)" >&2
    status=1
fi

while IFS= read -r -d '' file; do
    if [ -s "${file}" ] && grep -Iq . "${file}" && [ -n "$(tail -c1 "${file}")" ]; then
        echo "[i18n-whitespace] ERROR: no final newline: ${file}" >&2
        status=1
    fi
done < <(git ls-files -co --exclude-standard -z -- . ':!vendor' ':!consumer-verification/vendor')

[ "${status}" -eq 0 ] || exit 1
echo "[i18n-whitespace] clean"
