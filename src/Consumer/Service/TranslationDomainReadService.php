<?php

declare(strict_types=1);

namespace Maatify\I18n\Consumer\Service;

use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\I18n\Repository\TranslationRepositoryInterface;
use Maatify\I18n\Consumer\DTO\TranslationDomainValuesDTO;
use Maatify\I18n\Exception\InvalidLanguageCodeException;
use Maatify\I18n\Service\I18nGovernancePolicyService;
use Maatify\I18n\ValueObject\LanguageCode;

/**
 * Reads all available translations for one domain and exact language scope without applying a fallback.
 */
final readonly class TranslationDomainReadService
{
    public function __construct(
        private TranslationKeyRepositoryInterface $keyRepository,
        private TranslationRepositoryInterface $translationRepository,
        private I18nGovernancePolicyService $policyService,
    ) {}

    /**
     * Bulk exact read for a single domain (ADR-019).
     *
     * - Policy enforced
     * - Exact scope only: `$languageCode` reads that code, `null` reads the
     *   unlocalized scope; no fallback of any kind (Host policy)
     * - No language registry lookup; an unknown code simply owns no rows
     * - Fail-soft: empty DTO when nothing resolvable
     */
    public function getDomainValues(
        ?string $languageCode,
        string $scope,
        string $domain,
    ): TranslationDomainValuesDTO {
        try {
            $exactCode = LanguageCode::fromNullable($languageCode);
        } catch (InvalidLanguageCodeException) {
            return new TranslationDomainValuesDTO([]);
        }

        // 1) Enforce governance
        if (!$this->policyService->isScopeAndDomainReadable($scope, $domain)) {
            return new TranslationDomainValuesDTO([]);
        }

        // 2) Resolve keys for (scope + domain)
        $keys = $this->keyRepository->listByScopeAndDomain(
            scope : $scope,
            domain: $domain,
        );

        if ($keys->isEmpty()) {
            return new TranslationDomainValuesDTO([]);
        }

        $values = [];

        foreach ($keys->items as $keyDto) {
            $translation = $this->translationRepository
                ->getByLanguageAndKey($exactCode->value(), $keyDto->id);

            if ($translation !== null) {
                $values[$keyDto->key] = $translation->value;
            }
        }

        return new TranslationDomainValuesDTO($values);
    }
}
