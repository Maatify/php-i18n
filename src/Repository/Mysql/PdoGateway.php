<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository\Mysql;

use Maatify\I18n\Exception\I18nStorageException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * The single place where PDO result states are classified (storage-failure
 * contract of the Package).
 *
 * - A thrown PDOException is NEVER caught here: it propagates unchanged.
 * - A NON-throwing failure state (prepare/execute/fetch returning a failure
 *   value, which happens under PDO::ERRMODE_SILENT / WARNING) becomes an
 *   {@see I18nStorageException}. It can never masquerade as "no row",
 *   "empty collection" or a policy denial.
 * - A genuine "no row" is the only thing reported as null / empty / false.
 */
final readonly class PdoGateway
{
    /** MySQL/MariaDB driver code of a duplicate-key violation. */
    private const MYSQL_DUPLICATE_KEY = 1062;

    public function __construct(
        private PDO $pdo,
    ) {}

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Prepare + execute. Named placeholders only.
     *
     * @param array<string, scalar|null> $params
     *
     * @throws I18nStorageException on a non-throwing prepare/execute failure
     * @throws PDOException a thrown PDO failure propagates unchanged
     */
    public function run(string $sql, array $params, string $operation): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);

        if (!$stmt instanceof PDOStatement) {
            throw new I18nStorageException($operation . ':prepare');
        }

        if ($stmt->execute($params) === false) {
            throw new I18nStorageException($operation . ':execute');
        }

        return $stmt;
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @return array<string, mixed>|null null = a genuine "no row"
     *
     * @throws I18nStorageException
     * @throws PDOException a thrown PDO failure propagates unchanged
     */
    public function fetchOne(string $sql, array $params, string $operation): ?array
    {
        $stmt = $this->run($sql, $params, $operation);

        return $this->nextRow($stmt, $operation);
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @return list<array<string, mixed>>
     *
     * @throws I18nStorageException
     * @throws PDOException a thrown PDO failure propagates unchanged
     */
    public function fetchAll(string $sql, array $params, string $operation): array
    {
        $stmt = $this->run($sql, $params, $operation);
        $rows = [];

        while (($row = $this->nextRow($stmt, $operation)) !== null) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @throws I18nStorageException
     * @throws PDOException a thrown PDO failure propagates unchanged
     */
    public function exists(string $sql, array $params, string $operation): bool
    {
        $stmt = $this->run($sql, $params, $operation);

        /** @var array<int, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_NUM);

        if ($row === false) {
            $this->assertNoFetchError($stmt, $operation);

            return false;
        }

        return true;
    }

    /**
     * Single scalar (COUNT / MAX ...). A row is expected: its absence is a failure.
     *
     * @param array<string, scalar|null> $params
     *
     * @throws I18nStorageException
     * @throws PDOException a thrown PDO failure propagates unchanged
     */
    public function scalarInt(string $sql, array $params, string $operation): int
    {
        $stmt = $this->run($sql, $params, $operation);
        $value = $stmt->fetchColumn();

        if (!is_int($value) && !(is_string($value) && is_numeric($value))) {
            throw new I18nStorageException($operation . ':scalar');
        }

        return (int) $value;
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @return int affected rows
     *
     * @throws I18nStorageException
     * @throws PDOException a thrown PDO failure propagates unchanged
     */
    public function write(string $sql, array $params, string $operation): int
    {
        return $this->run($sql, $params, $operation)->rowCount();
    }

    /**
     * @throws I18nStorageException
     */
    public function lastInsertId(string $operation): int
    {
        $id = $this->pdo->lastInsertId();

        if (!is_string($id) || !ctype_digit($id)) {
            throw new I18nStorageException($operation . ':lastInsertId');
        }

        return (int) $id;
    }

    /**
     * True only for the MySQL/MariaDB duplicate-key violation (driver code
     * 1062). SQLSTATE 23000 alone is NOT sufficient evidence of a duplicate.
     */
    public static function isDuplicateKey(PDOException $e): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;

        return is_int($driverCode) && $driverCode === self::MYSQL_DUPLICATE_KEY;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nextRow(PDOStatement $stmt, string $operation): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            $this->assertNoFetchError($stmt, $operation);

            return null;
        }

        return $row;
    }

    /**
     * After fetch returns false, treat error code 00000 as result exhaustion;
     * any other statement error code raises I18nStorageException.
     */
    private function assertNoFetchError(PDOStatement $stmt, string $operation): void
    {
        if ($stmt->errorCode() !== '00000') {
            throw new I18nStorageException($operation . ':fetch');
        }
    }
}
