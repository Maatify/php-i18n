<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository;

use Maatify\I18n\DTO\TranslationKeyCollectionDTO;
use Maatify\I18n\DTO\TranslationKeyDTO;
use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\TranslationKeyAlreadyExistsException;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\RenameKeyCommand;
use Maatify\I18n\Management\Criteria\KeyListCriteria;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/**
 * Defines the persistence operations for translation key data used by I18n services.
 */
interface TranslationKeyRepositoryInterface
{
    /**
     * @return int id of the new key
     *
     * @throws TranslationKeyAlreadyExistsException on a duplicate identity (DB UNIQUE is the race authority)
     */
    public function create(CreateKeyCommand $command): int;

    /** Returns null only when no key row has this ID. */
    public function getById(int $id, LockModeEnum $lock = LockModeEnum::NONE): ?TranslationKeyDTO;

    /** Looks up the exact structured key identity without normalization. */
    public function getByStructuredKey(
        string $scope,
        string $domain,
        string $key,
    ): ?TranslationKeyDTO;

    /**
     * Replaces or clears the key description for the given key ID.
     *
     * @return bool whether the stored row changed
     */
    public function updateDescription(int $id, ?string $description): bool;

    /**
     * @throws TranslationKeyAlreadyExistsException on a duplicate identity
     */
    public function rename(RenameKeyCommand $command): void;

    /**
     * Whether any key uses the scope code (usage check, locking read capable).
     */
    public function existsForScope(string $scopeCode, LockModeEnum $lock = LockModeEnum::NONE): bool;

    /**
     * Whether any key uses the domain code (usage check, locking read capable).
     */
    public function existsForDomain(string $domainCode, LockModeEnum $lock = LockModeEnum::NONE): bool;

    /**
     * List all keys for a given (scope + domain). Bounded by one domain of one
     * scope; this is the runtime exact-read path.
     */
    public function listByScopeAndDomain(
        string $scope,
        string $domain,
    ): TranslationKeyCollectionDTO;

    /**
     * @return PageResult<TranslationKeyDTO>
     */
    public function search(KeyListCriteria $criteria): PageResult;
}
