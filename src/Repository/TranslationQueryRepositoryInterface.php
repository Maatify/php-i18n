<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository;

use Maatify\I18n\DTO\KeyTranslationSummaryDTO;
use Maatify\I18n\DTO\LanguageTranslationValueDTO;
use Maatify\I18n\DTO\TranslationGridRowDTO;
use Maatify\I18n\Management\Criteria\DomainKeySummaryCriteria;
use Maatify\I18n\Management\Criteria\DomainTranslationGridCriteria;
use Maatify\I18n\Management\Criteria\LanguageTranslationValuesCriteria;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/**
 * Package-owned management read surface over keys x translations (ADR-019).
 *
 * Every language-facing input is an exact code supplied by the Host; the
 * Package never reads a Host language table. Pagination mechanics are
 * delegated to maatify/persistence; the Package owns filters, search, count
 * alignment and row mapping.
 */
interface TranslationQueryRepositoryInterface
{
    /**
     * Keys of one (scope, domain) with the number of the supplied exact codes
     * each key is still missing.
     *
     * @return PageResult<KeyTranslationSummaryDTO>
     */
    public function pageDomainKeySummaries(DomainKeySummaryCriteria $criteria): PageResult;

    /**
     * Keys x supplied exact codes of one (scope, domain); `value === null`
     * marks a missing translation.
     *
     * @return PageResult<TranslationGridRowDTO>
     */
    public function pageDomainTranslationGrid(DomainTranslationGridCriteria $criteria): PageResult;

    /**
     * Every key with its translation in one exact language scope.
     *
     * @return PageResult<LanguageTranslationValueDTO>
     */
    public function pageLanguageTranslationValues(LanguageTranslationValuesCriteria $criteria): PageResult;
}
