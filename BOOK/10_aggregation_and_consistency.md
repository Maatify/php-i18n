# 10. Aggregation & Consistency Model

This chapter documents the **strong-consistency derived layers** and the **synchronous maintenance strategy** for translation statistics.

## 1. Derived Aggregation Layers

The package maintains **two** high-performance derived tables:

1. **`maa_i18n_domain_language_summary`** (domain-first summary)
2. **`maa_i18n_key_stats`** (per-key counters)

Both tables are **derived**, **non-authoritative**, and **fully rebuildable** from authoritative sources.

---

### 1.1 `maa_i18n_domain_language_summary`

**Purpose:** Fast completeness metrics per exact `(scope, domain, language_code)` ([ADR-019](../dcos/ADR-019-host-owned-exact-language-code-in-i18n.md)).

**Characteristics**

* **Derived:** Computed purely from `maa_i18n_keys` and `maa_i18n_translations`. No Host language table is read or joined.
* **Exact scope:** `language_code` is nullable; `NULL` is the exact unlocalized scope. Identity is NULL-safe (`language_code_identity`).
* **Sparse:** a row exists **only** while at least one translation of that exact scope exists. I18n does not know the Host language list and never pre-creates rows; the Host composes an absent row as `translated = 0` (so `missing = total_keys`).
* **Non-Authoritative:** Never treated as source of truth.
* **Rebuildable:** Safe to truncate + rebuild at any time.
* **Synchronous:** Maintained inside the same transaction as writes.

**Stored metrics per `(scope, domain, language_code)`**

* `total_keys`: number of keys in `(scope, domain)` (an I18n-owned fact)
* `translated_count`: number of translated keys for the exact `(scope, domain, language_code)`
* `missing_count`: `total_keys - translated_count`
  (must always be consistent with the two counters)

---

### 1.2 `maa_i18n_key_stats`

**Purpose:** Fast per-key translated counter to accelerate key-grid queries.

**Characteristics**

* **Derived:** Computed purely from `maa_i18n_keys` and `maa_i18n_translations`.
* **Non-Authoritative:** Optimization only.
* **Rebuildable:** Safe to truncate + rebuild at any time.
* **Synchronous:** Maintained inside the same transaction as writes.

**Stored metrics per `key_id`**

* `translated_count`: number of translation rows that exist for that key across all exact scopes
  (no knowledge of the Host language universe; it is a raw counter)

---

## 2. Consistency Model (Strong Consistency)

The package strictly enforces **Strong Consistency** for derived layers:

* **Synchronous Updates:** Counters are updated immediately during the write transaction.
* **Single-TX Guarantee:** Derived writes must run in the **same TX** as authoritative writes.
* **No Background Workers:** No queues, jobs, async repairs, or eventual reconciliation.
* **No Cron:** Scheduled tasks are not required for correctness.
* **Immediate Read-After-Write:** A read immediately following a committed write must reflect the new state.

> Derived layers are “fast mirrors”. If drift happens due to manual DB edits, **rebuild** is the canonical recovery.

---

## 3. Write Flow (Authoritative → Derived)

`TranslationWriteService` coordinates with `MissingCounterService` to keep derived layers correct.

### 3.1 Key Creation

When a new key is created:

1. `TranslationWriteService` inserts into `maa_i18n_keys`.
2. `MissingCounterService::onKeyCreated($keyId)` runs inside the same TX.
3. Derived updates:

    * `maa_i18n_domain_language_summary`: `incrementTotalKeys(scope, domain)`
    * `maa_i18n_key_stats`: `createForKey(keyId)` (initialize `translated_count = 0`)

### 3.2 Translation Creation

When a new translation row is inserted (`created = true`):

1. Authoritative insert occurs in `maa_i18n_translations`.
2. `MissingCounterService::onTranslationCreated(languageCode, keyId)` runs inside the same TX.
3. Derived updates:

    * `maa_i18n_domain_language_summary`: `refreshExactScope(scope, domain, languageCode)` (recomputes that one exact row from the authoritative tables; creates it when the first translation of the scope appears)
    * `maa_i18n_key_stats`: `incrementTranslated(keyId)`

### 3.3 Translation Deletion

When a translation row is deleted (`affected > 0`):

1. Authoritative delete occurs in `maa_i18n_translations`.
2. `MissingCounterService::onTranslationDeleted(languageCode, keyId)` runs inside the same TX.
3. Derived updates:

    * `maa_i18n_domain_language_summary`: `refreshExactScope(scope, domain, languageCode)` (the row is removed when the last translation of that exact scope is gone)
    * `maa_i18n_key_stats`: `decrementTranslated(keyId)` (must never go below 0)

### 3.4 Key Rename / Move (Scope/Domain Change)

When a key is updated such that `(scope, domain)` changes:

1. Authoritative update occurs in `maa_i18n_keys`.
2. `MissingCounterService::onKeyMoved(oldScope, oldDomain, newScope, newDomain)` runs inside the same TX.
3. Derived update strategy:

    * **Safest approach**: rebuild summaries for both affected pairs:

        * `rebuildScopeDomain(oldScope, oldDomain)`
        * `rebuildScopeDomain(newScope, newDomain)`

> This avoids guessing counter deltas across multiple languages.

### 3.4.1 Language-Code Re-key

When the Host renames a language code (`TranslationWriteService::rekeyLanguageCode(old, new)`):

1. Authoritative update: `maa_i18n_translations.language_code` old -> new (fails hard if `new` already owns translations).
2. `MissingCounterService::onLanguageCodeRekeyed(old, new)` runs inside the same TX:

    * `rebuildLanguageCode(old)` and `rebuildLanguageCode(new)` recompute the affected summary rows.

### 3.5 Key Deletion

The public services do not delete keys (there is no `deleteKey`; see [chapter 06](06_translation_lifecycle.md)). The maintenance hook below exists on `MissingCounterService` for a Host that removes a key by its own means in the same transaction:

1. The authoritative delete occurs in `maa_i18n_keys` (and `maa_i18n_translations` via FK cascade).
2. `MissingCounterService::onKeyDeleted(keyId)` runs in the same TX:

    * fetch key identity (fail-soft if not found)
    * `maa_i18n_domain_language_summary`: `decrementTotalKeys(scope, domain)`
      (implementation may choose rebuildScopeDomain for safety)
    * `maa_i18n_key_stats`: `deleteForKey(keyId)`

---

## 4. Rebuild Strategy (Canonical Recovery)

If drift occurs (manual DB edits, bad import, partial restore), derived layers can be rebuilt deterministically.

### 4.1 Full Rebuild

**Service:** `I18nStatsRebuilder::fullRebuild()`

Guarantees:

* **Single TX** for `clear + rebuild` to avoid half-state (the clear is a `DELETE`, not `TRUNCATE`, because `TRUNCATE` is DDL and would implicitly commit the transaction).
* Reads **only** `maa_i18n_keys` and `maa_i18n_translations`.
* **DB-driven** rebuild (pure SQL `INSERT..SELECT / GROUP BY`).
* **No PHP loops / no N+1**.
* **Idempotent**: safe to run multiple times.

**Required repository ops:**

* `DomainLanguageSummaryRepositoryInterface::truncate()`
* `DomainLanguageSummaryRepositoryInterface::rebuildAll()`
* `KeyStatsRepositoryInterface::truncate()`
* `KeyStatsRepositoryInterface::rebuildAll()`

### 4.2 Partial Rebuild (Repair)

**Repository:** `DomainLanguageSummaryRepositoryInterface::rebuildScopeDomain(scope, domain)`

Use cases:

* key moved across domain/scope
* suspected drift in a specific area
* safe alternative to counter guessing

---

## 5. Repository Contract Notes (Derived Layer Rules)

### 5.1 DomainLanguageSummaryRepositoryInterface

Key rules:

* `translated_count <= total_keys`
* `missing_count = total_keys - translated_count`
* All mutations must run in caller TX.
* A row exists iff the exact scope has at least one translation; `refreshExactScope` is idempotent and must keep incremental state identical to `rebuildAll()`.
* No method reads or joins a Host language table.

### 5.2 KeyStatsRepositoryInterface

Key rules:

* `translated_count` must be atomic and never go negative.
* Increment/decrement may be implemented as UPSERT to guarantee row existence.
* Rebuild is authoritative-SQL-driven from `maa_i18n_translations`.

---

## 6. Failure Semantics

* **Write path:** fail-hard (typed exceptions for governance/authoritative failures).
* **Derived maintenance:**

    * Normal incremental ops should succeed within the TX.
    * Repair-style operations (rebuildScopeDomain / rebuildAll) are deterministic and may throw if SQL fails.

---

## 7. Non-Goals

To preserve reliability and kernel-grade behavior, the package explicitly rejects:

* **Async reconciliation** (“fix later”)
* **Event buses / external workers**
* **Cron-based correctness**
* **Eventual consistency**
* **Cross-package coupling** (derived logic remains self-contained within the I18n package)

---
