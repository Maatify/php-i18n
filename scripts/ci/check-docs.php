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

/** Maintained consumer and governance documents (ADRs under dcos/ are historical decision records). */
$documents = array_merge(
    ['README.md',
        'I18N_PACKAGE_REFERENCE.md',
        'ARCHITECTURE.md',
        'CHANGELOG.md',
        'llms.txt',
        'SECURITY.md',
        'CONTRIBUTING.md',
        'CODE_OF_CONDUCT.md',
    ],
    glob('docs/guides/*.md') ?: [],
    glob('BOOK/*.md') ?: [],
    glob('consumer-verification/*.md') ?: [],
);

/** Current-state consumer and governance docs; historical ADRs under dcos/ are deliberately excluded. */
$currentStateDocuments = array_merge(
    ['README.md',
        'I18N_PACKAGE_REFERENCE.md',
        'ARCHITECTURE.md',
        'CHANGELOG.md',
        'llms.txt',
        'SECURITY.md',
        'CONTRIBUTING.md',
        'CODE_OF_CONDUCT.md',
    ],
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
        '/https?:\/\/github\.com\/Maatify\/i18n\b/i' => 'stale repository URL (the repository is Maatify/php-i18n)',
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

}

// S15: the first RC is allocated for preparation, but has not been published.
$readme = (string) file_get_contents('README.md');
$changelog = (string) file_get_contents('CHANGELOG.md');
$llms = (string) file_get_contents('llms.txt');
$readmeHeader = explode("\n---\n", $readme, 2)[0];
$canonicalLogo = '![Maatify.dev](https://www.maatify.dev/assets/img/img/maatify_logo_white.svg)';


$canonicalReadmeBadges = [
    '[![Status](https://img.shields.io/badge/Status-Development-blue)](README.md)',
    '[![PHP](https://img.shields.io/badge/PHP-8.4-8892BF)](composer.json)',
    '[![License](https://img.shields.io/badge/License-Proprietary-green)](LICENSE)',
    '[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-blueviolet)](https://github.com/Maatify)',
];
$requiredDocumentationBadges = [
    '[![Usage Guide](https://img.shields.io/badge/Docs-Usage%20Guide-informational)](docs/guides/USAGE_GUIDE.md)',
    '[![Examples](https://img.shields.io/badge/Docs-Examples-informational)](examples/)',
    '[![Package Reference](https://img.shields.io/badge/Docs-Package%20Reference-informational)](I18N_PACKAGE_REFERENCE.md)',
    '[![Changelog](https://img.shields.io/badge/Docs-Changelog-informational)](CHANGELOG.md)',
    '[![Security](https://img.shields.io/badge/Docs-Security-informational)](SECURITY.md)',
    '[![Contributing](https://img.shields.io/badge/Docs-Contributing-informational)](CONTRIBUTING.md)',
];
foreach ($canonicalReadmeBadges as $badge) {
    if (substr_count($readmeHeader, $badge) !== 1) {
        $errors[] = 'README.md: canonical unpublished badge is missing or duplicated: ' . $badge;
    }
}
foreach ($requiredDocumentationBadges as $badge) {
    if (substr_count($readmeHeader, $badge) !== 1) {
        $errors[] = 'README.md: required documentation badge is missing or duplicated: ' . $badge;
    }
}
if (str_contains($readmeHeader, 'style=for-the-badge')) {
    $errors[] = 'README.md: for-the-badge is forbidden for README badges';
}
$badgeOrder = [
    'Status-Development-blue',
    'PHP-8.4-8892BF',
    'License-Proprietary-green',
    'PHPStan-Level%20Max-4E8CAE',
    'Maatify-Ecosystem-blueviolet',
    'Docs-Usage%20Guide-informational',
    'Docs-Examples-informational',
    'Docs-Package%20Reference-informational',
    'Docs-Changelog-informational',
    'Docs-Security-informational',
    'Docs-Contributing-informational',
];
$previousBadgePosition = -1;
foreach ($badgeOrder as $badgeToken) {
    $position = strpos($readmeHeader, $badgeToken);
    if ($position === false) {
        if ($badgeToken === 'PHPStan-Level%20Max-4E8CAE') {
            $errors[] = 'README.md: proven PHPStan Level Max badge is missing';
        }
        continue;
    }
    if ($position <= $previousBadgePosition) {
        $errors[] = 'README.md: badge groups/order do not follow the canonical Status/PHP/License/PHPStan → Ecosystem → Documentation order';
        break;
    }
    $previousBadgePosition = $position;
}

$governanceBadgeContracts = [
    'CODE_OF_CONDUCT.md' => [
        '[![Maatify I18n](https://img.shields.io/badge/Maatify-I18n-blue?style=for-the-badge)](https://github.com/Maatify/php-i18n)',
        '[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-9C27B0?style=for-the-badge)](https://github.com/Maatify)',
    ],
    'SECURITY.md' => [
        '[![Maatify I18n](https://img.shields.io/badge/Maatify-I18n-blue?style=for-the-badge)](https://github.com/Maatify/php-i18n)',
        '[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-9C27B0?style=for-the-badge)](https://github.com/Maatify)',
    ],
];
foreach ($governanceBadgeContracts as $document => $requiredBadges) {
    $governanceText = (string) file_get_contents($document);
    foreach ($requiredBadges as $badge) {
        if (substr_count($governanceText, $badge) !== 1) {
            $errors[] = $document . ': canonical governance identity badge is missing or duplicated: ' . $badge;
        }
    }
    if (preg_match_all('/img\\.shields\\.io\\/badge\\//', $governanceText) !== 2) {
        $errors[] = $document . ': exactly two governance identity badges are required';
    }
}

if (substr_count($readmeHeader, $canonicalLogo) !== 1
    || preg_match_all('/!\[Maatify\.dev\]\([^\n)]+\)/', $readmeHeader) !== 1) {
    $errors[] = 'README.md: header must use exactly the canonical Maatify logo source';
}
if (str_contains($readmeHeader, 'https://github.com/Maatify.png')) {
    $errors[] = 'README.md: previous GitHub avatar logo source is forbidden';
}
if (preg_match('/<img\b[^>]*(?:maatify|logo)[^>]*>/i', $readmeHeader) === 1) {
    $errors[] = 'README.md: HTML logo or custom logo sizing is forbidden';
}
foreach (explode("\n", $readmeHeader) as $line) {
    if (preg_match('/Maatify\.dev|maatify_logo|github\.com\/Maatify\.png/i', $line) === 1
        && preg_match('/\b(?:width|height|size)\s*=|\{[^}]*\b(?:width|height)\s*=/i', $line) === 1) {
        $errors[] = 'README.md: custom logo sizing is forbidden';
    }
}

if (preg_match('/^## \[1\.0\.0-rc\.1\]\R(.*?)(?=^## |\z)/ms', $changelog, $targetSection) !== 1
    || preg_match('/^### Added$/m', $targetSection[1]) !== 1
    || preg_match('/^- /m', $targetSection[1]) !== 1) {
    $errors[] = 'CHANGELOG.md: initial contents must be allocated under the undated 1.0.0-rc.1 target';
}
if (preg_match('/^## \[Unreleased\]\R(.*?)(?=^## |\z)/ms', $changelog, $unreleasedSection) === 1
    && trim($unreleasedSection[1]) !== '') {
    $errors[] = 'CHANGELOG.md: allocated initial contents must not remain under Unreleased';
}
if (preg_match('/initial (?:package )?contents[^\n]*\[Unreleased\]/i', $changelog) === 1
    || str_contains($readme, '[Unreleased]')
    || str_contains($llms, '[Unreleased]')) {
    $errors[] = 'Release navigation: initial contents must not be described as Unreleased after allocation';
}
if (preg_match('/^\[1\.0\.0-rc\.1\]:/m', $changelog) === 1) {
    $errors[] = 'CHANGELOG.md: unpublished RC must have no release link';
}

if (!str_contains($readme, 'Release Target: `1.0.0-rc.1`')
    || !str_contains($readme, 'Publication State: Unpublished')
    || !str_contains($readmeHeader, 'Status-Development-blue')
    || preg_match('#img\.shields\.io/badge/Version-#i', $readme) === 1) {
    $errors[] = 'README.md: release target, unpublished state and Development badge must stay synchronized';
}
if (!str_contains($llms, 'Release Target 1.0.0-rc.1')
    || !str_contains($llms, 'Publication State Unpublished')
    || !str_contains($llms, '[CHANGELOG.md](CHANGELOG.md)')) {
    $errors[] = 'llms.txt: exact RC target and unpublished navigation must stay synchronized';
}
foreach (['README.md' => $readme, 'CHANGELOG.md' => $changelog, 'llms.txt' => $llms] as $document => $text) {
    if (preg_match('/(?:1\.0\.0-rc\.1|release candidate|\bRC\b)\s+(?:(?:is|was|has been)\s+)?(?:published|released|externally available|resolvable|installable)\b/i', $text) === 1
        || preg_match('/\bPublication State:\s*(?:Published|Release Candidate|Stable)\b/i', $text) === 1
        || preg_match('/\b(?:release|publication) date\b\s*[:=-]?\s*\d{4}-\d{2}-\d{2}\b/i', $text) === 1
        || preg_match('/\b(?:is|was|has been)\s+(?:available|published)\s+(?:on|via)\s+Packagist\b/i', $text) === 1
        || preg_match('#github\.com/Maatify/php-i18n/(?:releases/tag|tree|tags)/v?1\.0\.0-rc\.1\b#i', $text) === 1) {
        $errors[] = $document . ': unpublished RC must not claim publication, distribution or an existing tag';
    }
}

/** Current package documentation and schema must retain the standalone artifact identity. */
$currentArtifactIdentitySurfaces = array_merge(
    $currentStateDocuments,
    ['schema/schema.i18n.sql'],
);
$staleArtifactForm = [
    '/Embedded Base Module/i' => 'stale embedded-artifact form claim in a standalone package',
    '/Modules\/I18n\b/i' => 'stale embedded module path in a standalone package',
    '/\bI18n\s+module\b/i' => 'the standalone package is identified as a module',
    '/\b(?:this|the)\s+module\b/i' => 'the standalone package is identified as a module',
    '/\bmodule\s*\(\s*translation\s+layer\s*\)/i' => 'the standalone package is identified as a module',
    '/\bcross-module\s+coupling\b/i' => 'stale module-framed package boundary wording',
];
foreach ($currentArtifactIdentitySurfaces as $document) {
    $text = (string) file_get_contents($document);
    foreach ($staleArtifactForm as $pattern => $reason) {
        if (preg_match($pattern, $text, $m) === 1) {
            $errors[] = sprintf('%s: "%s": %s', $document, $m[0], $reason);
        }
    }
}

/** First-RC consumer documents describe current state, not unpublished engineering stages. */
$stalePrePublicationLineage = [
    '/\bpre-S1\b/i' => 'unpublished pre-S1 schema lineage in consumer documentation',
    '/\bS1\s+(?:schema\s+)?(?:upgrade|migration)\b/i' => 'unpublished S1 upgrade or migration framing in consumer documentation',
    '/\b(?:dcos|docs\/decisions)\//i' => 'internal decision-record path in consumer documentation',
];
$staleFirstRcOccurrences = 0;
foreach ($currentStateDocuments as $document) {
    $text = (string) file_get_contents($document);
    foreach ($stalePrePublicationLineage as $pattern => $reason) {
        $matches = preg_match_all($pattern, $text, $m);
        $staleFirstRcOccurrences += $matches;
        if ($matches > 0) {
            $errors[] = sprintf('%s: "%s": %s', $document, $m[0][0], $reason);
        }
    }
}

// The current decision index is internal governance, not consumer navigation.
// Keep its current-state framing clean without rewriting historical ADR bodies.
$decisionIndex = (string) file_get_contents('docs/decisions/DECISIONS_INDEX.md');
$staleDecisionIndexFraming = [
    '/\bLegacy record\b/i' => 'legacy framing in the current Decision Index',
    '/\bexisting legacy paths\b/i' => 'legacy path framing in the current Decision Index',
    '/\bthis S1 change\b/i' => 'internal stage lineage in the current Decision Index',
    '/\bpre-S1\b/i' => 'pre-publication lineage in the current Decision Index',
    '/\bS1\s+(?:schema\s+)?(?:upgrade|migration)\b/i' => 'internal stage lineage in the current Decision Index',
];
foreach ($staleDecisionIndexFraming as $pattern => $reason) {
    $matches = preg_match_all($pattern, $decisionIndex, $m);
    $staleFirstRcOccurrences += $matches;
    if ($matches > 0) {
        $errors[] = sprintf('docs/decisions/DECISIONS_INDEX.md: "%s": %s', $m[0][0], $reason);
    }
}
foreach (['ADR-018', 'ADR-019', 'ADR-020'] as $decisionId) {
    $rowPattern = '/^\|\s*' . preg_quote($decisionId, '/')
        . '\s*\|[^|]+\|\s*ACTIVE\s*\|[^|]+\|\s*\[[^\]]+\]\([^)]+\)\s*\|\s*'
        . '\[Package Reference\]\([^)]+\)\s*\|/m';
    if (preg_match($rowPattern, $decisionIndex) !== 1) {
        $errors[] = 'docs/decisions/DECISIONS_INDEX.md: missing ACTIVE current decision row with record and Package Reference owner: ' . $decisionId;
    }
}

// Keep both supported package archive mechanisms excluding pre-publication
// decision and migration history from first-RC distribution artifacts.
$requiredDistributionPaths = [
    'dcos',
    'docs/decisions',
    'dcos/ADR-018-string-codes-instead-of-fk-in-i18n.md',
    'dcos/ADR-019-host-owned-exact-language-code-in-i18n.md',
    'docs/decisions/ADR-020-nullable-translation-type-metadata.md',
    'docs/decisions/DECISIONS_INDEX.md',
    'schema/migrations/2026-10-02-translation-type.sql',
    'tests/Fixtures/Schema/schema.pre-s1.i18n.sql',
    'tests/Integration/TranslationTypeMigrationTest.php',
];
$requiredComposerExclusions = [
    '/dcos',
    '/docs/decisions',
    '/schema/migrations/2026-10-02-translation-type.sql',
    '/tests/Fixtures/Schema/schema.pre-s1.i18n.sql',
    '/tests/Integration/TranslationTypeMigrationTest.php',
];
$composerData = json_decode((string) file_get_contents('composer.json'), true);
$composerExclusions = $composerData['archive']['exclude'] ?? [];
foreach ($requiredComposerExclusions as $exclusion) {
    if (!in_array($exclusion, $composerExclusions, true)) {
        $errors[] = 'composer.json: archive.exclude is missing required path: ' . $exclusion;
    }
}
foreach ($requiredDistributionPaths as $path) {
    $attributeOutput = [];
    exec('git check-attr export-ignore -- ' . escapeshellarg($path), $attributeOutput, $attributeStatus);
    if ($attributeStatus !== 0 || !in_array($path . ': export-ignore: set', $attributeOutput, true)) {
        $errors[] = '.gitattributes: export-ignore is missing for required path: ' . $path;
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

// PHP source and tests must use the current Composer and repository identity.
$staleSourceTestIdentityOccurrences = 0;
foreach (['src', 'tests'] as $sourceRoot) {
    $sourceIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($sourceIterator as $sourceFile) {
        if (!$sourceFile instanceof SplFileInfo || $sourceFile->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($sourceFile->getPathname());
        $staleSourceTestIdentityOccurrences += preg_match_all(
            '/maatify\/i18n\b|maatify:i18n\b|https?:\/\/github\.com\/Maatify\/i18n\b/i',
            $source,
        );
    }
}
if ($staleSourceTestIdentityOccurrences !== 0) {
    $errors[] = sprintf(
        'src/tests: %d stale package or repository identity occurrence(s)',
        $staleSourceTestIdentityOccurrences,
    );
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
    "[i18n-docs] %d documents and %d src types: links, anchors, claims, API inventory, source/test identity (%d stale identity occurrences), first-RC current-state sweep (%d stale occurrences) and llms.txt shape are consistent\n",
    count($documents),
    $inventoried,
    $staleSourceTestIdentityOccurrences,
    $staleFirstRcOccurrences,
);
