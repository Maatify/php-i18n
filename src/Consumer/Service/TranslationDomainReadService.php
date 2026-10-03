<?php

declare(strict_types=1);

namespace Maatify\I18n\Consumer\Service;

use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\I18n\Repository\TranslationRepositoryInterface;
use Maatify\I18n\Consumer\DTO\TranslationDomainTranslationsDTO;
use Maatify\I18n\Consumer\DTO\TranslationValueDTO;
use Maatify\I18n\Consumer\DTO\TranslationDomainValuesDTO;
use Maatify\I18n\Service\I18nGovernancePolicyService;
use Maatify\I18n\ValueObject\LanguageCode;

/**
 * Reads all available translations for one domain and exact language scope without applying a fallback.
 * Invalid codes and unreadable governance combinations return empty DTOs;
 * repository storage failures propagate unchanged.
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
     * - Invalid codes and unreadable governance return an empty DTO
     * - Repository storage failures propagate
     */
    public function getDomainValues(
        ?string $languageCode,
        string $scope,
        string $domain,
    ): TranslationDomainValuesDTO {
        $translations = $this->getDomainTranslations($languageCode, $scope, $domain);
        $values = [];
        foreach ($translations->translations as $keyPart => $translation) {
            $values[$keyPart] = $translation->value;
        }

        return new TranslationDomainValuesDTO($values);
    }

    /**
     * Reads all available values and types for one domain and exact language scope.
     *
     * Missing rows are absent from the result. An empty value remains present
     * with its optional type. Invalid codes or unreadable domains return an
     * empty DTO; storage failures propagate and no fallback is applied.
     */
    public function getDomainTranslations(
        ?string $languageCode,
        string $scope,
        string $domain,
    ): TranslationDomainTranslationsDTO {
        $exactCode = LanguageCode::tryFromNullable($languageCode);
        if ($exactCode === null) {
            return new TranslationDomainTranslationsDTO([]);
        }

        // 1) Enforce governance
        if (!$this->policyService->isScopeAndDomainReadable($scope, $domain)) {
            return new TranslationDomainTranslationsDTO([]);
        }

        // 2) Resolve keys for (scope + domain)
        $keys = $this->keyRepository->listByScopeAndDomain(
            scope : $scope,
            domain: $domain,
        );

        if ($keys->isEmpty()) {
            return new TranslationDomainTranslationsDTO([]);
        }

        $translations = [];

        foreach ($keys->items as $keyDto) {
            $translation = $this->translationRepository
                ->getByLanguageAndKey($exactCode->value(), $keyDto->id);

            if ($translation !== null) {
                $translations[$keyDto->key] = new TranslationValueDTO(
                    $translation->value,
                    $translation->type,
                );
            }
        }

        return new TranslationDomainTranslationsDTO($translations);
    }
}
