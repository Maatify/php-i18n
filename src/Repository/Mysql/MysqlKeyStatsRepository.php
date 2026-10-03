<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository\Mysql;

use Maatify\I18n\Repository\KeyStatsRepositoryInterface;
use PDO;

/**
 * Persists and reads key stats records through the package-owned MySQL schema.
 */
final readonly class MysqlKeyStatsRepository implements KeyStatsRepositoryInterface
{
    private PdoGateway $gateway;

    public function __construct(PDO $pdo)
    {
        $this->gateway = new PdoGateway($pdo);
    }

    /* ==========================================================
     * BASIC MUTATIONS
     * ========================================================== */

    /** Create a zero-count stats row only when absent; an existing row is unchanged. */
    public function createForKey(int $keyId): void
    {
        $this->gateway->write(
            'INSERT INTO maa_i18n_key_stats (key_id, translated_count)
             VALUES (:key_id, 0)
             ON DUPLICATE KEY UPDATE key_id = key_id',
            ['key_id' => $keyId],
            'keyStats.create',
        );
    }

    /** Delete a key's derived stats row; absence is a no-op. */
    public function deleteForKey(int $keyId): void
    {
        $this->gateway->write(
            'DELETE FROM maa_i18n_key_stats WHERE key_id = :key_id',
            ['key_id' => $keyId],
            'keyStats.delete',
        );
    }

    /** Atomically increment the derived translated count, creating it at one when absent. */
    public function incrementTranslated(int $keyId): void
    {
        $this->gateway->write(
            'INSERT INTO maa_i18n_key_stats (key_id, translated_count)
             VALUES (:key_id, 1)
             ON DUPLICATE KEY UPDATE translated_count = translated_count + 1',
            ['key_id' => $keyId],
            'keyStats.increment',
        );
    }

    /** Decrement the derived count atomically, creating or retaining zero rather than going negative. */
    public function decrementTranslated(int $keyId): void
    {
        $this->gateway->write(
            'INSERT INTO maa_i18n_key_stats (key_id, translated_count)
             VALUES (:key_id, 0)
             ON DUPLICATE KEY UPDATE translated_count =
                 CASE
                     WHEN translated_count > 0
                     THEN translated_count - 1
                     ELSE 0
                 END',
            ['key_id' => $keyId],
            'keyStats.decrement',
        );
    }

    /** Store the supplied count, clamping negative values to zero. */
    public function setTranslatedCount(
        int $keyId,
        int $translatedCount,
    ): void {
        if ($translatedCount < 0) {
            $translatedCount = 0;
        }

        $this->gateway->write(
            'INSERT INTO maa_i18n_key_stats (key_id, translated_count)
             VALUES (:key_id, :count_insert)
             ON DUPLICATE KEY UPDATE translated_count = :count_update',
            ([
                'key_id'       => $keyId,
                'count_insert' => $translatedCount,
                'count_update' => $translatedCount,
            ]),
            'keyStats.set',
        );
    }

    /** Return the stored derived count, or zero when no stats row exists. */
    public function getTranslatedCount(int $keyId): int
    {
        $row = $this->gateway->fetchOne(
            'SELECT translated_count FROM maa_i18n_key_stats WHERE key_id = :key_id',
            ['key_id' => $keyId],
            'keyStats.get',
        );

        // A missing row is the documented "0 translations" fact, not a failure.
        return $row === null ? 0 : Row::int($row, 'translated_count');
    }

    /* ==========================================================
     * REBUILD OPERATIONS (SQL-DRIVEN)
     * ========================================================== */

    /**
     * Clear the derived table before a full rebuild.
     *
     * DELETE (not TRUNCATE): TRUNCATE is DDL and implicitly commits, which
     * would break the single-transaction guarantee of I18nStatsRebuilder.
     */
    public function truncate(): void
    {
        $this->gateway->write('DELETE FROM maa_i18n_key_stats', [], 'keyStats.truncate');
    }

    /** Recompute this key's derived count from authoritative translations. */
    public function rebuildForKey(int $keyId): void
    {
        $this->gateway->write(
            'INSERT INTO maa_i18n_key_stats (key_id, translated_count)
             SELECT
                 k.id,
                 COUNT(t.id)
             FROM maa_i18n_keys k
             LEFT JOIN maa_i18n_translations t
                 ON t.key_id = k.id
             WHERE k.id = :key_id
             GROUP BY k.id
             ON DUPLICATE KEY UPDATE translated_count = VALUES(translated_count)',
            ['key_id' => $keyId],
            'keyStats.rebuildForKey',
        );
    }

    /** Recompute every current key's derived count from authoritative translations after the table is cleared. */
    public function rebuildAll(): void
    {
        $this->gateway->write(
            'INSERT INTO maa_i18n_key_stats (key_id, translated_count)
             SELECT
                 k.id,
                 COUNT(t.id) AS translated_count
             FROM maa_i18n_keys k
             LEFT JOIN maa_i18n_translations t
                 ON t.key_id = k.id
             GROUP BY k.id
             ON DUPLICATE KEY UPDATE
                 translated_count = VALUES(translated_count)',
            [],
            'keyStats.rebuildAll',
        );
    }
}
