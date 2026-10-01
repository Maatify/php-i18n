# Decisions Index

Current decision discovery for `maatify/php-i18n`. Decision rationale remains in each linked record; current public behavior is owned by the canonical contract listed below.

| Decision ID | Title | Status | Scope / Concern | Decision Record | Canonical Contract / Current Owner | Supersedes | Superseded By |
|---|---|---|---|---|---|---|---|
| ADR-018 | String codes for scope and domain identity | ACTIVE | Scope/domain persistence identity | [Legacy record](../../dcos/ADR-018-string-codes-instead-of-fk-in-i18n.md) | [Package Reference](../../I18N_PACKAGE_REFERENCE.md) | — | — |
| ADR-019 | Host-owned exact nullable language code | ACTIVE | Translation language identity, exact scope, and re-keying | [Legacy record](../../dcos/ADR-019-host-owned-exact-language-code-in-i18n.md) | [Package Reference](../../I18N_PACKAGE_REFERENCE.md) | — | — |
| ADR-020 | Nullable translation type metadata contract | ACTIVE | Translation type validation, persistence, and consumer reads | [ADR-020](ADR-020-nullable-translation-type-metadata.md) | [Package Reference](../../I18N_PACKAGE_REFERENCE.md) | — | — |

ADR-018 and ADR-019 remain at their existing legacy paths and retain their original `ACCEPTED` header labels. Their current decisions are indexed as `ACTIVE`; this S1 change does not rewrite or relocate either record.
