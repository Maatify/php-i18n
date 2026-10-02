<?php

declare(strict_types=1);

namespace Maatify\I18n\Management\Service;

use Maatify\I18n\DTO\DomainDTO;
use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\DomainAlreadyExistsException;
use Maatify\I18n\Exception\DomainInUseException;
use Maatify\I18n\Exception\DomainNotFoundException;
use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\UpdateDomainMetadataCommand;
use Maatify\I18n\Repository\DomainScopeRepositoryInterface;
use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\I18n\Repository\DomainRepositoryInterface;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;

/**
 * Domain governance mutations (create, metadata, active state, code change, ordering).
 *
 * Transaction owner: this service (persistence TransactionRunnerInterface; a
 * caller-owned outer transaction is joined, never committed / rolled back).
 *
 * Code change: the target governance row is locked FOR UPDATE first, then
 * usage (domain mappings + keys) is checked with locking reads, and only then
 * the code changes - all in one transaction. Mutations that create usage of
 * this domain (key create / move, scope-domain assign) take SHARE locks on the
 * same row before they decide, so they serialize with a code change instead
 * of racing it.
 *
 * Ordering is delegated to maatify/persistence (ScopedOrderingManager).
 */
final readonly class I18nDomainManagementService
{
    public function __construct(
        private TransactionRunnerInterface $tx,
        private DomainRepositoryInterface $repository,
        private DomainScopeRepositoryInterface $domainScopes,
        private TranslationKeyRepositoryInterface $keys,
    ) {}

    /**
     * @return int id of the new domain (appended to the display order)
     *
     * @throws DomainAlreadyExistsException
     */
    public function create(CreateDomainCommand $command): int
    {
        return $this->tx->run(function () use ($command): int {
            // Serialize concurrent position allocation (caller-owned lock for
            // ScopedOrderingManager::getNextPosition()).
            $this->repository->lockOrderingScope();

            return $this->repository->create($command, $this->repository->nextPosition());
        });
    }

    /**
     * @throws DomainNotFoundException
     * @throws I18nInvalidArgumentException when id is not positive
     */
    public function updateMetadata(UpdateDomainMetadataCommand $command): void
    {
        $this->tx->run(function () use ($command): void {
            $current = $this->lockOrFail($command->id);

            if (($command->name === null || $command->name === $current->name)
                && ($command->description === null || $command->description === $current->description)) {
                return;
            }

            if (!$this->repository->updateMetadata($command)) {
                throw new DomainNotFoundException((string) $command->id);
            }
        });
    }

    /**
     * Persist the domain's active state; a non-positive ID raises
     * I18nInvalidArgumentException, setting the current state is a successful
     * no-op, and a missing domain raises DomainNotFoundException.
     *
     * @throws DomainNotFoundException
     * @throws I18nInvalidArgumentException when id is not positive
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
                throw new DomainNotFoundException((string) $id);
            }
        });
    }

    /**
     * @throws DomainNotFoundException
     * @throws DomainInUseException  the current code is used by mappings or keys
     * @throws DomainAlreadyExistsException the new code is taken
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

        if (mb_strlen($newCode) > 64) {
            throw I18nInvalidArgumentException::tooLong('newCode', 64);
        }

        $this->tx->run(function () use ($id, $newCode): void {
            $current = $this->lockOrFail($id);

            // Locking reads: they see the latest committed usage even inside an
            // outer transaction that already holds an older snapshot.
            if ($this->domainScopes->hasScopesForDomain($current->code, LockModeEnum::SHARE)
                || $this->keys->existsForDomain($current->code, LockModeEnum::SHARE)) {
                throw new DomainInUseException($current->code);
            }

            $this->repository->changeCode($id, $newCode);
        });
    }

    /**
     * Move to a display position (clamped by maatify/persistence).
     *
     * @throws DomainNotFoundException
     */
    public function moveToPosition(int $id, int $position): void
    {
        if (!$this->repository->moveToPosition($id, $position)) {
            throw new DomainNotFoundException((string) $id);
        }
    }

    /**
     * @throws DomainNotFoundException
     */
    private function lockOrFail(int $id): DomainDTO
    {
        $row = $this->repository->getById($id, LockModeEnum::UPDATE);

        if ($row === null) {
            throw new DomainNotFoundException((string) $id);
        }

        return $row;
    }
}
