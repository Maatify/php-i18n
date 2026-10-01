# 09. Real World Scenarios

This chapter provides end-to-end usage examples for common requirements.

## Scenario 1: Feature Expansion (Dark Mode)

**Requirement:** Add translation keys for a "Dark Mode" toggle in the User Dashboard (`client` scope).

**Steps:**
1.  **Check Governance:**
    Ensure `client` scope and `dashboard` domain exist and are mapped in `maa_i18n_domain_scopes`.
    ```sql
    SELECT * FROM maa_i18n_domain_scopes WHERE scope_code='client' AND domain_code='dashboard';
    ```

2.  **Create Keys:** (Write Service)
    ```php
    $keyId1 = $writeService->createKey(new CreateKeyCommand('client', 'dashboard', 'settings.dark_mode.label'));
    $keyId2 = $writeService->createKey(new CreateKeyCommand('client', 'dashboard', 'settings.dark_mode.on'));
    $keyId3 = $writeService->createKey(new CreateKeyCommand('client', 'dashboard', 'settings.dark_mode.off'));
    ```

3.  **Add Translations:** (Write Service)
    ```php
    $writeService->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'en-US',
    keyId: $keyId1,
    value: 'Dark Mode',
    type: null,
));
    $writeService->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'en-US',
    keyId: $keyId2,
    value: 'On',
    type: null,
));
    $writeService->upsertTranslation(new UpsertTranslationCommand(
    languageCode: 'en-US',
    keyId: $keyId3,
    value: 'Off',
    type: null,
));
    ```

4.  **Runtime Usage:** (Read Service)
    ```php
    $translations = $domainReadService->getDomainValues('en-US', 'client', 'dashboard');
    ```

## Scenario 2: Regional Fallback (Host-owned)

**Requirement:** Add translations for `es-MX` (Mexican Spanish) which falls back to `es-ES` (Spain Spanish).

**Prerequisite:**
The Host owns the language list and the fallback rule. This module stores exact codes only and never applies a fallback.

**Steps:**
1.  **Add Translations:**
    Assume `es-ES` has all base translations. We only override specific keys for Mexico.

    ```php
    // Override the 'Welcome' message for Mexico
    $writeService->upsertTranslation(new UpsertTranslationCommand(
        languageCode: 'es-MX',
        keyId: $welcomeKeyId,
        value: '¡Bienvenido a México!',
        type: null,
    ));
    // Other keys are left empty for es-MX
    ```

2.  **Runtime Logic:**
    When requesting a key for `es-MX`:
    ```php
    $text = $readService->getValue('es-MX', 'client', 'auth', 'login');
    ```
    *   If `es-MX` has a value, it is returned.
    *   If not, **I18n returns `null`**. It does not consult `es-ES`.
    *   The Host implements the fallback explicitly, e.g. `$readService->getValue('es-MX', …) ?? $readService->getValue('es-ES', …)`, or loads the `es-ES` values first and overlays the `es-MX` values.

## Scenario 3: Key Refactoring

**Requirement:** Rename `client.auth.btn_submit` ("Log In") to `client.auth.login.submit`.

**Steps:**
1.  **Find the Key ID:**
    ```sql
    SELECT id FROM maa_i18n_keys WHERE key_part='btn_submit';
    -- Assume ID = 105
    ```

2.  **Rename:**
    ```php
    $writeService->renameKey(new RenameKeyCommand(
        keyId: 105,
        scope: 'client',
        domain: 'auth',
        key: 'login.submit'
    ));
    ```

3.  **Result:**
    *   ID `105` is preserved.
    *   All translations are retained.
    *   Old key `btn_submit` is removed.
    *   Runtime reads **must** use `login.submit`.
