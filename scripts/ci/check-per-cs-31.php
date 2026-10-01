<?php

declare(strict_types=1);

require_once __DIR__ . '/PerCs31SourceChecker.php';

$root = dirname(__DIR__, 2);
$paths = ['src', 'tests', 'examples', 'scripts', 'consumer-verification/bin'];
$files = [];
foreach ($paths as $path) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $path, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}
$files[] = $root . '/.php-cs-fixer.dist.php';
sort($files, SORT_STRING);

$errors = [];
foreach ($files as $file) {
    $source = file_get_contents($file);
    if ($source === false) {
        $errors[] = $file . ': unable to read source';
        continue;
    }
    array_push($errors, ...PerCs31SourceChecker::violations($source, $file));
}

if ($errors !== []) {
    fwrite(STDERR, "[i18n-per-cs-3.1] violations:\n  " . implode("\n  ", $errors) . "\n");
    exit(1);
}

echo sprintf("[i18n-per-cs-3.1] PER Coding Style 3.1 supplemental checks passed across %d PHP files\n", count($files));
