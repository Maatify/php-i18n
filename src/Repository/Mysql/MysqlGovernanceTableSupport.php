<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository\Mysql;

use LogicException;
use Maatify\I18n\Enum\LockModeEnum;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingConfig;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingManager;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use Maatify\Persistence\Pdo\Pagination\PdoPaginator;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;

/**
 * Persistence mechanics shared by the two governance tables (scopes, domains).
 *
 * Ordering and pagination are delegated to maatify/persistence; this class only
 * supplies the trusted table identifiers and the domain query (filters, search,
 * selected columns, row mapping). The table name is a constant of the calling
 * repository, never user input.
 */
final readonly class MysqlGovernanceTableSupport
{
    private ScopedOrderingConfig $orderingConfig;

    /**
     * @param non-empty-string $table trusted constant of the calling repository
     */
    public function __construct(
        private PdoGateway $gateway,
        private string $table,
        private ScopedOrderingManager $ordering = new ScopedOrderingManager(),
        private PdoPaginator $paginator = new PdoPaginator(),
    ) {
        $this->orderingConfig = new ScopedOrderingConfig(
            table: $table,
            scopeColumn: null,
            idColumn: 'id',
            orderColumn: 'sort_order',
            deletedAtColumn: null,
        );
    }

    /**
     * Next display position (max + 1). The caller owns the transaction and
     * must hold {@see self::lockOrderingScope()} for concurrent creates.
     */
    public function nextPosition(): int
    {
        return $this->ordering->getNextPosition($this->gateway->pdo(), $this->orderingConfig);
    }

    /**
     * Caller-owned lock that serializes concurrent position allocation. It
     * takes the same locks, in the same index order (display position, then id),
     * as maatify/persistence takes for a move, so creates and moves contend on
     * the first row instead of deadlocking each other. Requires an active
     * transaction.
     *
     * An EMPTY ordering has no row to lock: concurrent first creates may make
     * InnoDB choose a deadlock victim (SQLSTATE 40001, propagated unchanged).
     */
    public function lockOrderingScope(): void
    {
        $this->assertInTransaction();

        $this->gateway->run(
            'SELECT id FROM ' . $this->table . ' ORDER BY sort_order ASC, id ASC FOR UPDATE',
            [],
            $this->table . '.lockOrdering',
        );
    }

    /**
     * @return bool false when the row does not exist (delegated unchanged)
     */
    public function moveToPosition(int $id, int $position): bool
    {
        return $this->ordering->moveWithinScope(
            $this->gateway->pdo(),
            $this->orderingConfig,
            null,
            $id,
            $position,
        );
    }

    /** Raise LogicException when a locking read has no active transaction. */
    public function assertInTransaction(): void
    {
        if (!$this->gateway->pdo()->inTransaction()) {
            throw new LogicException('A locking read requires an active transaction.');
        }
    }

    /**
     * Return the SQL suffix from LockModeEnum; NONE needs no transaction,
     * while SHARE/UPDATE require one and otherwise raise LogicException.
     */
    public function lockSuffix(LockModeEnum $lock): string
    {
        if ($lock !== LockModeEnum::NONE) {
            $this->assertInTransaction();
        }

        return $lock->sqlSuffix();
    }

    /**
     * @template TItem of array<array-key, mixed>|object
     *
     * @param list<string>                         $where   AND-ed trusted predicates (alias `s`)
     * @param array<string, string|int|bool|null>  $params  values bound by name
     * @param callable(array<string, mixed>): TItem $mapper
     *
     * @return PageResult<TItem>
     */
    public function page(
        string $selectColumns,
        string $from,
        array $where,
        array $params,
        PageRequest $request,
        callable $mapper,
        string $countFrom = '',
    ): PageResult {
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $countFrom = $countFrom === '' ? $from : $countFrom;

        $descriptor = new PdoPaginationQueryDescriptor(
            totalSql: 'SELECT COUNT(*) FROM ' . $this->table,
            totalParams: [],
            filteredCountSql: 'SELECT COUNT(*) ' . $countFrom . $whereSql,
            filteredCountParams: $params,
            dataSql: 'SELECT ' . $selectColumns . ' ' . $from . $whereSql,
            dataParams: $params,
        );

        $config = new PaginationConfig(
            sortWhitelist: new SortWhitelist([
                'id' => 's.id',
                'code' => 's.code',
                'name' => 's.name',
                'is_active' => 's.is_active',
                'sort_order' => 's.sort_order',
                'created_at' => 's.created_at',
            ]),
            defaultSortBy: 'sort_order',
            defaultSortDirection: SortDirectionEnum::ASC,
            tieBreakerSortBy: 'id',
            tieBreakerDirection: SortDirectionEnum::ASC,
        );

        return $this->paginator->paginate($this->gateway->pdo(), $descriptor, $request, $config, $mapper);
    }
}
