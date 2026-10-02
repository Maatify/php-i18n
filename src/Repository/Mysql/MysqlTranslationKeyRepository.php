<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository\Mysql;

use LogicException;
use Maatify\I18n\DTO\TranslationKeyCollectionDTO;
use Maatify\I18n\DTO\TranslationKeyDTO;
use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\TranslationKeyAlreadyExistsException;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\RenameKeyCommand;
use Maatify\I18n\Management\Criteria\KeyListCriteria;
use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use Maatify\Persistence\Pdo\Pagination\PdoPaginator;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;
use PDO;
use PDOException;

/**
 * Persists and reads translation key records through the package-owned MySQL schema.
 */
final readonly class MysqlTranslationKeyRepository implements TranslationKeyRepositoryInterface
{
    private const COLUMNS = 'id, scope, domain, key_part, description, created_at';

    private PdoGateway $gateway;

    public function __construct(
        PDO $pdo,
        private PdoPaginator $paginator = new PdoPaginator(),
    ) {
        $this->gateway = new PdoGateway($pdo);
    }

    /**
     * Insert the exact structured key and return its new ID; a duplicate
     * identity is classified as TranslationKeyAlreadyExistsException.
     *
     * @throws TranslationKeyAlreadyExistsException
     */
    public function create(CreateKeyCommand $command): int
    {
        try {
            $this->gateway->write(
                'INSERT INTO maa_i18n_keys (scope, domain, key_part, description)
                 VALUES (:scope, :domain, :key, :description)',
                ([
                    'scope' => $command->scope,
                    'domain' => $command->domain,
                    'key' => $command->key,
                    'description' => $command->description,
                ]),
                'key.create',
            );
        } catch (PDOException $e) {
            if (PdoGateway::isDuplicateKey($e)) {
                throw new TranslationKeyAlreadyExistsException($command->scope, $command->domain, $command->key);
            }

            throw $e;
        }

        return $this->gateway->lastInsertId('key.create');
    }

    /**
     * Read a key by ID, returning null only when no row matches. Non-NONE lock
     * modes require an active transaction.
     */
    public function getById(int $id, LockModeEnum $lock = LockModeEnum::NONE): ?TranslationKeyDTO
    {
        $row = $this->gateway->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM maa_i18n_keys WHERE id = :id LIMIT 1' . $this->lockSuffix($lock),
            ['id' => $id],
            'key.getById',
        );

        return $row === null ? null : $this->map($row);
    }

    /** Look up the exact structured identity without normalization; return null when it is absent. */
    public function getByStructuredKey(
        string $scope,
        string $domain,
        string $key,
    ): ?TranslationKeyDTO {
        $row = $this->gateway->fetchOne(
            'SELECT ' . self::COLUMNS . '
             FROM maa_i18n_keys
             WHERE scope = :scope
               AND domain = :domain
               AND key_part = :key
             LIMIT 1',
            ['scope' => $scope, 'domain' => $domain, 'key' => $key],
            'key.getByStructuredKey',
        );

        return $row === null ? null : $this->map($row);
    }

    /** Replace or clear the description; true means the SQL update changed a row. */
    public function updateDescription(int $id, ?string $description): bool
    {
        return $this->gateway->write(
            'UPDATE maa_i18n_keys SET description = :description WHERE id = :id',
            ['id' => $id, 'description' => $description],
            'key.updateDescription',
        ) > 0;
    }

    /** Rename the key's exact structured identity; duplicate identity raises TranslationKeyAlreadyExistsException. */
    public function rename(RenameKeyCommand $command): void
    {
        try {
            $this->gateway->write(
                'UPDATE maa_i18n_keys
                 SET scope = :scope,
                     domain = :domain,
                     key_part = :key
                 WHERE id = :id',
                ([
                    'id' => $command->keyId,
                    'scope' => $command->scope,
                    'domain' => $command->domain,
                    'key' => $command->key,
                ]),
                'key.rename',
            );
        } catch (PDOException $e) {
            if (PdoGateway::isDuplicateKey($e)) {
                throw new TranslationKeyAlreadyExistsException($command->scope, $command->domain, $command->key);
            }

            throw $e;
        }
    }

    /** Test whether any key uses this exact scope code; non-NONE lock modes require an active transaction. */
    public function existsForScope(string $scopeCode, LockModeEnum $lock = LockModeEnum::NONE): bool
    {
        return $this->gateway->exists(
            'SELECT 1 FROM maa_i18n_keys WHERE scope = :code LIMIT 1' . $this->lockSuffix($lock),
            ['code' => $scopeCode],
            'key.existsForScope',
        );
    }

    /** Test whether any key uses this exact domain code; non-NONE lock modes require an active transaction. */
    public function existsForDomain(string $domainCode, LockModeEnum $lock = LockModeEnum::NONE): bool
    {
        return $this->gateway->exists(
            'SELECT 1 FROM maa_i18n_keys WHERE domain = :code LIMIT 1' . $this->lockSuffix($lock),
            ['code' => $domainCode],
            'key.existsForDomain',
        );
    }

    /** Return keys for this exact scope/domain ordered by key part; no matches yields an empty collection. */
    public function listByScopeAndDomain(
        string $scope,
        string $domain,
    ): TranslationKeyCollectionDTO {
        $rows = $this->gateway->fetchAll(
            'SELECT ' . self::COLUMNS . '
             FROM maa_i18n_keys
             WHERE scope = :scope
               AND domain = :domain
             ORDER BY key_part ASC',
            ['scope' => $scope, 'domain' => $domain],
            'key.listByScopeAndDomain',
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->map($row);
        }

        return new TranslationKeyCollectionDTO($items);
    }

    /**
     * Return a page of keys constrained to the criteria's scope. The total is
     * the global key population; the filtered count includes the required scope
     * and optional filters. Default order is ID ascending.
     *
     * @return PageResult<TranslationKeyDTO>
     */
    public function search(KeyListCriteria $criteria): PageResult
    {
        $where = ['k.scope = :scope'];
        $params = ['scope' => $criteria->scopeCode];

        if ($criteria->globalSearch !== null && trim($criteria->globalSearch) !== '') {
            $like = Row::like(trim($criteria->globalSearch));
            $where[] = '(k.domain LIKE :g_domain OR k.key_part LIKE :g_key)';
            $params['g_domain'] = $like;
            $params['g_key'] = $like;
        }

        if ($criteria->id !== null) {
            $where[] = 'k.id = :id';
            $params['id'] = $criteria->id;
        }

        if ($criteria->domainLike !== null) {
            $where[] = 'k.domain LIKE :domain_like';
            $params['domain_like'] = Row::like(trim($criteria->domainLike));
        }

        if ($criteria->keyPartLike !== null) {
            $where[] = 'k.key_part LIKE :key_part_like';
            $params['key_part_like'] = Row::like(trim($criteria->keyPartLike));
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $descriptor = new PdoPaginationQueryDescriptor(
            // Admin response contract: `total` is the GLOBAL unfiltered key population;
            // `filtered` carries the mandatory scope constraint + optional filters.
            totalSql: 'SELECT COUNT(*) FROM maa_i18n_keys',
            totalParams: [],
            filteredCountSql: 'SELECT COUNT(*) FROM maa_i18n_keys k' . $whereSql,
            filteredCountParams: $params,
            dataSql: 'SELECT k.id, k.scope, k.domain, k.key_part, k.description, k.created_at
                      FROM maa_i18n_keys k' . $whereSql,
            dataParams: $params,
        );

        $config = new PaginationConfig(
            sortWhitelist: new SortWhitelist([
                'id' => 'k.id',
                'domain' => 'k.domain',
                'key_part' => 'k.key_part',
                'created_at' => 'k.created_at',
            ]),
            defaultSortBy: 'id',
            defaultSortDirection: SortDirectionEnum::ASC,
            tieBreakerSortBy: 'id',
            tieBreakerDirection: SortDirectionEnum::ASC,
        );

        return $this->paginator->paginate(
            $this->gateway->pdo(),
            $descriptor,
            $criteria->page,
            $config,
            fn(array $row): TranslationKeyDTO => $this->map($row),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): TranslationKeyDTO
    {
        return new TranslationKeyDTO(
            Row::int($row, 'id'),
            Row::string($row, 'scope'),
            Row::string($row, 'domain'),
            Row::string($row, 'key_part'),
            Row::nullableString($row, 'description'),
            Row::string($row, 'created_at'),
        );
    }

    /**
     * Return the enum's SQL lock suffix; non-NONE modes require an active
     * transaction and otherwise raise LogicException.
     */
    private function lockSuffix(LockModeEnum $lock): string
    {
        if ($lock !== LockModeEnum::NONE && !$this->gateway->pdo()->inTransaction()) {
            throw new LogicException('A locking read requires an active transaction.');
        }

        return $lock->sqlSuffix();
    }
}
