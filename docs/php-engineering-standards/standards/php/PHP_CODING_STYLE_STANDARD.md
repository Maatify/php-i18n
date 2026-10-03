# PHP Coding Style Standard

## Standard Metadata

- **Standard ID:** `std-php-coding-style`
- **Standard Version:** `1.0.1`
- **Standard Version Format:** `MAJOR.MINOR.PATCH`

## 1. Purpose and Canonical Ownership

This Standard is the sole canonical owner of the following contract:

```text
Repository-Owned PHP Source Coding Style / Formatting Contract
```

It does not own or redefine:

- architecture;
- source placement;
- module or package structure;
- namespace architecture beyond requirements imposed directly by the external coding-style baseline;
- DocBlock coverage or DocBlock requirements;
- documentation language;
- API or BFF contracts;
- runtime behavior;
- testing architecture; or
- CI orchestration.

Other Standards remain the canonical owners of those concerns. This Standard does not collect their rules as local coding-style preferences.

## 2. External Normative Baseline

The exact external normative baseline for this Standard is:

```text
PHP-FIG PER Coding Style 3.1
```

The adopted PHP-FIG coding-style version is specifically `3.1`. The selector `latest`, any floating PHP-FIG coding-style reference, and any unpinned future version MUST NOT be used as the normative baseline. Moving to another PER Coding Style version requires a separate reviewed Standards change.

According to PHP-FIG, PER Coding Style 3.1 extends and replaces PSR-12 and builds on PSR-1. This Standard references that baseline; it does not reproduce its detailed rules. PSR-12 alone is not an equivalent baseline for this contract.

## 3. Applicability

This Standard applies to repository-owned, manually maintained PHP code within the scope that passes the canonical applicability evaluation owned by this Standard.

When present, the applicable scope includes:

- runtime PHP source;
- tests;
- PHP CLI scripts;
- examples;
- bootstrap and configuration PHP files;
- PHP-based tooling and configuration files; and
- maintained PHP portions within mixed PHP files.

This Standard does not apply to:

- `vendor/`;
- third-party source not owned by the repository; or
- generated PHP code that is not manually maintained.

The presence of this Standard in a Profile produces only a Candidate Standard Reference during structural/transitive resolution. Final applicability is determined at Stage 2 by this Standard against the actual activation scope and artifact facts; Profile composition does not override that evaluation.

## 4. Normative Contract

1. Every applicable PHP source file **MUST conform to PHP-FIG PER Coding Style 3.1**.
2. Any formatter, linter, or configuration used to check that conformance is an implementation mechanism only. This Standard remains the canonical source of truth.
3. A tool preset or configuration used for verification MUST pin or otherwise explicitly establish the same `PHP-FIG PER Coding Style 3.1` baseline. It MUST NOT introduce conflicting rules and present them as a replacement for that baseline.
4. Additional local rules are not part of this Standard unless they are an independent coding-style concern with an appropriate canonical owner. This Standard MUST NOT be used to collect architecture, documentation, or project-preference rules unrelated to the coding-style contract.
5. Adoption of this Standard does not require automatic formatting or automatic remediation for existing consumers. Existing violations are Findings to be addressed by independent Work Units.
6. PER Coding Style 3.1 does not establish a requirement for a DocBlock above every class, method, or function. Documentation requirements remain owned by the applicable documentation Standards.

## 5. Mechanical Verification

Every applicable repository or scope MUST provide a deterministic, repository-owned, non-mutating verification command and configuration capable of demonstrating coding-style compliance with `PHP-FIG PER Coding Style 3.1`.

The contract does not prescribe a particular formatter or linter. A suitable tool MAY be used when:

- verification is non-mutating when used as a quality gate;
- its configuration is version-controlled;
- its result is deterministic; and
- its configuration does not replace the normative baseline stated in this Standard.

### Composite Verification Coverage

When verification is composed from a base ruleset, local overrides, and one or more supplemental verifiers, the combined verification surface MUST demonstrate the complete normative baseline.

Disabling, overriding, or weakening a rule inherited from the selected base ruleset MUST be treated as removing verification coverage for every normative obligation represented by that rule.

Such an override is valid only when every displaced normative obligation remains mechanically verified elsewhere in the repository-owned verification surface without conflict or coverage gap.

A successful tool exit status does not establish compliance when the configured verification surface omits part of the normative baseline.

When a tool limitation or preset behavior conflicts with the canonical coding-style contract, this Standard remains authoritative. The repository MUST resolve the source shape, tool configuration, or verifier composition without silently weakening normative coverage.

When independently applicable, `CI_WORKFLOW_STANDARD.md` remains the sole canonical owner of CI execution and enforcement. Referencing it by canonical filename does not create a Required Standard, Candidate dependency, or pinned-file requirement, and does not expand Project Host composition. This Standard does not define CI orchestration or duplicate CI workflow rules.

## 6. Versioning and Change Control

The Standard ID and Standard Version are governed by the central `STANDARD_VERSIONING_POLICY_AR.md`. This central governance policy is referenced by canonical filename only; it is not a dependency of this Standard's consumer Adoption Set, does not create a Required Standard or pinned-file requirement, and need not be copied into a consumer solely because this Standard is adopted. The initial `1.0.0` version was the owner-approved bootstrap version for this newly established canonical owner.
