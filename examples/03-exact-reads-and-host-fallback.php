<?php

/**
 * Example 03 - Exact reads and Host-owned fallback.
 *
 * Capability: runtime reads. I18n reads exactly one language scope and never
 * falls back. A Host that wants a fallback composes it itself, outside I18n.
 *
 *   Input -> Public Call -> Result -> Boundary
 *   (code|null, scope, domain, key) -> getValue / getDomainValues
 *   -> exact string | null | empty DTO -> fail-soft, no exception for a miss
 *
 * Usage guide: docs/guides/USAGE_GUIDE.md (section "Read translations")
 */

declare(strict_types=1);

require __DIR__ . '/_support/bootstrap.php';
require __DIR__ . '/_support/ExampleCore.php';

use Maatify\I18n\Consumer\Service\TranslationReadService;
use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Management\Command\UpsertTranslationCommand;

echo 'Example 03 - Exact reads and Host-owned fallback', PHP_EOL;

$core = ExampleCore::from(example_pdo());
$core->scopes->create(new CreateScopeCommand('web', 'Website'));
$core->domains->create(new CreateDomainCommand('home', 'Home page'));
$core->assignments->assign('web', 'home');

$title = $core->writer->createKey(new CreateKeyCommand('web', 'home', 'title'));
$subtitle = $core->writer->createKey(new CreateKeyCommand('web', 'home', 'subtitle'));
$richCopy = $core->writer->createKey(new CreateKeyCommand('web', 'home', 'rich-copy'));

$core->writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'en',
    keyId: $title,
    value: 'Welcome',
    type: null,
));
$core->writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'ar-EG',
    keyId: $title,
    value: 'اهلا',
    type: null,
));
$core->writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: null,
    keyId: $title,
    value: 'Neutral title',
    type: null,
));
// An empty string is a real, authoritative value: it is NOT a missing translation.
$core->writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'fr',
    keyId: $subtitle,
    value: '',
    type: null,
));
$richMarkup = '<p>Formatted copy</p>';
$richType = 'client.rich-copy'; // Consumer-defined opaque token; I18n assigns no behavior to it.
$core->writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'en',
    keyId: $richCopy,
    value: $richMarkup,
    type: $richType,
));

// Exact semantics.
example_expect('exact code', 'Welcome', $core->reader->getValue('en', 'web', 'home', 'title'));
example_expect('regional code does not fall back to its base', null, $core->reader->getValue('ar', 'web', 'home', 'title'));
example_expect('codes are case-sensitive', null, $core->reader->getValue('EN', 'web', 'home', 'title'));
example_expect('null reads the unlocalized scope only', 'Neutral title', $core->reader->getValue(null, 'web', 'home', 'title'));
example_expect('empty string is authoritative', '', $core->reader->getValue('fr', 'web', 'home', 'subtitle'));
example_expect('value-only read remains a string', 'Welcome', $core->reader->getValue('en', 'web', 'home', 'title'));
example_expect(
    'rich read returns the opaque value and its type',
    ['value' => $richMarkup, 'type' => $richType],
    $core->reader->getTranslation('en', 'web', 'home', 'rich-copy')?->jsonSerialize(),
);
example_expect('an unknown key is null', null, $core->reader->getValue('en', 'web', 'home', 'nope'));
example_expect('an invalid code reads as null, not as an exception', null, $core->reader->getValue('   ', 'web', 'home', 'title'));
example_expect(
    'a bulk read returns only the exact scope',
    ['rich-copy' => $richMarkup, 'title' => 'Welcome'],
    $core->domainReader->getDomainValues('en', 'web', 'home')->all(),
);
example_expect(
    'rich bulk read exposes type without interpreting content',
    ['value' => $richMarkup, 'type' => $richType],
    $core->domainReader->getDomainTranslations('en', 'web', 'home')->get('rich-copy')?->jsonSerialize(),
);
example_expect('a bulk read of an unknown domain is empty', [], $core->domainReader->getDomainValues('en', 'web', 'nope')->all());

// Fallback is Host policy. This function is YOUR code, not part of I18n.
/**
 * @param list<string|null> $chain codes to try in order; null is the unlocalized scope
 */
function host_translate(
    TranslationReadService $reader,
    array $chain,
    string $scope,
    string $domain,
    string $key,
    string $default,
): string {
    foreach ($chain as $code) {
        $value = $reader->getValue($code, $scope, $domain, $key);
        if ($value !== null) {
            return $value; // '' is a valid answer and stops the chain
        }
    }

    return $default;
}

example_expect(
    'Host chain: ar-EG hit',
    'اهلا',
    host_translate($core->reader, ['ar-EG', 'en'], 'web', 'home', 'title', '?'),
);
example_expect(
    'Host chain: ar misses, the Host decides to try en',
    'Welcome',
    host_translate($core->reader, ['ar', 'en'], 'web', 'home', 'title', '?'),
);
example_expect(
    'Host chain: an authoritative empty value is not skipped',
    '',
    host_translate($core->reader, ['fr', 'en'], 'web', 'home', 'subtitle', '?'),
);
example_expect(
    'Host chain: nothing matched, the Host default applies',
    '?',
    host_translate($core->reader, ['de'], 'web', 'home', 'title', '?'),
);
