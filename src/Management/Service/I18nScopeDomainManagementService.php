<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Service;

use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\DomainNotFoundException;
use Maatify\I18n\Exception\DomainScopeAlreadyAssignedException;
use Maatify\I18n\Exception\DomainScopeNotAssignedException;
use Maatify\I18n\Exception\ScopeNotFoundException;
use Maatify\I18n\Repository\DomainRepositoryInterface;
use Maatify\I18n\Repository\DomainScopeRepositoryInterface;
use Maatify\I18n\Repository\ScopeRepositoryInterface;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;

/**
 * Domain <-> scope assignment.
 *
 * Lock order is deterministic and shared with every other usage-creating
 * mutation: scope row (SHARE), domain row (SHARE), then the mapping row. A
 * concurrent scope / domain code change holds the UPDATE lock on its row, so it
 * serializes with an assignment instead of orphaning it.
 *
 * The UNIQUE (scope_code, domain_code) constraint is the final authority for a
 * duplicate assignment and surfaces as DomainScopeAlreadyAssignedException.
 */
final readonly class I18nScopeDomainManagementService
{
    public function __construct(
        private TransactionRunnerInterface $tx,
        private ScopeRepositoryInterface $scopes,
        private DomainRepositoryInterface $domains,
        private DomainScopeRepositoryInterface $domainScopes,
    ) {}

    /**
     * @throws ScopeNotFoundException
     * @throws DomainNotFoundException
     * @throws DomainScopeAlreadyAssignedException
     */
    public function assign(string $scopeCode, string $domainCode): void
    {
        $this->tx->run(function () use ($scopeCode, $domainCode): void {
            $this->lockBoth($scopeCode, $domainCode);

            if ($this->domainScopes->isDomainAllowedForScope($scopeCode, $domainCode, LockModeEnum::SHARE)) {
                throw new DomainScopeAlreadyAssignedException($scopeCode, $domainCode);
            }

            $this->domainScopes->assign($scopeCode, $domainCode);
        });
    }

    /**
     * @throws ScopeNotFoundException
     * @throws DomainNotFoundException
     * @throws DomainScopeNotAssignedException
     */
    public function unassign(string $scopeCode, string $domainCode): void
    {
        $this->tx->run(function () use ($scopeCode, $domainCode): void {
            $this->lockBoth($scopeCode, $domainCode);

            if (!$this->domainScopes->isDomainAllowedForScope($scopeCode, $domainCode, LockModeEnum::UPDATE)) {
                throw new DomainScopeNotAssignedException($scopeCode, $domainCode);
            }

            $this->domainScopes->unassign($scopeCode, $domainCode);
        });
    }

    /**
     * @throws ScopeNotFoundException
     * @throws DomainNotFoundException
     */
    private function lockBoth(string $scopeCode, string $domainCode): void
    {
        if ($this->scopes->getByCode($scopeCode, LockModeEnum::SHARE) === null) {
            throw new ScopeNotFoundException($scopeCode);
        }

        if ($this->domains->getByCode($domainCode, LockModeEnum::SHARE) === null) {
            throw new DomainNotFoundException($domainCode);
        }
    }
}
