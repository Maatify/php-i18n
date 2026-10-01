#!/usr/bin/env bash
#
# Documentation consistency gate (links, anchors, identity, release and
# Host-name claims, llms.txt shape). See check-docs.php.
#
# Entry point: `composer check:docs`.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
exec "${I18N_PHP_BIN:-php}" "${ROOT}/scripts/ci/check-docs.php"
