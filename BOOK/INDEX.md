# Documentation Index

This is the entry point to the conceptual documentation of `maatify/php-i18n`.

The Book explains the ideas behind the design: governance, structured keys, exact language scopes, lifecycle, runtime reads, errors and the derived aggregation layers. It is **not** a contract owner. If a chapter and the canonical documents differ, the canonical documents prevail:

- [I18N_PACKAGE_REFERENCE.md](../I18N_PACKAGE_REFERENCE.md): the public, runtime and behavioral contract and the complete Public Runtime API inventory.
- [docs/guides/USAGE_GUIDE.md](../docs/guides/USAGE_GUIDE.md): how to integrate the package.
- [README.md](../README.md): overview, installation state and verification commands.

Use this index to navigate non-linearly, or read the chapters in order.

---

## Getting Started

- [01_introduction.md](01_introduction.md)
  Library identity, philosophy, architectural boundaries, and non-goals.

- [02_core_concepts.md](02_core_concepts.md)
  Core terminology: scope, domain, structured keys, exact language identity.

---

## Governance and Authority

- [03_governance_model.md](03_governance_model.md)
  Scope and domain governance, enforcement rules, policy modes, and write authority.

---

## Key Design Rules

- [05_key_design_patterns.md](05_key_design_patterns.md)
  Key structure, naming rules, and anti-patterns.

---

## Translation Management

- [06_translation_lifecycle.md](06_translation_lifecycle.md)
  Creating, renaming, updating, and deleting translation keys and values.

---

## Runtime Reads

- [07_runtime_reads.md](07_runtime_reads.md)
  Fail-soft read behavior, single versus bulk reads, exact-scope reads (no fallback inside I18n), and caching.

---

## Error Handling

- [08_error_handling.md](08_error_handling.md)
  Write-time exceptions, read-time null semantics, and handling patterns.

---

## Real World Usage

- [09_real_world_scenarios.md](09_real_world_scenarios.md)
  Scenarios: feature expansion, Host-owned regional fallback, and key refactoring.

---

## Aggregation and Consistency

- [10_aggregation_and_consistency.md](10_aggregation_and_consistency.md)
  Derived layers (`maa_i18n_domain_language_summary` and `maa_i18n_key_stats`), synchronous counters, rebuild strategy, and the consistency model.

---

## Reading Advice

- Read once top to bottom, then use this index for lookups.
- Governance rules apply to writes; runtime reads are fail-soft by design.
- For signatures, exact exception behavior and the API inventory, go to the [Package Reference](../I18N_PACKAGE_REFERENCE.md).

---

End of documentation index.
