# 06. Translation Lifecycle

This chapter documents the lifecycle of translation keys and values managed by `TranslationWriteService`. Signatures and exact failure behavior are in the [Package Reference](../I18N_PACKAGE_REFERENCE.md#52-translation-writes-managementservicetranslationwriteservice); runnable code is in the [Usage Guide](../docs/guides/USAGE_GUIDE.md#5-create-keys-and-write-translations).

## 1. Creating Keys

The `createKey` method defines a new structured key, enforcing strict governance.

```php
// Define a new key for the 'auth' domain in 'client' scope
$keyId = $service->createKey(new CreateKeyCommand(
    scope: 'client',
    domain: 'auth',
    key: 'login.title',
    description: 'Main heading on the login page'
));
```

**Mandatory Validations:**
1.  **Scope** must exist and be active.
2.  **Domain** must exist and be active.
3.  **Mapping** must exist (`client` <-> `auth`).
4.  **Uniqueness:** The combination `(client, auth, login.title)` must not already exist.

**Exceptions:**
*   `DomainScopeViolationException` (Governance failure)
*   `TranslationKeyAlreadyExistsException` (Duplicate key)

## 2. Renaming Keys

The `renameKey` method renames and/or moves an existing key to a `(scope, domain, key_part)`, preserving its ID and translations.

```php
// Rename 'login.title' to 'login.header'
$service->renameKey(new RenameKeyCommand(
    keyId: $keyId,
    scope: 'client',
    domain: 'auth',
    key: 'login.header'
));
```

**Constraints:**
*   The target `(scope, domain)` must be allowed under the governance policy.
*   The new identity must not already exist (`TranslationKeyAlreadyExistsException`).
*   When the `(scope, domain)` changes, the derived summary of the old and the new pair is recomputed in the same transaction.

## 3. Managing Descriptions

Descriptions provide metadata for translators.

```php
$service->updateKeyDescription(
    keyId: $keyId,
    description: 'Updated context: This appears above the username field.'
);
```

## 4. Upserting Translations

The `upsertTranslation` method inserts or updates a translation value.

```php
// Set English Value (exact language code)
$translationId = $service->upsertTranslation(
    new UpsertTranslationCommand(languageCode: 'en-US', keyId: $keyId, value: 'Welcome Back', type: null)
);

// Update English Value (Overwrites previous)
$translationId = $service->upsertTranslation(
    new UpsertTranslationCommand(languageCode: 'en-US', keyId: $keyId, value: 'Please Log In', type: null)
);

// Single-language consumer: the exact unlocalized scope
$translationId = $service->upsertTranslation(
    new UpsertTranslationCommand(languageCode: null, keyId: $keyId, value: 'Welcome', type: null)
);

// A consumer may choose rich-text presentation handling for this exact token.
$translationId = $service->upsertTranslation(
    new UpsertTranslationCommand(
        languageCode: 'en-US',
        keyId: $keyId,
        value: '<p>Formatted copy</p>',
        type: TranslationType::WYSIWYG,
    )
);
```

**Behavior:**
*   Returns `int` (Translation ID).
*   Internally uses `TranslationUpsertResultDTO` to detect changes.
*   Synchronously refreshes the exact-scope summary row and the per-key counter if a new record is created.
*   The language code is only checked against the storage contract (`InvalidLanguageCodeException`); I18n never looks the language up.
*   `updated_at` timestamp is refreshed.
*   `type` is required explicitly on every command: `null` means no specialized type, and `TranslationType::WYSIWYG` is the canonical `wysiwyg` token. It is stored atomically with `value` and never changes row identity or completeness counts.
*   Type transitions (`null` to `wysiwyg` and back) update the existing translation row. The Package leaves `value` opaque and does not render or sanitize HTML; the consumer owns output handling.

## 5. Deleting Translations

The `deleteTranslation` method removes a specific translation value.

```php
$service->deleteTranslation(
    languageCode: 'en-US',
    keyId: $keyId
);
```

**Behavior:**
*   Returns `void`.
*   Internally tracks affected rows.
*   Synchronously refreshes the exact-scope summary row (removing it when no translation of that scope remains) and the per-key counter if a record was removed.

## 5.1 Renaming a Language Code

The code is the translation's identity, so a Host that renames a language code must move the rows explicitly:

```php
$rekeyed = $service->rekeyLanguageCode(oldCode: 'ar', newCode: 'ar-EG');
```

*   Re-keys every authoritative translation of `ar` to `ar-EG` and recomputes the derived summary rows, in one transaction.
*   Throws `LanguageCodeAlreadyInUseException` if `ar-EG` already owns translations (nothing is merged or lost).
*   The Host must call it in the same transaction, on the same connection, as its own language rename.

## 6. Key Deletion

**Status: NOT SUPPORTED**

The module does not support deleting keys (`deleteKey`).
*   **Rationale:** Deleting keys breaks historical context and referential integrity in consuming applications.
*   **Strategy:** Deprecated keys should be left as-is or renamed with a `deprecated.` prefix if necessary.
