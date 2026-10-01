# ADR-020: Nullable Translation Type Metadata Contract

**Status:** ACTIVE
**Date:** 2026-10-02
**Decision ID:** ADR-020
**Decision authority:** Package Owner
**Scope:** Translation write, persistence, management reads, and consumer reads in `maatify/php-i18n`
**Canonical contract:** [I18n Package Reference](../../I18N_PACKAGE_REFERENCE.md)

## Context

Consumers need an optional signal for specialized translation presentation while the Package continues to store and return translation content as an opaque authoritative string. The signal must not alter the existing exact language scope, translation identity, fallback, or completeness contracts.

## Decision

Each translation row has nullable `type` metadata with this contract:

- `NULL` means no specialized presentation type is declared.
- A non-null type is an exact, non-empty, non-whitespace-only string of at most 32 characters. Validation does not trim, lowercase, or normalize it.
- `TranslationType::WYSIWYG` is the canonical `wysiwyg` use case. It lets a consumer choose rich-text presentation handling; it does not establish that the value is safe HTML or prescribe a renderer or editor.
- `type` is stored atomically with `value` and is not part of translation identity, `language_code_identity`, fallback, missing-row semantics, or derived completeness counts.
- `language_code = NULL` remains the exact unlocalized scope. `value = ''` remains an existing authoritative translation. A missing row remains distinct from an existing row whose `type` is `NULL`.
- `TranslationDTO`, management translation-row reads, and the new rich consumer read methods expose `type`. The existing `getValue()` and `getDomainValues()` methods remain value-only compatibility APIs.
- `value` remains opaque. The Package does not render, sanitize, trust, or interpret its content based on `type`.
- Language-code re-keying preserves the type stored on each row.

## Rationale

Keeping presentation metadata beside the translation value lets consumers select handling without encoding metadata into the value or language identity. Nullability preserves existing rows and callers that do not declare a specialized type. Keeping identity and completeness unchanged preserves established language-scope and derived-state behavior.

## Consequences

- `UpsertTranslationCommand` requires callers to provide `type` explicitly, including `null`.
- The translation table gains a nullable `VARCHAR(32)` column with binary collation and a check constraint. The additive migration targets the exact pre-S1 schema and assigns `NULL` to existing rows.
- `TranslationReadService::getTranslation()` and `TranslationDomainReadService::getDomainTranslations()` expose `value` and `type`; existing value-only methods retain their result shapes.
- Management grid and per-language translation rows expose `type`. Summary DTOs and statistics remain concerned only with translation existence and counts.
- Consumers remain responsible for escaping or sanitizing values for their output context.

## Alternatives

Encoding the type in `value` or `language_code` was rejected because it changes authoritative content or language identity. Rendering or sanitizing in the Package was rejected because presentation and output safety belong to consumers.
