<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Service;

use Maatify\I18n\DTO\DomainCoverageDTO;
use Maatify\I18n\DTO\I18nLanguageCodeCountDTO;
use Maatify\I18n\DTO\I18nStatCountDTO;
use Maatify\I18n\DTO\ScopeKeyCoverageDTO;
use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Repository\I18nOperationalStatsRepositoryInterface;

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
     * @return list<I18nLanguageCodeCountDTO>
     */
    public function translatedCountByLanguageCode(): array
    {
        return $this->stats->translatedCountByLanguageCode();
    }

    /**
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

    public function scopeKeyCoverage(string $scopeCode): ScopeKeyCoverageDTO
    {
        return $this->stats->scopeKeyCoverage($scopeCode);
    }

    /**
     * @return list<DomainCoverageDTO>
     */
    public function domainCoverage(string $scopeCode, string $languageCode): array
    {
        if ($languageCode === '') {
            throw I18nInvalidArgumentException::emptyField('languageCode');
        }

        return $this->stats->domainCoverage($scopeCode, $languageCode);
    }
}
