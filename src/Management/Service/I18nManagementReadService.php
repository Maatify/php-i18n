<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Service;

use Maatify\I18n\DTO\DomainAssignmentDTO;
use Maatify\I18n\DTO\DomainDTO;
use Maatify\I18n\DTO\DomainOptionCollectionDTO;
use Maatify\I18n\DTO\KeyTranslationSummaryDTO;
use Maatify\I18n\DTO\LanguageTranslationValueDTO;
use Maatify\I18n\DTO\ScopeDTO;
use Maatify\I18n\DTO\TranslationGridRowDTO;
use Maatify\I18n\DTO\TranslationKeyDTO;
use Maatify\I18n\Exception\DomainNotFoundException;
use Maatify\I18n\Exception\ScopeNotFoundException;
use Maatify\I18n\Exception\TranslationKeyNotFoundException;
use Maatify\I18n\Management\Criteria\DomainKeySummaryCriteria;
use Maatify\I18n\Management\Criteria\DomainListCriteria;
use Maatify\I18n\Management\Criteria\DomainTranslationGridCriteria;
use Maatify\I18n\Management\Criteria\KeyListCriteria;
use Maatify\I18n\Management\Criteria\LanguageTranslationValuesCriteria;
use Maatify\I18n\Management\Criteria\ScopeDomainListCriteria;
use Maatify\I18n\Management\Criteria\ScopeListCriteria;
use Maatify\I18n\Repository\DomainRepositoryInterface;
use Maatify\I18n\Repository\DomainScopeRepositoryInterface;
use Maatify\I18n\Repository\ScopeRepositoryInterface;
use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\I18n\Repository\TranslationQueryRepositoryInterface;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/**
 * Management read API: details and paginated lists of the Package-owned state.
 *
 * Read-only. Not-found for a requested identity is an exception; a list over an
 * unknown parent is simply empty. Pagination mechanics come from
 * maatify/persistence (PageResult); the Host adapts the result to its own
 * response contract.
 */
final readonly class I18nManagementReadService
{
    public function __construct(
        private ScopeRepositoryInterface $scopes,
        private DomainRepositoryInterface $domains,
        private DomainScopeRepositoryInterface $domainScopes,
        private TranslationKeyRepositoryInterface $keys,
        private TranslationQueryRepositoryInterface $queries,
    ) {}

    /**
     * @throws ScopeNotFoundException
     */
    public function getScope(int $id): ScopeDTO
    {
        return $this->scopes->getById($id) ?? throw new ScopeNotFoundException((string) $id);
    }

    /**
     * @throws ScopeNotFoundException
     */
    public function getScopeByCode(string $code): ScopeDTO
    {
        return $this->scopes->getByCode($code) ?? throw new ScopeNotFoundException($code);
    }

    /**
     * @return PageResult<ScopeDTO>
     */
    public function searchScopes(ScopeListCriteria $criteria): PageResult
    {
        return $this->scopes->search($criteria);
    }

    /**
     * @throws DomainNotFoundException
     */
    public function getDomain(int $id): DomainDTO
    {
        return $this->domains->getById($id) ?? throw new DomainNotFoundException((string) $id);
    }

    /**
     * @return PageResult<DomainDTO>
     */
    public function searchDomains(DomainListCriteria $criteria): PageResult
    {
        return $this->domains->search($criteria);
    }

    /**
     * Domains with their assignment flag for one scope.
     *
     * @return PageResult<DomainAssignmentDTO>
     */
    public function searchScopeDomains(ScopeDomainListCriteria $criteria): PageResult
    {
        return $this->domains->pageWithAssignment($criteria);
    }

    /**
     * Whether the domain is assigned to the scope.
     */
    public function isDomainAssigned(string $scopeCode, string $domainCode): bool
    {
        return $this->domainScopes->isDomainAllowedForScope($scopeCode, $domainCode);
    }

    /**
     * (code, name) of the domains assigned to the scope, for selectors.
     */
    public function listDomainOptionsForScope(string $scopeCode): DomainOptionCollectionDTO
    {
        return $this->domainScopes->listDomainOptionsForScope($scopeCode);
    }

    /**
     * @throws TranslationKeyNotFoundException
     */
    public function getKey(int $keyId): TranslationKeyDTO
    {
        return $this->keys->getById($keyId) ?? throw new TranslationKeyNotFoundException($keyId);
    }

    /**
     * @return PageResult<TranslationKeyDTO>
     */
    public function searchKeys(KeyListCriteria $criteria): PageResult
    {
        return $this->keys->search($criteria);
    }

    /**
     * @return PageResult<KeyTranslationSummaryDTO>
     */
    public function pageDomainKeySummaries(DomainKeySummaryCriteria $criteria): PageResult
    {
        return $this->queries->pageDomainKeySummaries($criteria);
    }

    /**
     * @return PageResult<TranslationGridRowDTO>
     */
    public function pageDomainTranslationGrid(DomainTranslationGridCriteria $criteria): PageResult
    {
        return $this->queries->pageDomainTranslationGrid($criteria);
    }

    /**
     * @return PageResult<LanguageTranslationValueDTO>
     */
    public function pageLanguageTranslationValues(LanguageTranslationValuesCriteria $criteria): PageResult
    {
        return $this->queries->pageLanguageTranslationValues($criteria);
    }
}
