<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Service;

use Maatify\I18n\Repository\ScopeRepositoryInterface;
use Maatify\I18n\DTO\ScopeCollectionDTO;

/**
 * Coordinates I18n services and reads scope records through package repository contracts.
 */
final readonly class I18nScopeReadService
{
    public function __construct(
        private ScopeRepositoryInterface $scopeRepository,
    ) {}

    /**
     * Read-only list of all scopes.
     *
     * - FAIL-SOFT by design
     * - No policy enforcement here
     * - Kernel returns full truth; UI decides what to render
     */
    public function listScopes(): ScopeCollectionDTO
    {
        return $this->scopeRepository->listAll();
    }

    /**
     * List only ACTIVE scopes.
     *
     * - Intended for UI selectors and runtime filtering
     * - FAIL-SOFT by design
     * - No policy enforcement (policy applies at domain level)
     */
    public function listActiveScopes(): ScopeCollectionDTO
    {
        return $this->scopeRepository->listActive();
    }

}
