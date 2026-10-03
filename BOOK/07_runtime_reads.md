# 07. Runtime Reads

This chapter details the fail-soft behavior of runtime translation reads.

## 1. Fail-Soft Philosophy

All read services (`TranslationReadService`, `TranslationDomainReadService`) implement a strict **fail-soft** strategy.

*   **Exceptions:** none. Reads never throw for data problems.
*   **Missing Data:**
    *   Missing Key → Returns `null`.
    *   Missing Translation in the requested exact scope → Returns `null` (there is **no fallback** inside I18n).
    *   Invalid Domain → Returns empty DTO.
    *   Invalid language code (empty / whitespace / > 16 chars) → `null` / empty DTO.

**Exact scope only:** `getValue('ar', …)` reads `ar` only; `getValue(null, …)` reads the unlocalized (`NULL`) scope only. A miss never retries on another language or on `NULL`. Fallback is a Host policy: the Host wraps `getValue` with its own chain of codes.

**Rationale:**
A missing translation must not cause a fatal application error.

## 2. Single Value Read (`TranslationReadService`)

Fetches a specific translation string for one exact language scope.

```php
$value = $readService->getValue(
    languageCode: 'en-US',
    scope: 'client',
    domain: 'auth',
    key: 'login.title'
);

// Returns "Log In" OR null
if ($value === null) {
    // Key or translation missing
}

$translation = $readService->getTranslation(
    languageCode: 'en-US',
    scope: 'client',
    domain: 'auth',
    key: 'login.title'
);
// TranslationValueDTO: ['value' => 'Log In', 'type' => null] or null on an exact miss.
```

`getValue()` remains a value-only compatibility read. `getTranslation()` returns the value and optional type together. Both use the same exact-scope and fail-soft rules.

**Performance:**
*   Executes a query to resolve the key.
*   Executes a query to fetch the translation of the exact scope.
*   **Recommendation:** Use sparingly.

## 3. Bulk Domain Read (`TranslationDomainReadService`)

Fetches all translations for a specific `Scope` + `Domain`.

```php
$dto = $domainReadService->getDomainValues(
    languageCode: 'en-US',
    scope: 'client',
    domain: 'auth'
);

// $dto is strictly typed: TranslationDomainValuesDTO
$translations = $dto->all();

// Result: ['login.title' => 'Log In', 'register.btn' => 'Sign Up']
```

For typed rows, use the rich domain read:

```php
$typed = $domainReadService->getDomainTranslations('en-US', 'client', 'auth');
$login = $typed->get('login.title'); // TranslationValueDTO: value + nullable type
```

**Behavior:**
*   Returns strictly typed `TranslationDomainValuesDTO`.
*   Contains only values of the requested exact scope (no fallback values).
*   Returns empty array `[]` if domain has no keys or is invalid.
*   `getDomainValues()` remains value-only. `getDomainTranslations()` returns `key_part => TranslationValueDTO`; absent rows are omitted, while an existing row with an empty value remains present.
*   Every non-null type is an opaque consumer-defined token. I18n does not define its vocabulary or assign behavior to it, and does not trust, render or sanitize value content.

**Performance Guidance (non-normative):**
The package does not cache reads. A Host that repeatedly reads the same domain may choose to cache bulk reads when appropriate. If the Host caches these results, it is responsible for invalidating them after writes through `TranslationWriteService`.

## 4. Caching Strategy

The library implementation does **not** cache data. It queries the database directly.
