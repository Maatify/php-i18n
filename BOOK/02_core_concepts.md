# 02. Core Concepts

This chapter defines the strictly enforced terminology and data models used by the `maatify/php-i18n` library.

## Language Identity

Language identity is **owned by the Host**, not by the I18n package (see the [Package Reference](../I18N_PACKAGE_REFERENCE.md#4-language-code-contract)).
I18n stores an exact, nullable `language_code` (`VARCHAR(16)`) in `maa_i18n_translations` and has no dependency on any language registry or `languages` table.

*   `NULL` = the **exact unlocalized scope**.
*   `'ar'` = the **exact `ar` scope**.
*   There is no fallback, default-language or wildcard semantics: reading `'ar'` never returns `NULL`-scope or `'en'` rows, and reading `NULL` never returns a language row.
*   The code is stored as given (no trimming/lowercasing; `'ar'` and `'AR'` are different). It must be non-empty, not whitespace-only and at most 16 characters. Whether a code is a known/active/supported language is Host policy and is not checked.
*   Logical translation identity: `(key_id, language_code)`, NULL-safe (`language_code_identity` generated column).

## Structured Keys

A "Translation Key" is a structured tuple of three parts, enforced by the database schema (unique constraint on `scope, domain, key_part`).

```text
scope . domain . key_part
```

### 1. Scope
The high-level consumer or boundary of the translation.
*   **Examples:** `admin`, `client`, `system`, `api`, `email`.
*   **Constraint:** A translation key cannot exist unless its `scope` is defined in `maa_i18n_scopes` and is active.

### 2. Domain
The functional area or feature set within a scope.
*   **Examples:** `auth`, `billing`, `products`, `errors`.
*   **Constraint:** A translation key cannot exist unless its `domain` is defined in `maa_i18n_domains` and is mapped to the `scope`.

### 3. Key Part
The specific label or message identifier.
*   **Examples:** `login.title`, `form.email.label`, `error.required`.
*   **Format:** Typically uses dot-notation (e.g., `form.email.label`), but the library treats it as a single string unit.

### The Full Key
When requesting a translation, you **must** provide all three parts:

| Scope    | Domain      | Key Part    | Full Key String           |
|:---------|:------------|:------------|:--------------------------|
| `admin`  | `dashboard` | `welcome`   | `admin.dashboard.welcome` |
| `client` | `auth`      | `login.btn` | `client.auth.login.btn`   |

This structure prevents naming collisions and ensures deterministic loading of translation subsets.
