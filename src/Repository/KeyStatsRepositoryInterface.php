<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository;

/**
 * Defines the persistence operations for key stats data used by I18n services.
 */
interface KeyStatsRepositoryInterface
{
    /**
     * Create stats row when key is created.
     *
     * Must initialize:
     * translated_count = 0
     *
     * Should be idempotent-safe (INSERT IGNORE / ON DUPLICATE no-op).
     */
    public function createForKey(int $keyId): void;

    /**
     * Remove stats row when key is deleted.
     *
     * Safe to call even if row does not exist.
     */
    public function deleteForKey(int $keyId): void;

    /**
     * Increment translated counter for a key.
     *
     * Must be atomic.
     */
    public function incrementTranslated(int $keyId): void;

    /**
     * Decrement translated counter for a key.
     *
     * Must never go below zero.
     * Implementation should guard against negative values.
     */
    public function decrementTranslated(int $keyId): void;

    /**
     * Set translated count explicitly.
     *
     * Used for:
     * - Full rebuild
     * - Repair
     */
    public function setTranslatedCount(
        int $keyId,
        int $translatedCount,
    ): void;

    /**
     * Get translated count for key.
     *
     * Returns 0 if row does not exist.
     */
    public function getTranslatedCount(int $keyId): int;

    /**
     * Bulk rebuild for a key.
     *
     * Should calculate translated_count
     * from maa_i18n_translations table.
     *
     * Used in:
     * - Repair mode
     * - Migration
     * - Integrity validation
     */
    public function rebuildForKey(int $keyId): void;

    /** Clears the derived key-stats table before a complete rebuild. */
    public function truncate(): void;

    /** Recomputes every key's translated count from authoritative translations. */
    public function rebuildAll(): void;
}
