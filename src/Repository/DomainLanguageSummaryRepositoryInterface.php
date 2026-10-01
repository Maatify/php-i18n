<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository;

/**
 * Defines the persistence operations for domain language summary data used by I18n services.
 */
interface DomainLanguageSummaryRepositoryInterface
{
    /* ==========================================================
     * INCREMENTAL MUTATIONS
     * ========================================================== */

    /**
     * A key was created in (scope, domain):
     * total_keys++ / missing_count++ on every EXISTING row of that
     * (scope, domain). No new rows are created.
     */
    public function incrementTotalKeys(
        string $scope,
        string $domain,
    ): void;

    /**
     * A key left (scope, domain): the (scope, domain) rows are recomputed
     * from authoritative tables (never guessed by delta).
     */
    public function decrementTotalKeys(
        string $scope,
        string $domain,
    ): void;

    /**
     * Recompute the single exact-scope row (scope, domain, languageCode)
     * from authoritative tables. Creates the row when translations exist,
     * removes it when none remain.
     *
     * Used after a translation create/delete.
     */
    public function refreshExactScope(
        string $scope,
        string $domain,
        ?string $languageCode,
    ): void;

    /**
     * Recompute every (scope, domain) row of one exact language code.
     *
     * Used after a language-code re-key (call for the old and the new code).
     */
    public function rebuildLanguageCode(?string $languageCode): void;

    /* ==========================================================
     * REBUILD
     * ========================================================== */

    /**
     * Truncate entire derived table.
     *
     * Used in full rebuild.
     */
    public function truncate(): void;

    /**
     * Rebuild entire table using authoritative tables
     * (maa_i18n_keys + maa_i18n_translations only).
     *
     * Must be SQL-driven and deterministic.
     */
    public function rebuildAll(): void;

    /**
     * Rebuild specific scope+domain only (all exact language scopes).
     *
     * Useful for partial repair.
     */
    public function rebuildScopeDomain(
        string $scope,
        string $domain,
    ): void;

    /* ==========================================================
     * READ ACCESS
     * ========================================================== */

    /**
     * Get the exact-scope summary row.
     *
     * Returns null if not found (which means translated = 0).
     *
     * @return array{
     *     total_keys: int,
     *     translated_count: int,
     *     missing_count: int
     * }|null
     */
    public function getRow(
        string $scope,
        string $domain,
        ?string $languageCode,
    ): ?array;
}
