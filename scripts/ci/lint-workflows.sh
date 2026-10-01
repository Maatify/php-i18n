#!/usr/bin/env bash
#
# Workflow lint gate: actionlint over EVERY file under the Host repository's
# .github/workflows/ (the package CI lives there). Non-mutating.
#
# The actionlint binary is version-pinned and verified against a pinned
# SHA-256 before it is executed. Set I18N_ACTIONLINT_USE_PATH=1 to use an
# `actionlint` already on PATH instead (local convenience only; CI always uses
# the pinned download).
#
# Entry point: `composer check:workflows`.
set -euo pipefail

VERSION="1.7.12"
SHA256_LINUX_AMD64="8aca8db96f1b94770f1b0d72b6dddcb1ebb8123cb3712530b08cc387b349a3d8"
SHA256_DARWIN_ARM64="aba9ced2dee8d27fecca3dc7feb1a7f9a52caefa1eb46f3271ea66b6e0e6953f"

ROOT="$(git rev-parse --show-toplevel)"
cd "${ROOT}"

if [ "${I18N_ACTIONLINT_USE_PATH:-0}" = "1" ]; then
    ACTIONLINT="$(command -v actionlint)" || { echo "[i18n-workflows] ERROR: actionlint not on PATH" >&2; exit 1; }
else
    case "$(uname -s)-$(uname -m)" in
        Linux-x86_64)  ASSET="linux_amd64";  EXPECTED="${SHA256_LINUX_AMD64}" ;;
        Darwin-arm64)  ASSET="darwin_arm64"; EXPECTED="${SHA256_DARWIN_ARM64}" ;;
        *) echo "[i18n-workflows] ERROR: no pinned actionlint for $(uname -s)-$(uname -m); use I18N_ACTIONLINT_USE_PATH=1" >&2; exit 1 ;;
    esac

    WORK="$(mktemp -d)"
    trap 'rm -rf "${WORK}"' EXIT
    ARCHIVE="actionlint_${VERSION}_${ASSET}.tar.gz"
    curl -fsSL -o "${WORK}/${ARCHIVE}" "https://github.com/rhysd/actionlint/releases/download/v${VERSION}/${ARCHIVE}"
    ACTUAL="$(shasum -a 256 "${WORK}/${ARCHIVE}" | cut -d' ' -f1)"
    [ "${ACTUAL}" = "${EXPECTED}" ] || { echo "[i18n-workflows] ERROR: checksum mismatch for ${ARCHIVE}" >&2; exit 1; }
    tar -xzf "${WORK}/${ARCHIVE}" -C "${WORK}" actionlint
    ACTIONLINT="${WORK}/actionlint"
fi

shopt -s nullglob
files=(.github/workflows/*.yml .github/workflows/*.yaml)
[ "${#files[@]}" -gt 0 ] || { echo "[i18n-workflows] ERROR: no workflow files found" >&2; exit 1; }

"${ACTIONLINT}" -color "${files[@]}"
echo "[i18n-workflows] ${#files[@]} workflow files lint clean"
