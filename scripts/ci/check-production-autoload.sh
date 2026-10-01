#!/usr/bin/env bash
#
# Strict, optimized PRODUCTION autoload gate:
#   composer dump-autoload --no-dev --optimize --strict-psr
# fails on any PSR-4 mismatch under the production mapping, then every class,
# interface, trait and enum under src/ must load from that production autoload
# alone (no autoload-dev, no tests). The development autoload is restored on exit.
#
# Entry point: `composer check:autoload`.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${I18N_PHP_BIN:-php}"
cd "${ROOT}"

restore() {
    composer dump-autoload --optimize --no-interaction >/dev/null
}
trap restore EXIT

composer dump-autoload --no-dev --optimize --strict-psr --no-interaction

"${PHP_BIN}" -r '
$root = getcwd();
require $root . "/vendor/autoload.php";

if (class_exists("Maatify\\I18n\\Tests\\Support\\MysqlTestEnvironment")) {
    fwrite(STDERR, "[i18n-autoload] ERROR: test namespace is autoloadable in the production autoload\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . "/src", FilesystemIterator::SKIP_DOTS));
$loaded = 0;
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== "php") {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($root . "/src/"), -4);
    $fqcn = "Maatify\\I18n\\" . str_replace("/", "\\", $relative);
    $exists = class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn) || enum_exists($fqcn);
    if (!$exists) {
        fwrite(STDERR, "[i18n-autoload] ERROR: " . $fqcn . " does not load from the production autoload\n");
        exit(1);
    }
    $loaded++;
}
echo "[i18n-autoload] " . $loaded . " Package types load from the strict optimized production autoload\n";
'
