<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository;

use Maatify\I18n\DTO\DomainCoverageDTO;
use Maatify\I18n\DTO\I18nLanguageCodeCountDTO;
use Maatify\I18n\DTO\I18nStatCountDTO;
use Maatify\I18n\DTO\ScopeKeyCoverageDTO;

/**
 * Package-owned operational facts (counts, coverage, derived-state size).
 *
 * I18n does not know the Host language universe (ADR-019): language-facing
 * figures are exposed as exact-code counts and the Host composes names,
 * ordering and "languages with zero translations" itself. These reads are
 * read-only and exist so the Host never reconstructs I18n semantics by SQL.
 */
interface I18nOperationalStatsRepositoryInterface
{
    /**
     * Total number of translation keys (I18n-owned fact).
     */
    public function totalKeyCount(): int;

    /**
     * Number of translated keys per exact language scope, as recorded in the
     * derived summary. A language without any translation has no entry
     * (i.e. translated = 0).
     *
     * @return list<I18nLanguageCodeCountDTO>
     */
    public function translatedCountByLanguageCode(): array;

    /** @return list<I18nStatCountDTO> */
    public function keyCountByScope(): array;

    /**
     * Rows currently held by the derived language summary (rebuild reporting).
     */
    public function summaryRowCount(): int;

    /**
     * Coverage facts of one scope: keys of the domains assigned to it and the
     * translated count per exact (non-null) language code.
     */
    public function scopeKeyCoverage(string $scopeCode): ScopeKeyCoverageDTO;

    /**
     * Per-domain coverage of one scope for one exact language code; ordered by
     * most missing first, then display order. Domains of the scope with keys
     * only.
     *
     * @return list<DomainCoverageDTO>
     */
    public function domainCoverage(string $scopeCode, string $languageCode): array;
}
