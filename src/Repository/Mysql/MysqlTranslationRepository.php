<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository\Mysql;

use Maatify\I18n\DTO\TranslationCollectionDTO;
use Maatify\I18n\DTO\TranslationDTO;
use Maatify\I18n\DTO\TranslationUpsertResultDTO;
use Maatify\I18n\Repository\TranslationRepositoryInterface;
use Maatify\I18n\ValueObject\LanguageCode;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/**
 * Persists and reads translation records through the package-owned MySQL schema.
 */
final readonly class MysqlTranslationRepository implements TranslationRepositoryInterface
{
    private const COLUMNS = 'id, key_id, language_code, value, type, created_at, updated_at';

    private PdoGateway $gateway;

    public function __construct(
        PDO $pdo,
        private ClockInterface $clock,
    ) {
        $this->gateway = new PdoGateway($pdo);
    }

    /**
     * Insert or update the exact nullable language scope (`null` is
     * unlocalized), with no fallback or normalization, writing value and type
     * together; type is not identity and an empty value remains stored. The
     * result ID identifies the row and `created` is true only for an insert.
     */
    public function upsert(
        ?string $languageCode,
        int $keyId,
        string $value,
        ?string $type,
    ): TranslationUpsertResultDTO {
        $stmt = $this->gateway->run(
            'INSERT INTO maa_i18n_translations (language_code, key_id, value, type)
             VALUES (:language_code, :key_id, :value, :type)
             ON DUPLICATE KEY UPDATE
                 id = LAST_INSERT_ID(id),
                 value = VALUES(value),
                 type = VALUES(type),
                 updated_at = :now',
            ([
                'language_code' => $languageCode,
                'key_id' => $keyId,
                'value' => $value,
                'type' => $type,
                'now' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]),
            'translation.upsert',
        );

        // rowCount() === 1 -> inserted; 2 -> updated; 0 -> unchanged upsert.
        return new TranslationUpsertResultDTO(
            $this->gateway->lastInsertId('translation.upsert'),
            $stmt->rowCount() === 1,
        );
    }

    /** Return a translation by ID, or null only when no row matches. */
    public function getById(int $id): ?TranslationDTO
    {
        $row = $this->gateway->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM maa_i18n_translations WHERE id = :id LIMIT 1',
            ['id' => $id],
            'translation.getById',
        );

        return $row === null ? null : $this->map($row);
    }

    /**
     * Read the row for this exact nullable language scope and key; null is
     * unlocalized, with no fallback or normalization.
     */
    public function getByLanguageAndKey(?string $languageCode, int $keyId): ?TranslationDTO
    {
        $row = $this->gateway->fetchOne(
            'SELECT ' . self::COLUMNS . '
             FROM maa_i18n_translations
             WHERE language_code_identity = :language_identity AND key_id = :key_id
             LIMIT 1',
            ([
                'language_identity' => LanguageCode::fromNullable($languageCode)->identity(),
                'key_id' => $keyId,
            ]),
            'translation.getByLanguageAndKey',
        );

        return $row === null ? null : $this->map($row);
    }

    /** Return every translation of the key in language-identity order; no rows yields an empty collection. */
    public function listByKey(int $keyId): TranslationCollectionDTO
    {
        $rows = $this->gateway->fetchAll(
            'SELECT ' . self::COLUMNS . '
             FROM maa_i18n_translations
             WHERE key_id = :key_id
             ORDER BY language_code_identity ASC',
            ['key_id' => $keyId],
            'translation.listByKey',
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->map($row);
        }

        return new TranslationCollectionDTO($items);
    }

    /**
     * Delete the exact nullable language-scope row for the key (`null` is
     * unlocalized); return whether it existed.
     */
    public function deleteByLanguageAndKey(?string $languageCode, int $keyId): bool
    {
        return $this->gateway->write(
            'DELETE FROM maa_i18n_translations
             WHERE language_code_identity = :language_identity AND key_id = :key_id',
            ([
                'language_identity' => LanguageCode::fromNullable($languageCode)->identity(),
                'key_id' => $keyId,
            ]),
            'translation.delete',
        ) > 0;
    }

    /** Test whether a row exists for this exact nullable language scope and key. */
    public function existsByLanguageAndKey(?string $languageCode, int $keyId): bool
    {
        return $this->gateway->exists(
            'SELECT 1
             FROM maa_i18n_translations
             WHERE language_code_identity = :language_identity AND key_id = :key_id
             LIMIT 1',
            ([
                'language_identity' => LanguageCode::fromNullable($languageCode)->identity(),
                'key_id' => $keyId,
            ]),
            'translation.exists',
        );
    }

    /** Test whether any row exists in this exact nullable language scope. */
    public function hasAnyForLanguage(?string $languageCode): bool
    {
        return $this->gateway->exists(
            'SELECT 1
             FROM maa_i18n_translations
             WHERE language_code_identity = :language_identity
             LIMIT 1',
            ['language_identity' => LanguageCode::fromNullable($languageCode)->identity()],
            'translation.hasAnyForLanguage',
        );
    }

    /**
     * Move rows from the exact non-null old code to the exact new code; the
     * caller ensures the new code has no rows. Return the number of rows moved.
     */
    public function rekeyLanguageCode(string $oldCode, string $newCode): int
    {
        return $this->gateway->write(
            'UPDATE maa_i18n_translations
             SET language_code = :new_code
             WHERE language_code_identity = :old_identity',
            ['new_code' => $newCode, 'old_identity' => $oldCode],
            'translation.rekey',
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): TranslationDTO
    {
        return new TranslationDTO(
            Row::int($row, 'id'),
            Row::int($row, 'key_id'),
            Row::nullableString($row, 'language_code'),
            Row::string($row, 'value'),
            Row::nullableString($row, 'type'),
            Row::string($row, 'created_at'),
            Row::nullableString($row, 'updated_at'),
        );
    }
}
