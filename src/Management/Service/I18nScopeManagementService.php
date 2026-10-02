<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Service;

use Maatify\I18n\DTO\ScopeDTO;
use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\ScopeAlreadyExistsException;
use Maatify\I18n\Exception\ScopeInUseException;
use Maatify\I18n\Exception\ScopeNotFoundException;
use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Management\Command\UpdateScopeMetadataCommand;
use Maatify\I18n\Repository\DomainScopeRepositoryInterface;
use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\I18n\Repository\ScopeRepositoryInterface;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;

/**
 * Scope governance mutations (create, metadata, active state, code change, ordering).
 *
 * Transaction owner: this service (persistence TransactionRunnerInterface; a
 * caller-owned outer transaction is joined, never committed / rolled back).
 *
 * Code change: the target governance row is locked FOR UPDATE first, then
 * usage (domain mappings + keys) is checked with locking reads, and only then
 * the code changes - all in one transaction. Mutations that create usage of
 * this scope (key create / move, scope-domain assign) take SHARE locks on the
 * same row before they decide, so they serialize with a code change instead
 * of racing it.
 *
 * Ordering is delegated to maatify/persistence (ScopedOrderingManager).
 */
final readonly class I18nScopeManagementService
{
    public function __construct(
        private TransactionRunnerInterface $tx,
        private ScopeRepositoryInterface $repository,
        private DomainScopeRepositoryInterface $domainScopes,
        private TranslationKeyRepositoryInterface $keys,
    ) {}

    /**
     * @return int id of the new scope (appended to the display order)
     *
     * @throws ScopeAlreadyExistsException
     */
    public function create(CreateScopeCommand $command): int
    {
        return $this->tx->run(function () use ($command): int {
            // Serialize concurrent position allocation (caller-owned lock for
            // ScopedOrderingManager::getNextPosition()).
            $this->repository->lockOrderingScope();

            return $this->repository->create($command, $this->repository->nextPosition());
        });
    }

    /**
     * @throws ScopeNotFoundException
     * @throws I18nInvalidArgumentException when id is not positive
     */
    public function updateMetadata(UpdateScopeMetadataCommand $command): void
    {
        $this->tx->run(function () use ($command): void {
            $current = $this->lockOrFail($command->id);

            if (($command->name === null || $command->name === $current->name)
                && ($command->description === null || $command->description === $current->description)) {
                return;
            }

            if (!$this->repository->updateMetadata($command)) {
                throw new ScopeNotFoundException((string) $command->id);
            }
        });
    }

    /**
     * @throws ScopeNotFoundException
     */
    public function setActive(int $id, bool $isActive): void
    {
        if ($id <= 0) {
            throw I18nInvalidArgumentException::notPositive('id');
        }

        $this->tx->run(function () use ($id, $isActive): void {
            $current = $this->lockOrFail($id);

            if ($current->isActive === $isActive) {
                return;
            }

            if (!$this->repository->setActive($id, $isActive)) {
                throw new ScopeNotFoundException((string) $id);
            }
        });
    }

    /**
     * @throws ScopeNotFoundException
     * @throws ScopeInUseException  the current code is used by mappings or keys
     * @throws ScopeAlreadyExistsException the new code is taken
     * @throws I18nInvalidArgumentException when id or newCode is invalid
     */
    public function changeCode(int $id, string $newCode): void
    {
        if ($id <= 0) {
            throw I18nInvalidArgumentException::notPositive('id');
        }

        if (trim($newCode) === '') {
            throw I18nInvalidArgumentException::emptyField('newCode');
        }

        if (mb_strlen($newCode) > 32) {
            throw I18nInvalidArgumentException::tooLong('newCode', 32);
        }

        $this->tx->run(function () use ($id, $newCode): void {
            $current = $this->lockOrFail($id);

            // Locking reads: they see the latest committed usage even inside an
            // outer transaction that already holds an older snapshot.
            if ($this->domainScopes->hasDomainsForScope($current->code, LockModeEnum::SHARE)
                || $this->keys->existsForScope($current->code, LockModeEnum::SHARE)) {
                throw new ScopeInUseException($current->code);
            }

            $this->repository->changeCode($id, $newCode);
        });
    }

    /**
     * Move to a display position (clamped by maatify/persistence).
     *
     * @throws ScopeNotFoundException
     */
    public function moveToPosition(int $id, int $position): void
    {
        if (!$this->repository->moveToPosition($id, $position)) {
            throw new ScopeNotFoundException((string) $id);
        }
    }

    /**
     * @throws ScopeNotFoundException
     */
    private function lockOrFail(int $id): ScopeDTO
    {
        $row = $this->repository->getById($id, LockModeEnum::UPDATE);

        if ($row === null) {
            throw new ScopeNotFoundException((string) $id);
        }

        return $row;
    }
}
