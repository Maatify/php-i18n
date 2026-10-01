<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Command;

use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Exception\InvalidLanguageCodeException;
use Maatify\I18n\ValueObject\LanguageCode;

/**
 * Intent: write the translation of one exact language scope of a key
 * (ADR-019). `languageCode === null` is the exact unlocalized scope; the code
 * is checked against the technical storage contract only. The empty string is
 * a valid, authoritative value.
 */
final readonly class UpsertTranslationCommand
{
    public ?string $languageCode;

    /**
     * Requires a positive key ID and validates the nullable exact code against
     * the storage contract; null remains the unlocalized scope and empty value
     * is preserved as a valid translation.
     *
     * @throws I18nInvalidArgumentException for a non-positive key ID
     * @throws InvalidLanguageCodeException for an invalid non-null code
     */
    public function __construct(
        ?string $languageCode,
        public int $keyId,
        public string $value,
    ) {
        if ($keyId <= 0) {
            throw I18nInvalidArgumentException::notPositive('keyId');
        }

        $this->languageCode = LanguageCode::fromNullable($languageCode)->value();
    }
}
