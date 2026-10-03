<?php

declare(strict_types=1);

namespace Maatify\I18n\Consumer\Service;

use Maatify\I18n\Consumer\DTO\TranslationValueDTO;
use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\I18n\Repository\TranslationRepositoryInterface;
use Maatify\I18n\ValueObject\LanguageCode;

/**
 * Provides fail-soft exact-scope translation reads. Invalid codes and misses
 * return `null`; repository storage failures propagate unchanged.
 */
final readonly class TranslationReadService
{
    public function __construct(
        private TranslationKeyRepositoryInterface $keyRepository,
        private TranslationRepositoryInterface $translationRepository,
    ) {}

    /**
     * Safe exact read (ADR-019):
     * - Invalid codes and missing identities return null
     * - Repository storage failures propagate
     * - No parsing
     * - Structured key only
     * - Exact scope only: `$languageCode` reads that code, `null` reads the
     *   unlocalized scope. No fallback, default or wildcard of any kind —
     *   fallback is a Host policy.
     * - The code is never looked up in a language registry
     * - Returns null if nothing exists in that exact scope
     */
    public function getValue(
        ?string $languageCode,
        string $scope,
        string $domain,
        string $key,
    ): ?string {
        return $this->getTranslation($languageCode, $scope, $domain, $key)?->value;
    }

    /**
     * Reads the value and optional type from one exact language scope.
     *
     * A missing row, unknown key, or invalid code returns null. The method
     * does not apply language fallback or interpret the translation value.
     */
    public function getTranslation(
        ?string $languageCode,
        string $scope,
        string $domain,
        string $key,
    ): ?TranslationValueDTO {
        $exactCode = LanguageCode::tryFromNullable($languageCode);
        if ($exactCode === null) {
            return null; // fail-soft: an invalid code can own no row
        }

        $translationKey = $this->keyRepository
            ->getByStructuredKey($scope, $domain, $key);

        if ($translationKey === null) {
            return null;
        }

        $translation = $this->translationRepository
            ->getByLanguageAndKey($exactCode->value(), $translationKey->id);

        return $translation === null
            ? null
            : new TranslationValueDTO($translation->value, $translation->type);
    }
}
