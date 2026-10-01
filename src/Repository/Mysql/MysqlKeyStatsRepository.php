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

    public function deleteForKey(int $keyId): void
    {
        $this->gateway->write(
            'DELETE FROM maa_i18n_key_stats WHERE key_id = :key_id',
            ['key_id' => $keyId],
            'keyStats.delete',
        );
    }

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
     * Clear the derived table.
     * Used in full rebuild.
     *
     * DELETE (not TRUNCATE): TRUNCATE is DDL and implicitly commits, which
     * would break the single-transaction guarantee of I18nStatsRebuilder.
     */
    public function truncate(): void
    {
        $this->gateway->write('DELETE FROM maa_i18n_key_stats', [], 'keyStats.truncate');
    }

    /**
     * Rebuild a single key using authoritative maa_i18n_translations table.
     */
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

    /**
     * Full rebuild for entire table.
     *
     * Pure SQL aggregation:
     * - No PHP loops
     * - No N+1
     */
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
