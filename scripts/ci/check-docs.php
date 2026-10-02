<?php

/**
 * Documentation consistency gate for the Package consumer documents.
 *
 * 1. Every relative Markdown link (and #anchor into a Markdown file) resolves.
 * 2. No stale identity, release, Packagist, PHP-version, Host-name,
 *    embedded-artifact or removed-guide claim appears in current consumer docs.
 * 3. `llms.txt` stays a navigation layer: one H1, a blockquote, link sections
 *    only, no copied contract.
 *
 * Entry point: `composer check:docs`.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

/** Consumer-facing documents (ADRs under dcos/ are historical decision records). */
$documents = array_merge(
    ['README.md', 'I18N_PACKAGE_REFERENCE.md', 'ARCHITECTURE.md', 'CHANGELOG.md', 'llms.txt'],
    glob('docs/guides/*.md') ?: [],
    glob('BOOK/*.md') ?: [],
    glob('consumer-verification/*.md') ?: [],
);

/** Current consumer docs; historical ADRs under dcos/ are deliberately excluded. */
$currentStateDocuments = array_merge(
    ['README.md', 'I18N_PACKAGE_REFERENCE.md', 'ARCHITECTURE.md', 'llms.txt'],
    glob('docs/guides/*.md') ?: [],
    glob('BOOK/*.md') ?: [],
);

$errors = [];

$slug = static function (string $heading): string {
    $heading = strtolower(trim(str_replace('`', '', $heading)));
    $heading = preg_replace('/[^\p{L}\p{N} _-]/u', '', $heading) ?? '';

    return str_replace(' ', '-', $heading);
};

$anchors = static function (string $file) use ($slug): array {
    $found = [];
    $inFence = false;
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (str_starts_with($line, '```')) {
            $inFence = !$inFence;
            continue;
        }
        if (!$inFence && preg_match('/^#{1,6}\s+(.+)$/', $line, $m) === 1) {
            $found[] = $slug($m[1]);
        }
    }

    return $found;
};

foreach ($documents as $document) {
    if (!is_file($document)) {
        $errors[] = $document . ': required document is missing';
        continue;
    }

    $text = (string) file_get_contents($document);

    // 1. Links (skip fenced code blocks).
    $withoutFences = preg_replace('/^```.*?^```/ms', '', $text) ?? '';
    preg_match_all('/\]\(([^)\s]+)\)/', $withoutFences, $links);
    foreach ($links[1] as $target) {
        if (preg_match('#^(https?:|mailto:)#', $target) === 1) {
            continue;
        }
        [$path, $fragment] = array_pad(explode('#', $target, 2), 2, '');
        $resolved = $path === '' ? $document : dirname($document) . '/' . $path;
        $resolved = (string) preg_replace('#/\./#', '/', $resolved);
        if (!file_exists($resolved)) {
            $errors[] = sprintf('%s: link target does not exist: %s', $document, $target);
            continue;
        }
        if ($fragment !== '' && is_file($resolved) && str_ends_with($resolved, '.md')
            && !in_array($fragment, $anchors($resolved), true)) {
            $errors[] = sprintf('%s: anchor does not exist: %s', $document, $target);
        }
    }

    // 2. Claims that must not appear.
    $forbidden = [
        '/maatify\/i18n\b/i' => 'stale identity (the Composer identity is maatify/php-i18n)',
        '/img\.shields\.io\/packagist/i' => 'Packagist badge for an unpublished package',
        '/packagist\.org\/packages/i' => 'Packagist package link for an unpublished package',
        '/Status-(Stable|RC|Release)/i' => 'publication status claim',
        '/(Total|Monthly) Downloads/i' => 'download metrics for an unpublished package',
        '/composer require maatify\//i' => 'install command for an unpublished package',
        '/(PHP|php)[ -]?(%3E%3D|>=|\^)\s?8\.[0-3]\b/' => 'PHP constraint that contradicts composer.json (^8.4)',
        '/\bAthar\b|LanguageCore|AdminKernel/' => 'Host name inside Package documentation',
        '/HOW_TO_USE/' => 'reference to the removed duplicate guide',
        '/llms-full\.txt/' => 'llms-full.txt is not part of this package',
    ];
    foreach ($forbidden as $pattern => $reason) {
        if (preg_match($pattern, $text, $m) === 1) {
            $errors[] = sprintf('%s: "%s": %s', $document, $m[0], $reason);
        }
    }

    if (in_array($document, $currentStateDocuments, true)) {
        $staleArtifactForm = [
            '/Embedded Base Module/i' => 'stale embedded-artifact form claim in a standalone package',
            '/Modules\/I18n\b/i' => 'stale embedded module path in a standalone package',
        ];
        foreach ($staleArtifactForm as $pattern => $reason) {
            if (preg_match($pattern, $text, $m) === 1) {
                $errors[] = sprintf('%s: "%s": %s', $document, $m[0], $reason);
            }
        }
    }
}

// Public Runtime API inventory closure: every type under src/ is either named in
// the Package Reference or explicitly listed there as not public API.
$reference = (string) file_get_contents('I18N_PACKAGE_REFERENCE.md');
$notPublic = ['PdoGateway', 'Row', 'MysqlGovernanceTableSupport', 'I18nErrorPolicy'];
$inventoried = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('src', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
        continue;
    }
    $short = $file->getBasename('.php');
    if (preg_match('/\b' . preg_quote($short, '/') . '\b/', $reference) !== 1) {
        $errors[] = 'I18N_PACKAGE_REFERENCE.md: src type is not inventoried: ' . $short;
        continue;
    }
    $inventoried++;
}
foreach ($notPublic as $short) {
    $section = strstr($reference, '### 5.12 Not public API') ?: '';
    if (!str_contains($section, $short)) {
        $errors[] = 'I18N_PACKAGE_REFERENCE.md: section 5.12 does not list ' . $short;
    }
}

// PHP source documentation must use the current Composer and repository identity.
$staleSourceIdentityOccurrences = 0;
$sourceIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('src', FilesystemIterator::SKIP_DOTS),
);
foreach ($sourceIterator as $sourceFile) {
    if (!$sourceFile instanceof SplFileInfo || $sourceFile->getExtension() !== 'php') {
        continue;
    }
    $source = (string) file_get_contents($sourceFile->getPathname());
    $staleSourceIdentityOccurrences += preg_match_all(
        '/maatify\/i18n\b|maatify:i18n\b|https?:\/\/github\.com\/Maatify\/i18n\b/i',
        $source,
    );
}
if ($staleSourceIdentityOccurrences !== 0) {
    $errors[] = sprintf('src: %d stale package or repository identity occurrence(s)', $staleSourceIdentityOccurrences);
}

// 3. llms.txt shape.
$llms = (string) file_get_contents('llms.txt');
if (preg_match_all('/^# /m', $llms) !== 1) {
    $errors[] = 'llms.txt: exactly one H1 is required';
}
if (preg_match('/^> .+/m', $llms) !== 1) {
    $errors[] = 'llms.txt: a blockquote summary is required';
}
if (strlen($llms) > 3500) {
    $errors[] = 'llms.txt: must stay a concise navigation layer';
}
foreach (['README.md', 'I18N_PACKAGE_REFERENCE.md', 'docs/guides/USAGE_GUIDE.md', 'examples/'] as $required) {
    if (!str_contains($llms, '](' . $required . ')')) {
        $errors[] = 'llms.txt: primary navigation link is missing: ' . $required;
    }
}
if (file_exists('llms-full.txt')) {
    $errors[] = 'llms-full.txt must not exist';
}
if (file_exists('HOW_TO_USE.md')) {
    $errors[] = 'HOW_TO_USE.md must not exist (docs/guides/USAGE_GUIDE.md is the only Usage Guide)';
}

// Reusable-library Composer source must not track a dependency lock file.
$trackedComposerLock = [];
exec('git ls-files --error-unmatch -- composer.lock 2>/dev/null', $trackedComposerLock, $composerLockStatus);
if ($composerLockStatus === 0) {
    $errors[] = 'composer.lock must not be tracked in reusable-library source (COMPOSER_PACKAGE_STANDARD.md §25)';
}

if ($errors !== []) {
    fwrite(STDERR, "[i18n-docs] ERRORS:\n  " . implode("\n  ", $errors) . "\n");
    exit(1);
}

echo sprintf(
    "[i18n-docs] %d documents and %d src types: links, anchors, claims, API inventory, source identity (%d stale occurrences) and llms.txt shape are consistent\n",
    count($documents),
    $inventoried,
    $staleSourceIdentityOccurrences,
);
