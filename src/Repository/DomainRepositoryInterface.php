<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository;

use Maatify\I18n\DTO\DomainAssignmentDTO;
use Maatify\I18n\DTO\DomainCollectionDTO;
use Maatify\I18n\DTO\DomainDTO;
use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\DomainAlreadyExistsException;
use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\UpdateDomainMetadataCommand;
use Maatify\I18n\Management\Criteria\DomainListCriteria;
use Maatify\I18n\Management\Criteria\ScopeDomainListCriteria;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/**
 * Persistence boundary of the domain governance table.
 *
 * Failure contract: a genuine "no row" is null / empty; a storage failure is
 * never reported that way (thrown PDOException propagates unchanged, a
 * non-throwing failure state becomes I18nStorageException).
 */
interface DomainRepositoryInterface
{
    /**
     * @param LockModeEnum $lock SHARE/UPDATE require an active transaction
     */
    public function getByCode(string $code, LockModeEnum $lock = LockModeEnum::NONE): ?DomainDTO;

    /** Returns null only when no domain row has this ID. */
    public function getById(int $id, LockModeEnum $lock = LockModeEnum::NONE): ?DomainDTO;

    /**
     * Active domains in display order (bounded governance list).
     */
    public function listActive(): DomainCollectionDTO;

    /**
     * All domains in display order (bounded governance list).
     */
    public function listAll(): DomainCollectionDTO;

    /**
     * Active domains among the given codes, in display order.
     *
     * @param list<string> $codes
     */
    public function listByCodes(array $codes): DomainCollectionDTO;

    /**
     * @return PageResult<DomainDTO>
     */
    public function search(DomainListCriteria $criteria): PageResult;

    /**
     * Domains with their assignment flag for one scope (paginated). Both
     * tables are Package-owned; the scope code is supplied by the caller.
     *
     * @return PageResult<DomainAssignmentDTO>
     */
    public function pageWithAssignment(ScopeDomainListCriteria $criteria): PageResult;

    /**
     * @throws DomainAlreadyExistsException on a duplicate code (DB UNIQUE is the race authority)
     */
    public function create(CreateDomainCommand $command, int $sortOrder): int;

    /** Applies only the metadata fields supplied by the command. */
    public function updateMetadata(UpdateDomainMetadataCommand $command): void;

    /** Persists the domain's active state. */
    public function setActive(int $id, bool $isActive): void;

    /**
     * @throws DomainAlreadyExistsException on a duplicate code
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
     * @return bool false when the domain does not exist
     */
    public function moveToPosition(int $id, int $position): bool;
}
