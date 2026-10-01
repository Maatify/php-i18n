<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Service;

use Maatify\I18n\Repository\DomainRepositoryInterface;
use Maatify\I18n\Repository\DomainScopeRepositoryInterface;
use Maatify\I18n\DTO\DomainCollectionDTO;

/**
 * Coordinates I18n services and reads domain records through package repository contracts.
 */
final readonly class I18nDomainReadService
{
    public function __construct(
        private DomainRepositoryInterface $domainRepository,
        private DomainScopeRepositoryInterface $domainScopeRepository,
    ) {}

    /**
     * List domains allowed for a given scope.
     *
     * - FAIL-SOFT by design
     * - No exceptions on invalid scope
     * - Used by Admin UI (select domain by scope)
     */
    public function listDomainsForScope(string $scopeCode): DomainCollectionDTO
    {
        // 1) Get allowed domain codes for scope
        $domainCodes = $this->domainScopeRepository
            ->listDomainsForScope($scopeCode);

        if ($domainCodes === []) {
            return new DomainCollectionDTO([]);
        }

        // 2) Resolve full domain records
        return $this->domainRepository
            ->listByCodes($domainCodes);
    }
}
