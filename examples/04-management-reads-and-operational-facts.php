<?php

/**
 * Example 04 - Management reads, operational facts and derived-state repair.
 *
 * Capability: paginated lists for an admin screen, exact-code coverage facts,
 * Host composition of those facts with its own language metadata, and the
 * operational rebuild of the derived tables.
 *
 *   Input -> Public Call -> Result -> Boundary
 *   Criteria (+ exact codes you supply) -> management / operational read
 *   -> PageResult / DTOs -> I18n never reads your language table; you compose
 *
 * Usage guide: docs/guides/USAGE_GUIDE.md (sections "Management reads" and
 * "Operational facts")
 */

declare(strict_types=1);

require __DIR__ . '/_support/bootstrap.php';
require __DIR__ . '/_support/ExampleCore.php';

use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Management\Command\UpsertTranslationCommand;
use Maatify\I18n\Management\Criteria\DomainKeySummaryCriteria;
use Maatify\I18n\Management\Criteria\DomainTranslationGridCriteria;
use Maatify\I18n\Management\Criteria\KeyListCriteria;
use Maatify\I18n\Management\Criteria\LanguageTranslationValuesCriteria;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

echo 'Example 04 - Management reads and operational facts', PHP_EOL;

$core = ExampleCore::from(example_pdo());
$core->scopes->create(new CreateScopeCommand('web', 'Website'));
$core->domains->create(new CreateDomainCommand('home', 'Home page'));
$core->assignments->assign('web', 'home');

$ids = [];
foreach (['title', 'subtitle', 'footer'] as $part) {
    $ids[$part] = $core->writer->createKey(new CreateKeyCommand('web', 'home', $part));
}
$core->writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'en',
    keyId: $ids['title'],
    value: 'Welcome',
    type: null,
));
$core->writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'en',
    keyId: $ids['subtitle'],
    value: 'Hello',
    type: null,
));
$core->writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'ar',
    keyId: $ids['title'],
    value: 'مرحبا',
    type: null,
));

// Paginated key list of one scope. Pagination mechanics come from maatify/persistence.
$keys = $core->managementRead->searchKeys(new KeyListCriteria(
    'web',
    page: new PageRequest(page: 1, perPage: 2, sortBy: 'key_part', sortDirection: 'asc'),
));
example_expect('first page holds two keys', ['footer', 'subtitle'], array_map(static fn($k): string => $k->key, $keys->data));
example_expect('the page knows the filtered total', 3, $keys->filtered);
example_expect('the page knows there is a next page', true, $keys->hasNext);

// The Host decides which exact codes it cares about and passes them in.
$hostCodes = ['en', 'ar', 'fr'];

$summaries = $core->managementRead->pageDomainKeySummaries(new DomainKeySummaryCriteria('web', 'home', $hostCodes));
$missing = [];
foreach ($summaries->data as $summary) {
    $missing[$summary->keyPart] = $summary->missingCount;
}
ksort($missing);
example_expect('missing count per key over the supplied codes', ['footer' => 3, 'subtitle' => 2, 'title' => 1], $missing);

$grid = $core->managementRead->pageDomainTranslationGrid(new DomainTranslationGridCriteria('web', 'home', ['ar', 'fr']));
$cells = [];
foreach ($grid->data as $cell) {
    $cells[$cell->keyPart . ':' . $cell->languageCode] = $cell->value;
}
ksort($cells);
example_expect(
    'grid cells: value or null for a missing translation',
    ([
        'footer:ar' => null,
        'footer:fr' => null,
        'subtitle:ar' => null,
        'subtitle:fr' => null,
        'title:ar' => 'مرحبا',
        'title:fr' => null,
    ]),
    $cells,
);

$english = $core->managementRead->pageLanguageTranslationValues(new LanguageTranslationValuesCriteria('en'));
$values = [];
foreach ($english->data as $row) {
    $values[$row->keyPart] = $row->value;
}
ksort($values);
example_expect('every key with its exact English value', ['footer' => null, 'subtitle' => 'Hello', 'title' => 'Welcome'], $values);

// Operational facts are exact-code counts. Names, order and "languages with zero
// translations" are composed by the Host from its own language data.
$hostLanguages = ['en' => 'English', 'ar' => 'Arabic', 'fr' => 'French'];
$total = $core->operationalRead->totalKeyCount();
$translated = [];
foreach ($core->operationalRead->translatedCountByLanguageCode() as $count) {
    if ($count->languageCode !== null) {
        $translated[$count->languageCode] = $count->count;
    }
}

$dashboard = [];
foreach ($hostLanguages as $code => $name) {
    $dashboard[$name] = $translated[$code] ?? 0;
}
example_expect('total keys', 3, $total);
example_expect('Host-composed dashboard (French has no row, so zero)', ['English' => 2, 'Arabic' => 1, 'French' => 0], $dashboard);

$coverage = $core->operationalRead->domainCoverage('web', 'en');
example_expect('per-domain coverage for English', [2, 3], [$coverage[0]->translatedCount, $coverage[0]->totalKeys]);

// Derived state is rebuildable from the authoritative tables, deterministically.
$rows = $core->operationalRead->summaryRowCount();
$core->rebuilder->fullRebuild();
example_expect('rebuild reproduces the derived summary', $rows, $core->operationalRead->summaryRowCount());
