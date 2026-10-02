<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Tests\Support\MysqlTestEnvironment;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Applies the published additive migration to the exact parent schema and
 * proves that existing authoritative rows retain their identity and content.
 */
final class TranslationTypeMigrationTest extends TestCase
{
    private const PRE_S1_SCHEMA_SHA256 = '41fb5f157ff3755c18738ba101e49840cc340e2ac52ebb43c25b2cb570354b42';

    private static ?PDO $pdo = null;
    private static string $schema = '';

    public static function setUpBeforeClass(): void
    {
        $credentials = MysqlTestEnvironment::credentials();
        if ($credentials === null) {
            if (MysqlTestEnvironment::isRequired()) {
                self::fail('I18N_IT_REQUIRED=1 but I18N_IT_DB_* credentials are not set.');
            }
            self::markTestSkipped('I18N_IT_DB_* env credentials not set — integration tests are opt-in.');
        }

        try {
            $pdo = MysqlTestEnvironment::connect($credentials);
        } catch (PDOException $e) {
            if (MysqlTestEnvironment::isRequired()) {
                self::fail('Required MySQL is unreachable: ' . $e->getMessage());
            }
            self::markTestSkipped('MySQL is not reachable: ' . $e->getMessage());
        }

        $root = dirname(__DIR__, 2);
        $fixture = $root . '/tests/Fixtures/Schema/schema.pre-s1.i18n.sql';
        $fixtureHash = hash_file('sha256', $fixture);
        self::assertSame(self::PRE_S1_SCHEMA_SHA256, $fixtureHash, 'The migration fixture must remain byte-exact.');

        self::$schema = MysqlTestEnvironment::randomSchemaName('type_migration');
        $pdo->exec('CREATE DATABASE `' . self::$schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . self::$schema . '`');
        $pdo->exec((string) file_get_contents($fixture));

        $pdo->exec("INSERT INTO maa_i18n_scopes (code, name) VALUES ('ct', 'Website')");
        $pdo->exec("INSERT INTO maa_i18n_domains (code, name) VALUES ('home', 'Home')");
        $pdo->exec("INSERT INTO maa_i18n_domain_scopes (scope_code, domain_code) VALUES ('ct', 'home')");
        $pdo->exec("INSERT INTO maa_i18n_keys (scope, domain, key_part) VALUES ('ct', 'home', 'legacy')");
        $keyId = (int) $pdo->lastInsertId();

        $seed = $pdo->prepare(
            'INSERT INTO maa_i18n_translations
                (key_id, language_code, value, created_at, updated_at)
             VALUES (:key_id, :language_code, :value, :created_at, :updated_at)',
        );
        $seed->execute([
            'key_id' => $keyId,
            'language_code' => 'ar',
            'value' => '',
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-02-03 04:05:06',
        ]);
        $seed->execute([
            'key_id' => $keyId,
            'language_code' => null,
            'value' => 'neutral',
            'created_at' => '2025-04-05 06:07:08',
            'updated_at' => null,
        ]);

        $pdo->exec((string) file_get_contents($root . '/schema/migrations/2026-10-02-translation-type.sql'));
        self::$pdo = $pdo;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$schema !== '' && self::$pdo !== null) {
            self::$pdo->exec('DROP DATABASE IF EXISTS `' . self::$schema . '`');
        }
        self::$pdo = null;
        self::$schema = '';
    }

    public function testMigrationPreservesRowsAndAllowsOnlyValidOpaqueMetadata(): void
    {
        $pdo = self::$pdo;
        self::assertInstanceOf(PDO::class, $pdo);

        $rowsStatement = $pdo->query(
            'SELECT id, key_id, language_code, value, created_at, updated_at, type
             FROM maa_i18n_translations ORDER BY id',
        );
        self::assertNotFalse($rowsStatement);
        $rows = $rowsStatement->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame([[
            'id' => 1,
            'key_id' => 1,
            'language_code' => 'ar',
            'value' => '',
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-02-03 04:05:06',
            'type' => null,
        ], [
            'id' => 2,
            'key_id' => 1,
            'language_code' => null,
            'value' => 'neutral',
            'created_at' => '2025-04-05 06:07:08',
            'updated_at' => null,
            'type' => null,
        ],
        ], $rows);

        $tablesStatement = $pdo->query('SHOW TABLES');
        self::assertNotFalse($tablesStatement);
        $tables = $tablesStatement->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(7, $tables);

        $identityStatement = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'maa_i18n_translations'
               AND INDEX_NAME = 'uq_maa_i18n_translation_unique'
             ORDER BY SEQ_IN_INDEX",
        );
        self::assertNotFalse($identityStatement);
        $identity = $identityStatement->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['key_id', 'language_code_identity'], $identity);

        $foreignKeyStatement = $pdo->query(
            "SELECT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME",
        );
        self::assertNotFalse($foreignKeyStatement);
        $foreignKeys = $foreignKeyStatement->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame([
            ['TABLE_NAME' => 'maa_i18n_key_stats', 'REFERENCED_TABLE_NAME' => 'maa_i18n_keys'],
            ['TABLE_NAME' => 'maa_i18n_translations', 'REFERENCED_TABLE_NAME' => 'maa_i18n_keys'],
        ], $foreignKeys);

        $write = $pdo->prepare(
            'INSERT INTO maa_i18n_translations (key_id, language_code, value, type)
             VALUES (1, :language_code, :value, :type)',
        );
        $write->execute(['language_code' => 'en', 'value' => '<p>opaque</p>', 'type' => 'client.rich-copy']);
        self::assertSame(3, (int) $pdo->lastInsertId());

        $readStatement = $pdo->query(
            'SELECT value, type FROM maa_i18n_translations WHERE id = 3',
        );
        self::assertNotFalse($readStatement);
        self::assertSame(
            ['value' => '<p>opaque</p>', 'type' => 'client.rich-copy'],
            $readStatement->fetch(PDO::FETCH_ASSOC),
        );

        foreach (['', ' ', "\t\n", "\x0B\x0C", "\u{0085}", "\u{00A0}", "\u{2028}", str_repeat('x', 33)] as $invalidType) {
            try {
                $write->execute(['language_code' => 'fr', 'value' => 'value', 'type' => $invalidType]);
                self::fail('The migrated check constraint must reject invalid metadata.');
            } catch (PDOException) {
                $countStatement = $pdo->query('SELECT COUNT(*) FROM maa_i18n_translations');
                self::assertNotFalse($countStatement);
                self::assertSame(3, (int) $countStatement->fetchColumn());
            }
        }
    }
}
