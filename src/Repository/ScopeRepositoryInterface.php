<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository;

use Maatify\I18n\DTO\ScopeCollectionDTO;
use Maatify\I18n\DTO\ScopeDTO;
use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\ScopeAlreadyExistsException;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Management\Command\UpdateScopeMetadataCommand;
use Maatify\I18n\Management\Criteria\ScopeListCriteria;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/**
 * Persistence boundary of the scope governance table.
 *
 * Failure contract: a genuine "no row" is null / empty; a storage failure is
 * never reported that way (thrown PDOException propagates unchanged, a
 * non-throwing failure state becomes I18nStorageException).
 */
interface ScopeRepositoryInterface
{
    /**
     * @param LockModeEnum $lock SHARE/UPDATE require an active transaction
     */
    public function getByCode(string $code, LockModeEnum $lock = LockModeEnum::NONE): ?ScopeDTO;

    /** Returns null only when no scope row has this ID. */
    public function getById(int $id, LockModeEnum $lock = LockModeEnum::NONE): ?ScopeDTO;

    /**
     * Active scopes in display order (bounded governance list).
     */
    public function listActive(): ScopeCollectionDTO;

    /**
     * All scopes in display order (bounded governance list).
     */
    public function listAll(): ScopeCollectionDTO;

    /**
     * @return PageResult<ScopeDTO>
     */
    public function search(ScopeListCriteria $criteria): PageResult;

    /**
     * @throws ScopeAlreadyExistsException on a duplicate code (DB UNIQUE is the race authority)
     */
    public function create(CreateScopeCommand $command, int $sortOrder): int;

    /** Applies only the metadata fields supplied by the command. */
    public function updateMetadata(UpdateScopeMetadataCommand $command): void;

    /** Persists the scope's active state. */
    public function setActive(int $id, bool $isActive): void;

    /**
     * @throws ScopeAlreadyExistsException on a duplicate code
     */
    public function changeCode(int $id, string $newCode): void;

    /**
     * Next display position; the caller holds {@see self::lockOrderingScope()}.
     */
    public function nextPosition(): int;

    /**
     * Caller-owned lock for concurrent position allocation (active transaction).
     */
    public function lockOrderingScope(): void;

    /**
     * Move to a display position through maatify/persistence ordering.
     *
     * @return bool false when the scope does not exist
     */
    public function moveToPosition(int $id, int $position): bool;
}
