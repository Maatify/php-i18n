<?php

/**
 * @copyright   ©2026 Maatify.dev
 * @Library     maatify/i18n
 * @Project     maatify:i18n
 * @author      Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @since       2026-10-01 00:00
 * @see         https://www.maatify.dev Maatify.dev
 * @link        https://github.com/Maatify/i18n view Project on GitHub
 * @note        Distributed in the hope that it will be useful - WITHOUT WARRANTY.
 */

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;

/**
 * GA-F05: the fresh install of schema/schema.i18n.sql is exactly the seven
 * canonical `maa_i18n_*` tables with the current Package standard (PKs,
 * indexes, constraints, column comments, no Host coupling, ADR-019 shape).
 */
final class FreshSchemaTest extends MysqlIntegrationTestCase
{
    private const CANONICAL = [
        'maa_i18n_domain_language_summary',
        'maa_i18n_domain_scopes',
        'maa_i18n_domains',
        'maa_i18n_key_stats',
        'maa_i18n_keys',
        'maa_i18n_scopes',
        'maa_i18n_translations',
    ];

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(['schema' => self::schemaName()]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    public function testExactlyTheSevenCanonicalTablesExist(): void
    {
        $tables = array_map(
            static fn(array $r): string => is_string($r['TABLE_NAME']) ? $r['TABLE_NAME'] : '',
            $this->rows('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema ORDER BY TABLE_NAME'),
        );

        self::assertSame(self::CANONICAL, $tables);
        foreach ($tables as $table) {
            self::assertStringStartsWith('maa_i18n_', $table);
        }
    }

    public function testEveryTableHasAnIdPrimaryKeyAndEveryColumnAComment(): void
    {
        foreach (self::CANONICAL as $table) {
            $pk = $this->rows(
                "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = '" . $table . "' AND CONSTRAINT_NAME = 'PRIMARY'",
            );
            self::assertSame([['COLUMN_NAME' => 'id']], $pk, $table . ' must be keyed by id');

            $uncommented = $this->rows(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = '" . $table . "' AND TRIM(COLUMN_COMMENT) = ''",
            );
            self::assertSame([], $uncommented, $table . ' has uncommented columns');
        }
    }

    public function testKeyStatsHasItsOwnIdAndOneRowPerKey(): void
    {
        $unique = $this->rows(
            "SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = 'maa_i18n_key_stats' AND COLUMN_NAME = 'key_id'",
        );
        self::assertCount(1, $unique);
        self::assertSame('0', self::text($unique[0]['NON_UNIQUE']));

        $keyId = $this->createKey('ct', 'home', 'k');
        $this->pdo()->exec('INSERT INTO maa_i18n_key_stats (key_id, translated_count) VALUES (' . $keyId . ', 0) ON DUPLICATE KEY UPDATE key_id = key_id');
        self::assertSame(1, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_key_stats WHERE key_id = ' . $keyId));
    }

    public function testTheOnlyForeignKeysPointAtPackageKeysNeverAtHostTables(): void
    {
        $fks = $this->rows(
            'SELECT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = :schema AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME',
        );

        self::assertSame([
            ['TABLE_NAME' => 'maa_i18n_key_stats', 'REFERENCED_TABLE_NAME' => 'maa_i18n_keys'],
            ['TABLE_NAME' => 'maa_i18n_translations', 'REFERENCED_TABLE_NAME' => 'maa_i18n_keys'],
        ], $fks);
    }

    public function testAdr019ExactNullableBinaryLanguageCodeShape(): void
    {
        foreach (['maa_i18n_translations', 'maa_i18n_domain_language_summary'] as $table) {
            $col = $this->rows(
                "SELECT IS_NULLABLE, COLLATION_NAME, CHARACTER_MAXIMUM_LENGTH, GENERATION_EXPRESSION FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = '" . $table . "' AND COLUMN_NAME = 'language_code'",
            );
            self::assertSame('YES', $col[0]['IS_NULLABLE'], $table);
            self::assertSame('utf8mb4_bin', $col[0]['COLLATION_NAME'], $table);
            self::assertSame('16', self::text($col[0]['CHARACTER_MAXIMUM_LENGTH']), $table);
        }

        $identity = $this->rows(
            "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema AND COLUMN_NAME = 'language_id'",
        );
        self::assertSame('0', self::text($identity[0]['c']), 'no Host language id anywhere');
    }

    public function testNoLegacyUnprefixedTableExists(): void
    {
        $legacy = $this->rows("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME LIKE 'i18n\\_%'");
        self::assertSame([], $legacy);
    }
}
