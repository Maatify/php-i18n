<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Service;

use Maatify\I18n\DTO\DomainCoverageDTO;
use Maatify\I18n\DTO\I18nLanguageCodeCountDTO;
use Maatify\I18n\DTO\I18nStatCountDTO;
use Maatify\I18n\DTO\ScopeKeyCoverageDTO;
use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Repository\I18nOperationalStatsRepositoryInterface;
use Maatify\I18n\ValueObject\LanguageCode;

/**
 * Operational Read surface: Package-owned counts and coverage facts.
 *
 * Read-only. The Host composes these exact-code facts with its own language
 * metadata; it must not rebuild them by SQL over Package tables.
 */
final readonly class I18nOperationalReadService
{
    public function __construct(
        private I18nOperationalStatsRepositoryInterface $stats,
    ) {}

    public function totalKeyCount(): int
    {
        return $this->stats->totalKeyCount();
    }

    /**
     * Return the Package-owned translated count for each exact language scope,
     * including null for the unlocalized scope; the Host composes language
     * metadata and languages with zero translations.
     *
     * @return list<I18nLanguageCodeCountDTO>
     */
    public function translatedCountByLanguageCode(): array
    {
        return $this->stats->translatedCountByLanguageCode();
    }

    /**
     * Return the Package-owned key count grouped by scope.
     *
     * @return list<I18nStatCountDTO>
     */
    public function keyCountByScope(): array
    {
        return $this->stats->keyCountByScope();
    }

    public function summaryRowCount(): int
    {
        return $this->stats->summaryRowCount();
    }

    /**
     * Return key totals for domains assigned to the scope and translated counts
     * by exact non-null language code; the Host composes language metadata and
     * languages with zero translations.
     */
    public function scopeKeyCoverage(string $scopeCode): ScopeKeyCoverageDTO
    {
        return $this->stats->scopeKeyCoverage($scopeCode);
    }

    /**
     * Reads per-domain coverage for one exact, technically valid language code.
     * The code is passed unchanged; language semantics remain Host-owned.
     *
     * @return list<DomainCoverageDTO>
     * @throws I18nInvalidArgumentException when languageCode is empty,
     *     whitespace-only, or longer than LanguageCode::MAX_LENGTH
     */
    public function domainCoverage(string $scopeCode, string $languageCode): array
    {
        if (LanguageCode::tryFromNullable($languageCode) === null) {
            if (mb_strlen($languageCode) > LanguageCode::MAX_LENGTH) {
                throw I18nInvalidArgumentException::tooLong('languageCode', LanguageCode::MAX_LENGTH);
            }

            throw I18nInvalidArgumentException::emptyField('languageCode');
        }

        return $this->stats->domainCoverage($scopeCode, $languageCode);
    }
}
