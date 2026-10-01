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
```

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

**Behavior:**
*   Returns strictly typed `TranslationDomainValuesDTO`.
*   Contains only values of the requested exact scope (no fallback values).
*   Returns empty array `[]` if domain has no keys or is invalid.

**Performance Note:**
The current implementation iterates through keys and fetches translations individually (N+1 pattern). It is **strongly recommended** to wrap this service in a caching layer.

## 4. Caching Strategy

The library implementation does **not** cache data. It queries the database directly.

**Integration Requirement:**
You **must** wrap `TranslationDomainReadService` in a caching layer (e.g., Redis).
*   **Key Pattern:** `i18n:domain:{scope}:{domain}:{lang_code}`
*   **Invalidation:** Must occur on `TranslationWriteService` upsert/delete.
