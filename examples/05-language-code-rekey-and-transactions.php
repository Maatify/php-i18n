<?php

/**
 * Example 05 - Language-code re-key and caller-owned transactions.
 *
 * Capability: renaming a Host language code atomically with the Host's own
 * data, and joining a transaction your application already opened.
 *
 *   Input -> Public Call -> Result -> Boundary
 *   (old code, new code) inside YOUR transaction -> rekeyLanguageCode
 *   -> number of re-keyed rows -> same PDO connection, you commit or roll back
 *
 * Usage guide: docs/guides/USAGE_GUIDE.md (section "Transactions and language-code changes")
 */

declare(strict_types=1);

require __DIR__ . '/_support/bootstrap.php';
require __DIR__ . '/_support/ExampleCore.php';

use Maatify\I18n\Exception\LanguageCodeAlreadyInUseException;
use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Management\Command\UpsertTranslationCommand;
use Maatify\I18n\Management\Criteria\KeyListCriteria;

echo 'Example 05 - Language-code re-key and transactions', PHP_EOL;

$pdo = example_pdo();
$core = ExampleCore::from($pdo);

// A stand-in for YOUR language registry. It is not Package-owned and I18n never reads it.
$pdo->exec('CREATE TABLE host_languages (code VARCHAR(16) NOT NULL PRIMARY KEY, name VARCHAR(64) NOT NULL)');
$pdo->exec("INSERT INTO host_languages (code, name) VALUES ('ar', 'Arabic'), ('en', 'English')");

$core->scopes->create(new CreateScopeCommand('web', 'Website'));
$core->domains->create(new CreateDomainCommand('home', 'Home page'));
$core->assignments->assign('web', 'home');
$keyId = $core->writer->createKey(new CreateKeyCommand('web', 'home', 'title'));
$core->writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'ar',
    keyId: $keyId,
    value: 'مرحبا',
    type: null,
));
$core->writer->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'en',
    keyId: $keyId,
    value: 'Welcome',
    type: null,
));

// 1. Rename the code in YOUR registry and re-key I18n in ONE transaction.
$pdo->beginTransaction();
$pdo->exec("UPDATE host_languages SET code = 'ar-EG' WHERE code = 'ar'");
$rekeyed = $core->writer->rekeyLanguageCode('ar', 'ar-EG');
$pdo->commit();

example_expect('one translation was re-keyed', 1, $rekeyed);
example_expect('the old code owns nothing', null, $core->reader->getValue('ar', 'web', 'home', 'title'));
example_expect('the new code owns the value', 'مرحبا', $core->reader->getValue('ar-EG', 'web', 'home', 'title'));

// 2. A failure inside the same transaction rolls back BOTH sides.
$pdo->beginTransaction();
$pdo->exec("UPDATE host_languages SET code = 'ar' WHERE code = 'ar-EG'");
$core->writer->rekeyLanguageCode('ar-EG', 'ar');
$pdo->rollBack();

example_expect('rollback: I18n keeps the committed code', 'مرحبا', $core->reader->getValue('ar-EG', 'web', 'home', 'title'));
$registry = $pdo->query('SELECT code FROM host_languages ORDER BY code');
if ($registry === false) {
    throw new RuntimeException('Query failed.');
}
example_expect(
    'rollback: the Host registry kept the committed code too',
    ['ar-EG', 'en'],
    $registry->fetchAll(PDO::FETCH_COLUMN),
);

// 3. A re-key never merges: the target code must own no translations.
example_expect_throws(
    're-key onto an occupied code is refused',
    LanguageCodeAlreadyInUseException::class,
    static fn() => $core->writer->rekeyLanguageCode('ar-EG', 'en'),
);

// 4. Any I18n write joins a transaction you already opened and never commits it.
$pdo->beginTransaction();
$transientId = $core->writer->createKey(new CreateKeyCommand('web', 'home', 'transient'));
example_expect('inside your transaction the write is visible', 'transient', $core->managementRead->getKey($transientId)->key);
$pdo->rollBack();
example_expect(
    'your rollback removed the participating write',
    ['title'],
    array_map(static fn($k): string => $k->key, $core->managementRead->searchKeys(new KeyListCriteria('web'))->data),
);
