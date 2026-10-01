# Standards Manifest

## Adoption source

- **Upstream Repository:** Maatify/php-engineering-standards (https://github.com/Maatify/php-engineering-standards)
- **Adoption Commit:** 5f872d3ef7da847cba3f82fee124a19c22f1c5c4
- **Adoption Date / Metadata:** 2026-10-01 (upstream commit date); تعميم نموذج Release Preparation وفصل Publication State (#129)
- **Floating upstream reference used:** No

## Pinned Adoption Control Set

All files below are copied from the exact Adoption Commit.

- std-standards-adoption @ 4.0.0 — docs/php-engineering-standards/standards/STANDARDS_ADOPTION_STANDARD_AR.md
- Active Profile composer-package @ 3.0.0 — docs/php-engineering-standards/standards/profiles/COMPOSER_PACKAGE_PROFILE.md
- Active Profile repository-governance @ 3.0.0 — docs/php-engineering-standards/standards/profiles/REPOSITORY_GOVERNANCE_PROFILE.md
- Inherited Profiles required: None (Extends: None for both active Profiles)
- Unused Profiles copied: None

## Active Profile Activations

### composer-package

- **Profile Version:** 3.0.0
- **Scope:** /
- **Resolution Status:** VALID
- **Exception State:** NONE

### repository-governance

- **Profile Version:** 3.0.0
- **Scope:** /
- **Resolution Status:** VALID
- **Exception State:** NONE

## Resolved Applicable Standards Set

- std-package-building @ 3.0.2 — docs/php-engineering-standards/standards/packages/PACKAGE_BUILDING_STANDARD.md
- std-composer-package @ 4.0.0 — docs/php-engineering-standards/standards/packages/COMPOSER_PACKAGE_STANDARD.md
- std-ci-workflow @ 3.0.0 — docs/php-engineering-standards/standards/packages/CI_WORKFLOW_STANDARD.md
- std-library-presentation @ 4.0.0 — docs/php-engineering-standards/standards/packages/LIBRARY_PRESENTATION_STANDARD.md
- std-testing @ 1.1.1 — docs/php-engineering-standards/standards/testing/TESTING_STANDARD.md
- std-documentation-lifecycle @ 3.0.0 — docs/php-engineering-standards/standards/governance/DOCUMENTATION_LIFECYCLE_STANDARD_AR.md
- std-php-source-documentation @ 1.0.0 — docs/php-engineering-standards/standards/php/PHP_SOURCE_DOCUMENTATION_STANDARD.md
- std-php-coding-style @ 1.0.1 — docs/php-engineering-standards/standards/php/PHP_CODING_STYLE_STANDARD.md
- std-ai-collaboration-workflow @ 9.0.0 — docs/php-engineering-standards/standards/ai/AI_COLLABORATION_WORKFLOW_AR.md
- std-github-phase-stack-workflow @ 4.0.0 — docs/php-engineering-standards/standards/GITHUB_PHASE_STACK_WORKFLOW_AR.md
- std-decision-governance @ 1.0.0 — docs/php-engineering-standards/standards/governance/DECISION_GOVERNANCE_STANDARD_AR.md

## Explicit Inputs and Exceptions

- **Explicit Additional Standards:** None
- **Explicit Exceptions/Overrides:** None

## Frozen Profile Version Baseline

The prior Lead-verified evidence identifies a completed VALID adoption for Maatify/php-rate-limiter at commit f9048d9d75395244fa4af26b55e6b85ff0a898c6. This execution rechecked both Profile artifacts byte-for-byte against the same paths at this Adoption Commit:

- standards/profiles/COMPOSER_PACKAGE_PROFILE.md — match
- standards/profiles/REPOSITORY_GOVERNANCE_PROFILE.md — match

The frozen baseline for composer-package@3.0.0 and repository-governance@3.0.0 is confirmed; no frozen-version/content mismatch was found.

## Artifact Facts Used for Applicability

- This repository is a standalone reusable PHP/Composer package with Composer identity maatify/php-i18n (composer.json).
- The package owns SQL persistence behavior: PDO/MySQL repositories, schema/schema.i18n.sql, transaction and concurrency behavior, and real-MySQL integration tests.
- The repository contains maintained PHP source, tests, documentation, and package verification/configuration surfaces. It currently has no .github/ directory or standalone GitHub CI workflows.
- The repository is package-only; it is not a deployable Host/Application or a Module scope, and it is not project-aware slim.

## Resolution and Closure

- **Structural / Transitive Resolution:** PASS — both Profiles exist, have required metadata, declare Extends: None, and their 11 unique Required Standard references exist at the exact Adoption Commit. No inheritance cycle or Explicit Additional Standard is present.
- **Canonical Applicability Resolution:** PASS — all 11 candidates apply to the / package and repository-governance scopes, including the active SQL persistence conditions.
- **Local Reference Closure:** PASS — relative Markdown references in the Control Set and final standards resolve within the Control Set or this Resolved Applicable Standards Set.
- **Overall Resolution Status:** VALID
