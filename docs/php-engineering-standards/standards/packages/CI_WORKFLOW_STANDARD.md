# CI Workflow Standard

## Standard Metadata

- **Standard ID:** `std-ci-workflow`
- **Standard Version:** `3.0.0`
- **Standard Version Format:** `MAJOR.MINOR.PATCH`

This document outlines the standard CI workflow architecture for reusable Composer artifacts in the Maatify ecosystem. It applies to standalone Composer packages and to an extractable Base Module Artifact Root when the check or service verifies that artifact as a reusable Package. It ensures a consistent, high-quality testing and static analysis baseline without coupling repository-specific values to this Standard.

## 1. Normative Language

The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD", "SHOULD NOT", "RECOMMENDED", "MAY", and "OPTIONAL" in this document are to be interpreted as described in RFC 2119.

* **MUST / MUST NOT**: A mandatory baseline requirement.
* **SHOULD / SHOULD NOT**: The expected default; deviation requires an explicit documented reason.
* **MAY**: An optional capability or enhancement.

## 2. Canonical CI Architecture

This standard governs CI execution and enforcement. The testing architecture and regression protection requirements are exclusively governed by the [Testing Standard](../testing/TESTING_STANDARD.md).
Repositories with required system/E2E suites MUST execute them as CI gates appropriate to that repository. CI MUST make it impossible to treat a required failing E2E/system suite as successful verification.

CI Workflow applicability is resolved from the canonical package and module contracts. A Host repository root is not automatically a Base Module Artifact Root, and a Host-specific Project-Aware scope does not acquire Composer-package CI obligations merely through profile inheritance. The CI checks and services that verify an extractable Base Module MUST target its Artifact Root as the reusable Package boundary; Host-only checks remain governed by their applicable Host/project contract.

### 2.1 Local Quality-Gate Parity

For every applicable required quality gate in this Standard, the repository MUST document a local command or command sequence that runs the same verification contract. This includes Composer validation and dependency resolution, platform requirements, PHP syntax, PHPStan, code style, whitespace, applicable test suites, schema verification, Composer audit, workflow lint, and the Consumer Verification Harness.

Every CI gate MUST invoke a repository-owned command or script, or a documented command sequence, that a developer can run locally to prove the same verification contract. Runner-specific environment setup, dependency matrices, and job orchestration MAY differ, but MUST NOT change what the gate verifies. For an Integration boundary that needs a Database or other containerizable infrastructure, local and CI execution MUST consume the same repository-owned infrastructure contract whenever the CI environment can use Docker Compose reasonably; CI-native provisioning is allowed only under the equivalence rule in §11. Workflow YAML MAY coordinate those concerns; it MUST NOT be the only place where a maintainer can discover or understand the verification logic. A repository MUST document the prerequisites and service setup needed to run each applicable gate locally.

Local parity means that the gate is runnable locally with equivalent verification semantics; it does not require a developer workstation to reproduce the GitHub runner. This Standard does not require one universal command, Composer script name, or CI job name for every gate. However, when an Integration suite exists, `composer test:integration` is the canonical focused Composer entry defined by [COMPOSER_PACKAGE_STANDARD.md](COMPOSER_PACKAGE_STANDARD.md) §21; the internal orchestration command and CI mapping remain repository-specific. The repository MUST use its actual maintained commands and document the mapping from local invocation to CI invocation. GitHub CI remains the final evidence for the integrated runner environment.

### 2.2 Consumer Verification Harness Gate

The [Testing Standard](../testing/TESTING_STANDARD.md) owns Consumer Verification Harness applicability and evidence semantics. This Standard owns only its CI execution and enforcement.

When the Harness is required for an artifact, CI MUST execute the Harness defined by the Testing Standard as a required applicable gate using the repository-owned local invocation documented under §2.1. CI orchestration MUST preserve the clean-state and repeatability requirements in the Testing Standard, and the executed Harness MUST prove production autoload. For a Base Module, the Harness MUST consume the Module Artifact Root's Composer contract as the dependency; the Host root MUST NOT substitute for the Artifact Root, as defined by the [Composer Package Standard](COMPOSER_PACKAGE_STANDARD.md).

When a Consumer Verification Harness needs the same Database or service as Integration tests, it MUST reuse the same repository-owned Compose definition and lifecycle orchestration. The Harness MAY use a separate Compose project identity, credentials, schema, or disposable state for isolation, but MUST NOT introduce a competing service definition or lifecycle.

The Harness gate MUST fail closed. Missing or incomplete setup, unavailable required dependencies or services, and an unexpected skip MUST fail verification when the Harness is relevant. The gate MUST NOT be hidden behind `continue-on-error`, `|| true`, or a silent skip. Applicable real-service requirements continue to follow Section 11, and baseline CI MUST NOT require production secrets.

Repositories using change-relevance detection for an applicable Harness MUST configure it from that Harness's actual implementation. Changes to the consumer project, fixture, template, script, or other files that affect its verification MUST make the Harness relevant. This Standard does not define a universal Harness path list.

### 2.3 Affected Checks and Full Integration Gate

After a small remediation or micro-fix, CI MAY run the affected checks selected by risk instead of repeating an expensive full matrix, provided all checks directly related to the changed contract still run. Risk-based selection MUST NOT remove evidence for the behavior, compatibility, or integration boundary changed by the fix.

At a meaningful integration boundary, CI MUST run the full applicable verification set for the integrated change. Boundaries include closing a substantial Work Unit or Phase, moving an Integration Draft to its next stage when its changed contract is affected, final acceptance for the integration boundary, and changes that expand compatibility or integration behavior. The full set includes all applicable required quality, dependency, PHP-version, test, real-service, package, and workflow checks defined by this Standard.

Repositories MAY organize their workflows into multiple files (e.g., separating `quality`/`static analysis`, `tests`, `integration`, and `dependency compatibility`).
However, workflows MUST have:
* stable workflow names
* stable terminal gate job names
* clear ownership of each check
* no duplicate conflicting sources of truth

The standard does not mandate one exact number of workflow files.

### 2.4 Release-Qualified SHA

Release qualification MUST be tied to one exact commit SHA. The required sequence is:

```text
approved integration state
→ Owner merge to main
→ actual main commit SHA
→ Full Applicable CI on that same SHA
→ release-qualified commit
```

CI MUST record evidence for the actual `main` SHA it verifies and MUST NOT treat a successful run on an older SHA as qualification for a newer SHA. Any content commit intended to enter the same release candidate creates a new candidate SHA that requires the full applicable verification set. A later `main` commit outside that release does not invalidate an earlier qualified SHA automatically, but a later commit included in the same release cannot be ignored.

This Standard owns qualification and CI evidence only. Tag and Release consumption of the qualified SHA is owned by `LIBRARY_PRESENTATION_STANDARD.md`; CI MUST NOT create a second release-presentation contract.

## 3. Required-Check-Safe Path Scoping

A workflow or job used directly as a branch-protection required check **MUST always report a conclusion** for every protected pull request.
As a **Maatify Operational Policy** supported by GitHub's context evaluation constraints, repositories MUST rely on a stable aggregate gate rather than requiring individual matrix child jobs.
A directly required workflow MUST NOT disappear because of top-level path filtering.

### Allowed Models

* **Model A — Always run:** The required workflow runs on every protected pull request. This is valid for small or inexpensive package CI.
* **Model B — Always-triggered workflow with internal relevance detection:** The workflow always starts, then:
  1. a change-detection job determines which checks are relevant
  2. expensive jobs run conditionally
  3. a stable terminal gate job always runs
  4. the gate succeeds when no relevant work is required
  5. the gate fails when any relevant required job fails

Workflow-level `paths` filters MAY be used for workflows that are not configured as required branch-protection checks.
Repositories MUST NOT mark a top-level path-filtered workflow directly as a required check.
Branch protection MUST target stable aggregate/gate jobs, not unstable matrix child job names.

## 4. Path Relevance Rules

Every repository MUST derive relevant paths from its actual structure. Do not universally require paths that may not exist.

Examples of relevant paths:
* `src/**/*.php`
* `tests/**/*.php`
* `examples/**/*.php`
* `schema/**/*.sql`
* `tests/Fixtures/**/*.sql`
* `composer.json`
* `composer.lock`
* `phpstan.neon`
* Actual test-runner configuration files used by the repository (for example, `phpunit.xml` or `phpunit.xml.dist` when PHPUnit is used)
* `.php-cs-fixer.php`
* `.github/workflows/<workflow>.yml`

Rules:
* Include `composer.lock` ONLY when the repository tracks it.
* Never require a reusable library to add `composer.lock` solely for CI path filtering.
* Include the actual test-runner configuration files used by the repository when they affect test execution.
* Include package-owned schema and SQL fixture paths used by Integration tests.
* Include the workflow itself and all quality-tool configuration files that affect it.
* Documentation changes SHOULD trigger heavy checks only when the workflow validates documentation code blocks, generated files, or examples.
* Under the always-triggered gate model, irrelevant changes MAY skip heavy jobs, but the gate MUST still report success.

## 5. Composer Validation and Dependency Resolution

The canonical construction and package-level contract of `composer.json` is defined by [COMPOSER_PACKAGE_STANDARD.md](COMPOSER_PACKAGE_STANDARD.md). This section governs how that contract is verified in CI.

Composer validation MUST use:
```bash
composer validate --strict
```

Two dependency modes are defined:

### Repositories that track `composer.lock`
MUST use:
```bash
composer install --no-interaction --prefer-dist --no-progress
```
The lock file MUST NOT be rewritten during verification.

### Reusable libraries that do not track `composer.lock`
MUST use explicit dependency resolution, such as:
```bash
composer update --no-interaction --prefer-dist --no-progress
```
The CI process MAY generate a temporary local lock file, but it MUST NOT require committing it.

After dependency resolution, the pipeline MUST run:
```bash
composer check-platform-reqs
```
The flags `--ignore-platform-reqs` or `--ignore-platform-req` MUST NOT be used in required baseline CI.

### 5.1 Composer Capability for Required Dependency-Policy Gates

The required authoritative dependency-policy security gate MUST run with a supported Composer release containing the Composer 2.10 dependency-policy model, including:

* `config.policy`
* the advisories policy
* the malware policy
* the abandoned policy
* custom dependency policies
* the current applicable policy-enforcement controls

This is a CI/tooling capability requirement only. It MUST NOT become a consumer runtime minimum, a Composer package dependency, a `composer-runtime-api` requirement, or a package-install requirement imposed on consumers.

The Composer executable used by the authoritative gate MUST be selected and provisioned deterministically under the repository's existing CI tooling contract. This Standard does not prescribe a new installation architecture or an exact Composer patch version.

A repository MAY run a separate compatibility job with an older Composer release when that compatibility is an actual repository requirement. That job MUST NOT be the sole dependency-policy security gate and MUST NOT replace the authoritative gate using the current policy model.

## 6. Dependency Compatibility

Reusable libraries MUST validate both ends of their declared dependency constraints.

### Latest-compatible dependencies
A normal compatible dependency-resolution job MUST be executed.

### Lowest-supported dependencies
On the minimum supported PHP version, CI MUST run an appropriate resolution such as:
```bash
composer update --prefer-lowest --prefer-stable --no-interaction --prefer-dist --no-progress
```
Then run the relevant static and test checks.
Note that `composer update --prefer-lowest --prefer-stable` tests the lowest resolved bounds but does not guarantee compatibility with every version within the range. The lowest-dependency job MUST NOT silently pass through `continue-on-error`, `allow-failure`, or `|| true`. If a package cannot support its declared lower bounds, the dependency constraint MUST be corrected.

## 7. PHP Version Compatibility

The declared Composer PHP constraint is a public compatibility contract.

* The minimum supported PHP minor MUST be tested.
* The latest stable PHP minor permitted by the Composer constraint MUST be tested.
* Every currently released PHP minor covered by the declared Composer constraint MUST either:
  * be represented directly in CI, or
  * have an explicit documented architectural exception.
* The minimum and latest supported versions MUST NOT be exempted.
* Future unreleased PHP versions implied by an open-ended constraint are not considered supported CI targets until released.
* Versions outside the declared Composer constraint MUST NOT be included merely to make a matrix appear broader.
* Packages SHOULD test every active intermediate minor directly; any omission requires documentation.

Rules:
* Static analysis and syntax compatibility SHOULD run on the minimum supported PHP version.
* Unit and Regression suites MUST represent the minimum and latest supported versions.
* Packages owning database/service behavior MUST run Integration coverage on the minimum and latest supported PHP versions unless the package supports only one PHP minor.
* Matrix `fail-fast` SHOULD be disabled so all compatibility failures are visible.

## 8. Mandatory Baseline Quality Checks

The standard MUST require, where applicable:

* **Composer validate --strict**
* **Dependency resolution/install**
* **Composer platform requirement validation**
* **PHP syntax validation**: Syntax-check every package-owned PHP path participating in runtime, tests, examples, or PHP-based tool configuration. MUST NOT silently ignore failures.
* **PHPStan level max**:
  * MUST enforce zero errors.
  * Runtime and tests MUST be included when tests are part of the configured PHPStan paths.
  * MUST NOT use a baseline.
  * MUST NOT use `ignoreErrors`.
  * MUST NOT use inline suppressions merely to make CI pass.
* **Code style**: When a supported formatter configuration (e.g., `.php-cs-fixer.php`) exists, CI MUST run a non-mutating check (e.g., `vendor/bin/php-cs-fixer fix --dry-run --diff`). CI MUST NEVER rewrite and commit formatting automatically during a required verification job.
* **Whitespace verification**: CI MUST detect and fail on applicable whitespace defects such as trailing whitespace, malformed whitespace introduced in tracked text/source files, or equivalent repository-specific whitespace integrity failures. A canonical Git-aware verification such as `git diff --check` MAY be documented as an accepted/basic mechanism where appropriate, provided it works correctly for the actual comparison context. This must be treated as a real required quality check, separate from generic code-style formatting.
* **Complete maintained applicable test suite**: CI MUST run the complete maintained test suite using the repository's actual test runner and tooling. Where separate Unit, Regression, and Integration suites exist, each MUST run explicitly. A runner failure MUST fail CI deterministically; a missing required runner, configuration, dependency, or setup MUST fail closed.
* **PHP example syntax and static validation**: Every PHP example MUST pass syntax validation and the applicable static validation.
* **Standalone example smoke execution**: Every standalone runnable example MUST execute successfully using the repository's production autoload and maintained runtime path.
* **Database/service examples**: A standalone example that depends on a database or service MUST use the repository-owned Integration infrastructure when one exists. CI MUST NOT create a new Docker Compose contract merely to validate an example.
* **Host-dependent examples**: An example MAY be exempted from smoke execution only when it is demonstrably non-standalone, has explicit prerequisites and boundaries, and still passes syntax/static validation.
* **Composer security audit**
* **Workflow syntax/lint validation**

## 9. Composer Security Audit

CI MUST perform a non-interactive dependency-policy audit after successful dependency resolution. The authoritative gate MUST satisfy §5.1. `composer audit` checks dependency policies, including advisories, malware, abandoned packages, and configured custom policies. Required verification MUST fail on security advisories, malware findings, abandoned packages under the Maatify release/audit policy unless an applicable approved migration decision permits a scoped ignore, and findings from a configured custom dependency policy when that policy is part of required enforcement.

CI MUST NOT rely on the mere presence of `composer audit` when the effective policy has been weakened. The audit and the surrounding Composer invocation MUST preserve the fail-closed contract in [`COMPOSER_PACKAGE_STANDARD.md`](COMPOSER_PACKAGE_STANDARD.md) §23.5. Therefore, CI MAY use an explicit enforcement command such as:
```bash
composer audit --no-interaction --abandoned=fail
```
but the command succeeds as required only when the effective advisories, malware, abandoned, and configured custom-policy audit configuration is not reduced to a permissive state. An equivalent explicit enforcement configuration MUST provide the same semantics.

Required dependency/security gates MUST remain non-interactive and MUST NOT use or permit an override that weakens the required policy, including when applicable:

* `COMPOSER_POLICY=0`
* `COMPOSER_NO_BLOCKING=1`
* `--no-blocking`
* deprecated `--no-security-blocking`
* `COMPOSER_POLICY_ADVISORIES_BLOCK=0`
* `COMPOSER_POLICY_MALWARE_BLOCK=0`
* `COMPOSER_NO_AUDIT=1` as a way to cancel required verification
* `COMPOSER_AUDIT_ABANDONED` or `--abandoned` values that reduce abandoned auditing to `ignore` or `report`
* `--ignore-severity` to bypass required advisory verification

`COMPOSER_POLICY_ABANDONED_BLOCK=0` MUST NOT be prohibited solely because its value is `0`: this Standard does not introduce abandoned resolution blocking as a new requirement. Abandoned audit MUST still fail by default, and an abandoned override MUST NOT change the required audit result to `ignore` or `report`.

Security and dependency-policy failures MUST NOT be made non-blocking through `continue-on-error` or shell fallbacks.

## 10. Workflow Syntax Validation

Once a repository contains GitHub Actions workflows, CI MUST validate workflow syntax and semantics using a dedicated tool such as `actionlint` or an equivalent maintained validator.

* Workflow lint MUST be non-mutating.
* Invalid expressions, malformed YAML, invalid event structures, and invalid job dependencies MUST fail CI.
* The validator installation or action MUST be version-pinned.
* Workflow lint MUST include every file under `.github/workflows/`.

## 11. Integration-Test Rules

Packages that own persistence or external-service behavior MUST use the real supported service in Integration CI.

For an Integration suite that needs a Database or other containerizable infrastructure, Docker Compose MUST be the canonical repository-owned local provisioning mechanism. Compose provisions the real service; it MUST NOT require the PHP test runner itself to run inside Docker. The PHP runner remains on the repository or CI PHP runtime by default so PHP compatibility matrices remain truthful; containerizing the runner requires an independently documented reason. Docker/Compose provisioning and Integration orchestration are verification-time dependencies only; they MUST NOT become consumer runtime dependencies of the Package or Base Module.

Integration environment variables MUST be clearly Integration-scoped and non-production. Their names remain repository-specific and MUST be discoverable from the canonical orchestration and its documentation; they MUST NOT reuse production credentials or Host application database/service configuration.

The canonical Integration orchestration MUST own or invoke a deterministic lifecycle equivalent to:

```text
validate prerequisites
→ start required services
→ wait for health/readiness
→ prepare fresh schema/state
→ run the Integration suite
→ cleanup and repeatability verification
→ teardown disposable infrastructure
```

Teardown MUST run on both success and failure. Diagnostic logs MAY be captured before teardown. A running container is not proof that its service is ready: readiness MUST use a health check or another deterministic service-level probe, and arbitrary `sleep` MUST NOT be used as the readiness proof. Missing prerequisites, service startup, readiness, schema/setup, or required runner configuration MUST fail clearly and MUST NOT become a silent skip.

The Compose contract MUST be collision-safe and isolated. It MUST NOT use a fixed global `container_name`; the orchestration MUST use a Compose project identity for each repository/run. When a host PHP runner needs a published port, it MUST bind to loopback and SHOULD use a dynamic host port where practical, then discover the effective endpoint. A port MUST NOT be published merely for a runner on the same Compose network. Integration infrastructure MUST NOT use production, Host application, developer-owned persistent, or unrelated-project databases, volumes, credentials, or containers.

Every independent run MUST start from fresh disposable state. Schema and installation assets MUST be applied to that fresh state, and teardown MUST remove disposable volumes/state (for example, `docker compose down -v` or an equivalent). Resetting state inside a run does not replace clean-run proof. Credentials MUST be temporary, non-production, and scoped to the run.

Local Integration, CI Integration, and any Consumer Verification Harness using the same service MUST consume the same repository-owned Compose definition and lifecycle contract. A CI-native service definition is an exception only when Docker Compose is not reasonably usable in the actual CI environment; the repository MUST then document and prove equivalence for engine/version, readiness, credentials/isolation, schema initialization, observable Integration behavior, and cleanup/repeatability. CI-native provisioning MUST NOT be used merely for convenience.

The internal orchestration command or path, raw test runner, Compose project identity, service image, and Integration environment-variable names are repository-specific implementation details, not additional public Composer entry points. The canonical Composer entry remains the discoverable Integration boundary.

When an artifact supports multiple service or Database versions, it MUST use one Compose definition with an explicit image/version parameter. Local execution MUST have a pinned default, and a CI matrix MAY vary that parameter across supported versions. Copied Compose files for each version are not permitted. Unit and Regression suites that do not need real infrastructure MUST remain runnable without Docker.

* The central standard does not enforce a unified Database engine or Database version across all projects.
* Each package or project defines its actual database contract from its authoritative sources and current schema.
* CI verification MUST use the actual engine owned by the project.
* Pinning a CI service image to an explicit version is for reproducibility, and does not make that version the central compatibility contract of the project.
* SQLite substitution is FORBIDDEN for MySQL/MariaDB-owned behavior when it cannot prove the same contract.
* In-memory or mocked substitutes are FORBIDDEN when they cannot prove the real storage contract.
* Service images MUST use explicit supported versions or immutable digests, never `latest`.
* Services MUST have health checks or deterministic readiness checks.
* CI credentials MUST be temporary, non-production, and local to the runner.
* Baseline CI MUST NOT require repository secrets.
* Integration tests MUST NOT access host application databases, schemas, services, or credentials.
* Integration tests MUST clean up package-created tables, triggers, temporary records, locks, and transactions where applicable.
* Tests that create or remove database objects MUST prove cleanup and repeatability.
* No transaction MAY leak between tests.
* No package-owned schema object MAY remain unexpectedly after the suite.
* If tests mutate schema-level objects or depend heavily on cleanup, CI MUST include either:
  * a repeated Integration run, or
  * an equivalent explicit repeatability and residue check.
* Service containers MUST represent only package-owned runtime dependencies.

## 12. Workflow Security and Supply-Chain Hardening

As a **Maatify Internal Policy**, workflows MUST require least privilege:
```yaml
permissions:
  contents: read
```
unless an additional permission is explicitly required and documented; only the minimum necessary permission may be granted. Note that this is not a GitHub default, but a strict Maatify operational security policy.
`contents: read` is the normal verification baseline. Workflows may use a narrower permission set when possible. Any broader/additional permission requires explicit justification.

Rules:
* Baseline verification workflows MUST NOT receive write permissions.
* MUST NOT use `pull_request_target` to execute untrusted pull-request code.
* MUST NOT expose repository or environment secrets to untrusted fork code.
* As a **Maatify Internal Policy**, every externally sourced GitHub Action MUST be pinned to an immutable full commit SHA. This includes GitHub-owned actions such as actions under the `actions/*` organization.
* As a **Maatify Internal Policy**, every external reusable workflow MUST be pinned to an immutable full commit SHA.
* Human-readable comments MAY document the corresponding release/tag.
* Floating branches and tags such as `main`, `master`, `latest`, `v1`, `v2`, or other mutable references MUST NOT be used in required workflows.
* Local actions stored within the same repository are resolved from the checked-out repository commit and are not required to use an external SHA reference.
* Any downloaded CI binary or validator must use a version/checksum or another integrity-verifiable installation policy documented by the repository.
* Container/service images MUST use explicit versions, and digests SHOULD be used for high-assurance environments.
* MUST NOT upload credentials, database dumps, `.env` files, or sensitive logs as artifacts.

## 13. Execution Reliability

* As a **Maatify Internal Policy**, every required job MUST define an appropriate `timeout-minutes`.
* As a **Maatify Internal Policy**, every required workflow architecture MUST define an explicit `concurrency` and `cancel-in-progress` policy.
* `concurrency` MAY be configured at workflow level or job level according to the architecture.
* Job-level concurrency groups MUST be scoped so matrix siblings and independent required jobs do not cancel one another.
* Pull-request superseded runs SHOULD be cancelled.
* Default/protected-branch runs MUST NOT be cancelled in a way that hides the newest merged verification result.
* `cancel-in-progress` must be applied according to event/ref context, not blindly to every run.
* Required jobs must remain fail-closed.

Rules:
* Matrix jobs SHOULD use `fail-fast: false`.
* Required jobs MUST NOT use `continue-on-error`.
* Required commands MUST NOT use `|| true`.
* Shell scripts SHOULD use strict failure behavior such as `set -euo pipefail` where supported.
* Missing dependencies, services, privileges, or extensions MUST fail clearly.
* Tests MUST NOT be silently skipped because CI setup is incomplete.

## 14. Cache Policy

Caching is OPTIONAL. When enabled:
* Cache Composer download files, not the entire `vendor/` directory.
* Cache keys MUST include relevant inputs such as operating system, PHP version, Composer dependency files, and dependency mode.
* Stale cache MUST NOT determine correctness.
* A clean installation MUST remain possible.
* Cache restore failure MUST NOT conceal dependency installation failures.

Caching MUST NOT be part of the functional contract.

## 15. Coverage Policy

Coverage collection MAY be enabled. A coverage gate becomes mandatory only when the package has an explicitly approved threshold. Once adopted, reducing the threshold requires an explicit architectural decision and documented justification.
Generated coverage artifacts MUST NOT contain sensitive data.
Coverage MUST NOT replace behavior-focused Unit, Regression, or Integration tests.

## 16. Stable Gate Jobs and Branch Protection

Every workflow used by branch protection MUST expose a stable final gate job.
The final gate MUST:
* use `if: always()` or equivalent behavior where necessary
* inspect every required upstream job
* fail when a relevant upstream job fails or is cancelled unexpectedly
* succeed when non-relevant heavy jobs were intentionally skipped by the approved relevance detector
* have a stable name that does not contain variable matrix values
* be the check selected in branch protection

As a **Maatify Internal Policy**, repositories MUST NOT require individual matrix child jobs directly; a stable aggregate gate MUST be used instead to avoid matrix ambiguity.
The gate MUST inspect `needs.*.result` which returns one of `success`, `failure`, `cancelled`, or `skipped`. The `skipped` state is only acceptable when an approved relevance detector proves the job was intentionally bypassed; any unexpected `skipped` state for a relevant job MUST fail the gate. The gate MUST NOT hide `failure` or `cancelled` or equivalent unsuccessful states.
When a Consumer Verification Harness is required and relevant, its job MUST be included among the upstream requirements inspected by the stable aggregate gate. A skipped Harness job is acceptable only when the approved relevance detector proves it is not relevant.

## 17. Trigger Events

Baseline verification MUST trigger on:
* `pull_request` targeting the protected/default branch
* `push` to the protected/default branch

`workflow_dispatch` MAY be added for manual verification.
Scheduled dependency-drift verification MAY be added for reusable libraries.

## 18. Generality and Package-Specific Configuration

This standard distinguishes between universal rules and repository-specific values.

* **Universal rules**: PHPStan max, real Integration services, minimum/latest PHP coverage, stable required gates, Composer validation, no hidden failures, least privilege.
* **Repository-specific values**: exact PHP versions, exact service versions, actual test-runner configuration files, actual suite names, schema paths, environment variable names, service ports, package-owned trigger/table names, the canonical Compose definition and orchestration, local Compose project/endpoint behavior, whether `composer.lock` is tracked, and any CI-native equivalence evidence.

Repository-specific values MUST be documented by each applicable artifact, but MUST NOT be hardcoded into the universal standard. The repository documentation MUST make the canonical Integration entry and the Local/CI/Harness infrastructure contract discoverable without requiring a separate manual service-start procedure.

## 19. Compliance Checklist

Any applicable reusable Composer artifact in the Maatify ecosystem MUST verify the following before being considered compliant. For an extractable Base Module, the checklist applies at the Module Artifact Root when the check evaluates that artifact as a reusable Package; it does not automatically apply to the Host root or a Host-only Project-Aware scope:

* [ ] Composer validation is strict
* [ ] dependency resolution strategy matches lock-file policy
* [ ] current compatible dependencies pass
* [ ] lowest supported dependencies pass
* [ ] platform requirements pass
* [ ] Required dependency-policy security gate uses a supported Composer release containing the Composer 2.10 dependency-policy model; any older compatibility job does not replace it
* [ ] Required dependency-policy audit passes under the effective fail-closed configuration
* [ ] PHP syntax passes
* [ ] PHPStan max passes with zero suppressions
* [ ] code-style dry-run passes when configured
* [ ] explicit whitespace verification passes
* [ ] Unit suite passes where applicable
* [ ] Regression suite passes where applicable
* [ ] Integration suite uses real services where applicable
* [ ] `composer test:integration` is the focused canonical Integration entry where an Integration suite exists
* [ ] Database/containerizable Integration infrastructure uses one repository-owned Docker Compose definition and deterministic lifecycle
* [ ] the PHP test runner is not forced into Docker by the Integration infrastructure
* [ ] Docker/Compose and Integration orchestration are not consumer runtime dependencies
* [ ] readiness is service-level and deterministic, not an arbitrary `sleep`
* [ ] teardown runs on success and failure, and independent runs use fresh disposable state
* [ ] no production, Host, developer-persistent, or unrelated-project service state is used
* [ ] Integration environment variables are clearly Integration-scoped and non-production, with repository-specific names
* [ ] no fixed global `container_name` is used and any published endpoint is collision-safe
* [ ] Local, CI, and applicable Consumer Verification Harness execution reuse the same infrastructure contract, or a documented CI-native equivalent exception proves parity
* [ ] service-version matrices parameterize one Compose definition rather than copying definitions
* [ ] Unit and Regression suites that do not need real infrastructure remain runnable without Docker
* [ ] complete maintained applicable test suite passes using the repository's actual test runner and tooling
* [ ] every PHP example passes syntax/static validation where examples exist
* [ ] every standalone runnable example passes smoke execution, and standalone database/service examples use the repository-owned Integration infrastructure where available
* [ ] any Host-dependent example excluded from smoke execution has explicit prerequisites and remains syntax/static validated
* [ ] minimum supported PHP is tested
* [ ] latest supported PHP is tested
* [ ] every currently released PHP minor covered by the declared constraint is tested or has an explicit documented architectural exception
* [ ] workflow files pass linting when workflows exist
* [ ] every externally sourced GitHub Action and external reusable workflow is pinned to an immutable full commit SHA
* [ ] downloaded CI tools and validators use a documented integrity-verifiable installation policy where downloaded tools exist
* [ ] permissions follow least privilege
* [ ] no baseline secrets or Host dependencies exist
* [ ] required gates always report
* [ ] branch protection requires stable gates only
* [ ] no continue-on-error or hidden failures exist
* [ ] package-created service/database state is cleaned up where Integration tests create such state
* [ ] every applicable required gate has a documented local invocation with the same verification contract as its CI invocation
* [ ] a required Consumer Verification Harness runs as a fail-closed gate, preserves clean-state repeatability, and is included in the stable aggregate gate when relevant
* [ ] meaningful integration boundaries run the full applicable verification set, while reduced runs still include every check tied directly to a changed contract
