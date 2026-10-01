<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository\Mysql;

use Maatify\I18n\DTO\DomainAssignmentDTO;
use Maatify\I18n\DTO\DomainCollectionDTO;
use Maatify\I18n\DTO\DomainDTO;
use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\DomainAlreadyExistsException;
use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\UpdateDomainMetadataCommand;
use Maatify\I18n\Management\Criteria\DomainListCriteria;
use Maatify\I18n\Management\Criteria\ScopeDomainListCriteria;
use Maatify\I18n\Repository\DomainRepositoryInterface;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use PDO;
use PDOException;

/**
 * Persists and reads domain records through the package-owned MySQL schema.
 */
final readonly class MysqlDomainRepository implements DomainRepositoryInterface
{
    private const COLUMNS = 'id, code, name, description, is_active, sort_order, created_at';

    private PdoGateway $gateway;
    private MysqlGovernanceTableSupport $support;

    public function __construct(PDO $pdo)
    {
        $this->gateway = new PdoGateway($pdo);
        $this->support = new MysqlGovernanceTableSupport($this->gateway, 'maa_i18n_domains');
    }

    public function getByCode(string $code, LockModeEnum $lock = LockModeEnum::NONE): ?DomainDTO
    {
        $row = $this->gateway->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM maa_i18n_domains WHERE code = :code LIMIT 1'
            . $this->support->lockSuffix($lock),
            ['code' => $code],
            'domain.getByCode',
        );

        return $row === null ? null : $this->map($row);
    }

    public function getById(int $id, LockModeEnum $lock = LockModeEnum::NONE): ?DomainDTO
    {
        $row = $this->gateway->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM maa_i18n_domains WHERE id = :id LIMIT 1'
            . $this->support->lockSuffix($lock),
            ['id' => $id],
            'domain.getById',
        );

        return $row === null ? null : $this->map($row);
    }

    public function listActive(): DomainCollectionDTO
    {
        return $this->listByCondition('WHERE is_active = 1', 'domain.listActive');
    }

    public function listAll(): DomainCollectionDTO
    {
        return $this->listByCondition('', 'domain.listAll');
    }

    public function listByCodes(array $codes): DomainCollectionDTO
    {
        $codes = array_values(array_unique($codes));

        if ($codes === []) {
            return new DomainCollectionDTO([]);
        }

        $params = [];
        $placeholders = [];
        foreach ($codes as $i => $code) {
            $placeholders[] = ':c' . $i;
            $params['c' . $i] = $code;
        }

        $rows = $this->gateway->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM maa_i18n_domains
             WHERE code IN (' . implode(',', $placeholders) . ') AND is_active = 1
             ORDER BY sort_order ASC, code ASC',
            $params,
            'domain.listByCodes',
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->map($row);
        }

        return new DomainCollectionDTO($items);
    }

    public function pageWithAssignment(ScopeDomainListCriteria $criteria): PageResult
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

        if ($criteria->assigned === true) {
            $where[] = 'ds.domain_code IS NOT NULL';
        } elseif ($criteria->assigned === false) {
            $where[] = 'ds.domain_code IS NULL';
        }

        // The assignment JOIN is part of the data AND filtered-count queries, so
        // every placeholder is bound once per statement and both stay aligned.
        $join = 'LEFT JOIN maa_i18n_domain_scopes ds
                    ON ds.domain_code = s.code
                   AND ds.scope_code = :scope_code';
        $params['scope_code'] = $criteria->scopeCode;

        return $this->support->page(
            's.id, s.code, s.name, s.description, s.is_active, s.sort_order,
             CASE WHEN ds.domain_code IS NULL THEN 0 ELSE 1 END AS assigned',
            'FROM maa_i18n_domains s ' . $join,
            $where,
            $params,
            $criteria->page,
            fn(array $row): DomainAssignmentDTO => new DomainAssignmentDTO(
                Row::int($row, 'id'),
                Row::string($row, 'code'),
                Row::string($row, 'name'),
                Row::nullableString($row, 'description'),
                Row::bool($row, 'is_active'),
                Row::int($row, 'sort_order'),
                Row::bool($row, 'assigned'),
            ),
        );
    }

    public function search(DomainListCriteria $criteria): PageResult
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
            'FROM maa_i18n_domains s',
            $where,
            $params,
            $criteria->page,
            fn(array $row): DomainDTO => $this->map($row),
        );
    }

    public function create(CreateDomainCommand $command, int $sortOrder): int
    {
        try {
            $this->gateway->write(
                'INSERT INTO maa_i18n_domains (code, name, description, is_active, sort_order)
                 VALUES (:code, :name, :description, :is_active, :sort_order)',
                ([
                    'code' => $command->code,
                    'name' => $command->name,
                    'description' => $command->description,
                    'is_active' => $command->isActive ? 1 : 0,
                    'sort_order' => $sortOrder,
                ]),
                'domain.create',
            );
        } catch (PDOException $e) {
            if (PdoGateway::isDuplicateKey($e)) {
                throw new DomainAlreadyExistsException($command->code);
            }

            throw $e;
        }

        return $this->gateway->lastInsertId('domain.create');
    }

    public function updateMetadata(UpdateDomainMetadataCommand $command): void
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
            'UPDATE maa_i18n_domains SET ' . implode(', ', $fields) . ' WHERE id = :id',
            $params,
            'domain.updateMetadata',
        );
    }

    public function setActive(int $id, bool $isActive): void
    {
        $this->gateway->write(
            'UPDATE maa_i18n_domains SET is_active = :is_active WHERE id = :id',
            ['id' => $id, 'is_active' => $isActive ? 1 : 0],
            'domain.setActive',
        );
    }

    public function changeCode(int $id, string $newCode): void
    {
        try {
            $this->gateway->write(
                'UPDATE maa_i18n_domains SET code = :code WHERE id = :id',
                ['id' => $id, 'code' => $newCode],
                'domain.changeCode',
            );
        } catch (PDOException $e) {
            if (PdoGateway::isDuplicateKey($e)) {
                throw new DomainAlreadyExistsException($newCode);
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

    private function listByCondition(string $where, string $operation): DomainCollectionDTO
    {
        $rows = $this->gateway->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM maa_i18n_domains ' . $where . ' ORDER BY sort_order ASC, id ASC',
            [],
            $operation,
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->map($row);
        }

        return new DomainCollectionDTO($items);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): DomainDTO
    {
        return new DomainDTO(
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
