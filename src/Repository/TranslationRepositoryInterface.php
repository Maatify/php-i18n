<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository;

use Maatify\I18n\DTO\TranslationCollectionDTO;
use Maatify\I18n\DTO\TranslationDTO;
use Maatify\I18n\DTO\TranslationUpsertResultDTO;

/**
 * Exact-scope translation persistence (ADR-019).
 *
 * Every `?string $languageCode` is an exact scope: `null` is the unlocalized
 * scope, a non-null value is that exact code. There is no fallback, default or
 * wildcard semantics and no Host language lookup in any method.
 */
interface TranslationRepositoryInterface
{
    /**
     * Inserts or atomically updates the value and nullable type metadata in
     * the exact scope. Type is not part of identity. Empty string is a stored
     * value, not a deletion marker.
     */
    public function upsert(
        ?string $languageCode,
        int $keyId,
        string $value,
        ?string $type,
    ): TranslationUpsertResultDTO;

    /** Returns null only when no translation row has this ID. */
    public function getById(int $id): ?TranslationDTO;

    /** Returns the row for this exact nullable language code and key, if any. */
    public function getByLanguageAndKey(?string $languageCode, int $keyId): ?TranslationDTO;

    /** Tests whether a row exists in this exact nullable language scope. */
    public function existsByLanguageAndKey(?string $languageCode, int $keyId): bool;

    /**
     * Every translation row of one key (bounded by the languages the Host
     * translates into).
     */
    public function listByKey(int $keyId): TranslationCollectionDTO;

    /** Deletes the exact translation row and reports whether it existed. */
    public function deleteByLanguageAndKey(?string $languageCode, int $keyId): bool;

    /**
     * True when at least one translation row owns exactly this code.
     */
    public function hasAnyForLanguage(?string $languageCode): bool;

    /**
     * Re-key every translation of `$oldCode` to `$newCode` (exact scopes,
     * both non-null). Caller guarantees `$newCode` owns no rows.
     *
     * @return int number of re-keyed rows
     */
    public function rekeyLanguageCode(string $oldCode, string $newCode): int;
}
