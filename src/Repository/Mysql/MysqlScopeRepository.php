<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository\Mysql;

use Maatify\I18n\DTO\ScopeCollectionDTO;
use Maatify\I18n\DTO\ScopeDTO;
use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\ScopeAlreadyExistsException;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Management\Command\UpdateScopeMetadataCommand;
use Maatify\I18n\Management\Criteria\ScopeListCriteria;
use Maatify\I18n\Repository\ScopeRepositoryInterface;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use PDO;
use PDOException;

/**
 * Persists and reads scope records through the package-owned MySQL schema.
 */
final readonly class MysqlScopeRepository implements ScopeRepositoryInterface
{
    private const COLUMNS = 'id, code, name, description, is_active, sort_order, created_at';

    private PdoGateway $gateway;
    private MysqlGovernanceTableSupport $support;

    public function __construct(PDO $pdo)
    {
        $this->gateway = new PdoGateway($pdo);
        $this->support = new MysqlGovernanceTableSupport($this->gateway, 'maa_i18n_scopes');
    }

    public function getByCode(string $code, LockModeEnum $lock = LockModeEnum::NONE): ?ScopeDTO
    {
        $row = $this->gateway->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM maa_i18n_scopes WHERE code = :code LIMIT 1'
            . $this->support->lockSuffix($lock),
            ['code' => $code],
            'scope.getByCode',
        );

        return $row === null ? null : $this->map($row);
    }

    public function getById(int $id, LockModeEnum $lock = LockModeEnum::NONE): ?ScopeDTO
    {
        $row = $this->gateway->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM maa_i18n_scopes WHERE id = :id LIMIT 1'
            . $this->support->lockSuffix($lock),
            ['id' => $id],
            'scope.getById',
        );

        return $row === null ? null : $this->map($row);
    }

    public function listActive(): ScopeCollectionDTO
    {
        return $this->listByCondition('WHERE is_active = 1', 'scope.listActive');
    }

    public function listAll(): ScopeCollectionDTO
    {
        return $this->listByCondition('', 'scope.listAll');
    }

    public function search(ScopeListCriteria $criteria): PageResult
    {
        $where = [];
        $params = [];

        if ($criteria->globalSearch !== null && trim($criteria->globalSearch) !== '') {
            $like = Row::like(trim($criteria->globalSearch));
            $where[] = '(s.code LIKE :g_code OR s.name LIKE :g_name OR s.description LIKE :g_description)';
            $params['g_code'] = $like;
            $params['g_name'] = $like;
            $params['g_description'] = $like;
        }

        if ($criteria->id !== null) {
            $where[] = 's.id = :id';
            $params['id'] = $criteria->id;
        }

        if ($criteria->code !== null) {
            $where[] = 's.code = :code';
            $params['code'] = trim($criteria->code);
        }

        if ($criteria->name !== null) {
            $where[] = 's.name = :name';
            $params['name'] = trim($criteria->name);
        }

        if ($criteria->isActive !== null) {
            $where[] = 's.is_active = :is_active';
            $params['is_active'] = $criteria->isActive ? 1 : 0;
        }

        return $this->support->page(
            's.id, s.code, s.name, s.description, s.is_active, s.sort_order, s.created_at',
            'FROM maa_i18n_scopes s',
            $where,
            $params,
            $criteria->page,
            fn(array $row): ScopeDTO => $this->map($row),
        );
    }

    public function create(CreateScopeCommand $command, int $sortOrder): int
    {
        try {
            $this->gateway->write(
                'INSERT INTO maa_i18n_scopes (code, name, description, is_active, sort_order)
                 VALUES (:code, :name, :description, :is_active, :sort_order)',
                ([
                    'code' => $command->code,
                    'name' => $command->name,
                    'description' => $command->description,
                    'is_active' => $command->isActive ? 1 : 0,
                    'sort_order' => $sortOrder,
                ]),
                'scope.create',
            );
        } catch (PDOException $e) {
            if (PdoGateway::isDuplicateKey($e)) {
                throw new ScopeAlreadyExistsException($command->code);
            }

            throw $e;
        }

        return $this->gateway->lastInsertId('scope.create');
    }

    public function updateMetadata(UpdateScopeMetadataCommand $command): void
    {
        $fields = [];
        $params = ['id' => $command->id];

        if ($command->name !== null) {
            $fields[] = 'name = :name';
            $params['name'] = $command->name;
        }

        if ($command->description !== null) {
            $fields[] = 'description = :description';
            $params['description'] = $command->description;
        }

        $this->gateway->write(
            'UPDATE maa_i18n_scopes SET ' . implode(', ', $fields) . ' WHERE id = :id',
            $params,
            'scope.updateMetadata',
        );
    }

    public function setActive(int $id, bool $isActive): void
    {
        $this->gateway->write(
            'UPDATE maa_i18n_scopes SET is_active = :is_active WHERE id = :id',
            ['id' => $id, 'is_active' => $isActive ? 1 : 0],
            'scope.setActive',
        );
    }

    public function changeCode(int $id, string $newCode): void
    {
        try {
            $this->gateway->write(
                'UPDATE maa_i18n_scopes SET code = :code WHERE id = :id',
                ['id' => $id, 'code' => $newCode],
                'scope.changeCode',
            );
        } catch (PDOException $e) {
            if (PdoGateway::isDuplicateKey($e)) {
                throw new ScopeAlreadyExistsException($newCode);
            }

            throw $e;
        }
    }

    public function nextPosition(): int
    {
        return $this->support->nextPosition();
    }

    public function lockOrderingScope(): void
    {
        $this->support->lockOrderingScope();
    }

    public function moveToPosition(int $id, int $position): bool
    {
        return $this->support->moveToPosition($id, $position);
    }

    private function listByCondition(string $where, string $operation): ScopeCollectionDTO
    {
        $rows = $this->gateway->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM maa_i18n_scopes ' . $where . ' ORDER BY sort_order ASC, id ASC',
            [],
            $operation,
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->map($row);
        }

        return new ScopeCollectionDTO($items);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): ScopeDTO
    {
        return new ScopeDTO(
            Row::int($row, 'id'),
            Row::string($row, 'code'),
            Row::string($row, 'name'),
            Row::nullableString($row, 'description'),
            Row::bool($row, 'is_active'),
            Row::int($row, 'sort_order'),
            Row::string($row, 'created_at'),
        );
    }
}
