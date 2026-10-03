# PHP Source Documentation Standard

## Standard Metadata

- **Standard ID:** `std-php-source-documentation`
- **Standard Version:** `1.0.0`
- **Standard Version Format:** `MAJOR.MINOR.PATCH`
- **Version Status:** `OWNER-APPROVED BOOTSTRAP VERSION`

## 1. Purpose and Canonical Ownership

This Standard is the sole canonical owner of the following contract:

```text
General PHP Source-Code Documentation Coverage and Quality Contract
```

Its purpose is to make repository-owned PHP source understandable from its documentation, signature, and necessary surrounding context without requiring a reader to reverse-engineer the implementation or trace unrelated files to discover the meaningful contract.

This Standard owns general source-documentation coverage and quality. It does not own:

- the language of PHP Source-Code Documentation;
- HTTP, API, or BFF endpoint integration documentation;
- architecture or source placement;
- module or package structure;
- runtime architecture or behavior;
- PHP coding style or formatting;
- project documentation; or
- CI orchestration.

The language of PHP Source-Code Documentation remains owned by `DOCUMENTATION_LIFECYCLE_STANDARD_AR.md`, including its current English-by-default contract. HTTP/API/BFF endpoint integration documentation remains owned by `HTTP_API_ENDPOINT_DOCUMENTATION_STANDARD.md`. PHP formatting remains owned by `PHP_CODING_STYLE_STANDARD.md`. These names are ownership references only; this Standard does not create a dependency on those files.

## 2. Governing Principles

The governing principles are:

```text
Documentation coverage is mandatory where a PHP source artifact
owns or exposes a meaningful contract.

Documentation depth is proportional to contract complexity.

Irrelevant or implementation-restating boilerplate is prohibited.
```

Documentation describes the source contract and meaningful semantics. It does not narrate the implementation line by line.

Documentation MUST reflect actual observed source behavior. It MUST NOT invent behavior, guarantees, exceptions, side effects, or supported use cases that do not exist at runtime. It MUST NOT describe intended future behavior as current runtime behavior.

A source artifact is non-compliant when its documentation is stale, materially misleading, or materially incomplete for a meaningful contract that the artifact owns or exposes.

## 3. Applicability

This Standard applies to repository-owned, manually maintained PHP source within the applicable scope. Depending on the artifacts present and their contracts, the scope includes:

- classes;
- interfaces;
- traits;
- enums;
- constructors;
- methods;
- functions;
- properties;
- constants;
- meaningful source-level data shapes and contracts; and
- manually maintained PHP portions of mixed files.

This Standard does not apply to:

- `vendor/`;
- third-party source not owned by the repository; or
- generated PHP source that is not manually maintained.

The presence of this Standard in a Profile identifies a candidate documentation contract. It does not make every possible source artifact subject to identical documentation depth, and it does not override a more specialized applicable Standard.

## 4. Class, Interface, Trait, and Enum Documentation

When a class, interface, trait, or enum owns a meaningful responsibility or contract, it MUST have an adjacent Source DocBlock sufficient to understand the artifact's contract without inspecting its implementation for the basic semantics.

The documentation MUST cover the following when they apply:

- purpose;
- primary responsibility;
- role or boundary;
- intended usage;
- meaningful invariants or guarantees;
- important collaboration or context needed to understand the contract;
- important lifecycle or state semantics; and
- relevant limitations or assumptions.

No fixed headings or fixed ordering is required. An item is not required when it does not apply. A usage example MAY be included when it adds actual understanding; examples are not required merely to populate a template.

The requirement is based on meaningful responsibility or contract. Trivial artifacts whose name, declaration, and surrounding context fully communicate their role do not require a long or artificial DocBlock.

## 5. Method and Function Documentation

Public and protected methods and functions that own or expose a meaningful contract MUST have an adjacent Source DocBlock sufficient to understand their behavior contract without reading the implementation to discover its basic semantics.

Whenever this Standard requires documentation for a method, function, or constructor, the required documentation MUST be an adjacent Source DocBlock. Implementation comments MAY exist when needed to explain implementation details, but inline or block implementation comments MUST NOT substitute for a required Source DocBlock.

The documentation MUST cover the following when they apply:

- what the operation does;
- the semantic meaning of inputs;
- meaningful constraints;
- requiredness or optionality that is not obvious from the signature;
- defaults;
- omitted, `null`, or empty-value behavior when it changes semantics;
- return semantics and returned data shape when needed;
- state mutation;
- persistence or other externally observable side effects;
- important preconditions and postconditions;
- meaningful exceptions and failure semantics;
- fallback behavior;
- idempotency where relevant;
- non-obvious edge cases; and
- invariants or guarantees.

A type declaration does not by itself document semantic meaning. It is not necessary to repeat a type that is already clear from the signature unless the documentation adds contract information. For example, a positive-ID constraint, a one-based value, an omitted value that preserves state, an empty list with special meaning, persistence, or a domain-specific exception requires semantic documentation even when the type declaration is explicit.

Constructors follow the same rule when their initialization contract, parameter meaning, constraints, or defaults are not obvious from the signature and context. Trivial or self-evident methods and functions with no meaningful additional contract remain exempt from an artificial DocBlock.

## 6. Private and Internal Documentation

Private and internal methods are not required to carry a DocBlock merely because they exist. When this Standard requires documentation for one, it MUST be an adjacent Source DocBlock. Implementation comments MAY explain implementation details, but inline or block implementation comments MUST NOT substitute for that required Source DocBlock. Private and internal methods MUST be documented when they contain non-obvious information that a maintainer needs to understand, such as:

- algorithm semantics;
- an invariant;
- a meaningful data shape;
- a side effect;
- a transformation contract;
- fallback behavior; or
- a non-obvious assumption.

Trivial or self-evident helpers MUST NOT be burdened with implementation-restating boilerplate.

## 7. Properties, Constants, and Source-Level Data Shapes

Properties and constants MUST be documented when their name, type, or value alone does not communicate their meaningful semantics, invariant, unit, constraint, or ownership. Comments that merely repeat the name, type, or literal value add no required coverage.

Meaningful source-level data shapes and contracts MUST be documented when their structure, field semantics, requiredness, defaults, special empty or null behavior, or invariants are not otherwise clear from the declaration and necessary context.

## 8. PHPDoc Tags and Documentation Form

Required semantic documentation coverage under this Standard is MANDATORY when applicable. Specific `@param`, `@return`, `@throws`, `@see`, examples, headings, and other PHPDoc constructs are OPTIONAL representation mechanisms; they are not a fixed or mandatory template unless a specialized applicable contract explicitly requires a particular form. The required semantics MAY be expressed through clear DocBlock prose, appropriate PHPDoc tags, or both. When a tag or other construct is used, it MUST carry real contract information. Constructs that add nothing beyond repeating an obvious type, signature, or declaration SHOULD be avoided.

PHP Source-Code Documentation MUST use plain professional technical prose. Decorative emoji or icons such as `📦`, `🎯`, `🧠`, `✅`, `⚙️`, `🔁`, or `🧩` MUST NOT be used as a documentation convention.

This Standard does not require a fixed long template, a DocBlock above every method, a line-by-line explanation, or documentation that repeats self-evident implementation details.

## 9. Specialized Documentation Contracts

General source-documentation allowances MUST NOT waive, reduce, replace, or weaken a specialized documentation contract.

When `HTTP_API_ENDPOINT_DOCUMENTATION_STANDARD.md` applies to a method, all Local Endpoint Integration DocBlock requirements defined by that Standard remain mandatory in full. That Standard is the sole canonical owner of the specialized endpoint documentation contract. This general Standard does not redefine or summarize those requirements.

One adjacent DocBlock MAY satisfy multiple applicable documentation Standards only when it fully satisfies every applicable contract. Duplicate DocBlocks are not required. A controller class-level DocBlock does not replace the Local Endpoint Integration DocBlock required beside an applicable endpoint method.

## 10. Consistency and Synchronization

The reader MUST be able to understand the meaningful contract from the documentation, signature, and necessary context without reverse-engineering the method or artifact body for basic semantics. This does not require exposing private implementation details that are not necessary to understand the contract.

When a meaningful documented contract changes, the documentation MUST be updated in the same change. This includes changes to semantics, constraints, defaults, null or empty behavior, state mutation, persistence, externally observable side effects, failure behavior, fallback behavior, idempotency, returned shape, or relevant invariants.

Documentation is not made compliant by describing an intended contract that the implementation does not provide. If the implementation and documentation disagree, the source behavior must be resolved and the documentation synchronized before the artifact is considered compliant.

## 11. Versioning and Change Control

The Standard ID and Standard Version are governed by `STANDARD_VERSIONING_POLICY_AR.md`. The reference is by canonical filename only; it is not a dependency of a consumer Adoption Set, does not create a Required Standard or pinned-file requirement, and need not be copied into a consumer solely because this Standard is adopted.

The `1.0.0` version above is the owner-approved bootstrap version for this newly established canonical owner.
