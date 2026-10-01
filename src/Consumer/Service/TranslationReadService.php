<?php

declare(strict_types=1);

namespace Maatify\I18n\Consumer\Service;

use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\I18n\Repository\TranslationRepositoryInterface;
use Maatify\I18n\ValueObject\LanguageCode;
use Maatify\I18n\Exception\InvalidLanguageCodeException;

/**
 * Provides fail-soft exact-scope translation reads. A miss returns `null`; the service never applies a language fallback.
 */
final readonly class TranslationReadService
{
    public function __construct(
        private TranslationKeyRepositoryInterface $keyRepository,
        private TranslationRepositoryInterface $translationRepository,
    ) {}

    /**
     * Safe exact read (ADR-019):
     * - No exceptions
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
        try {
            $exactCode = LanguageCode::fromNullable($languageCode);
        } catch (InvalidLanguageCodeException) {
            return null; // fail-soft: an invalid code can own no row
        }

        $translationKey = $this->keyRepository
            ->getByStructuredKey($scope, $domain, $key);

        if ($translationKey === null) {
            return null;
        }

        return $this->translationRepository
            ->getByLanguageAndKey($exactCode->value(), $translationKey->id)
            ?->value;
    }
}
