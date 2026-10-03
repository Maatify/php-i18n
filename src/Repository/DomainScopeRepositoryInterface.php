<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository;

use Maatify\I18n\DTO\DomainOptionCollectionDTO;
use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\DomainScopeAlreadyAssignedException;

/**
 * Persistence boundary of the domain <-> scope policy mapping.
 */
interface DomainScopeRepositoryInterface
{
    // ===== Read (Safe / Runtime) =====
    /** Tests an exact scope/domain assignment; optional locks require a transaction. */
    public function isDomainAllowedForScope(
        string $scopeCode,
        string $domainCode,
        LockModeEnum $lock = LockModeEnum::NONE,
    ): bool;

    /**
     * @return list<string> List of domain codes allowed for the given scope
     */
    public function listDomainsForScope(string $scopeCode): array;

    /**
     * (code, name) of the domains assigned to the scope, ordered by code.
     */
    public function listDomainOptionsForScope(string $scopeCode): DomainOptionCollectionDTO;

    /**
     * Whether any domain is assigned to the scope (usage check).
     */
    public function hasDomainsForScope(string $scopeCode, LockModeEnum $lock = LockModeEnum::NONE): bool;

    /**
     * Whether the domain is assigned to any scope (usage check).
     */
    public function hasScopesForDomain(string $domainCode, LockModeEnum $lock = LockModeEnum::NONE): bool;

    // ===== Write =====

    /**
     * @throws DomainScopeAlreadyAssignedException on a duplicate (DB UNIQUE is the race authority)
     */
    public function assign(string $scopeCode, string $domainCode): void;

    /** Returns whether the exact assignment row was deleted. */
    public function unassign(string $scopeCode, string $domainCode): bool;
}
