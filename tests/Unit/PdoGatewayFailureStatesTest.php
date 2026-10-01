<?php

/**
 * @copyright   ©2026 Maatify.dev
 * @Library     maatify/i18n
 * @Project     maatify:i18n
 * @author      Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @since       2026-10-01 00:00
 * @see         https://www.maatify.dev Maatify.dev
 * @link        https://github.com/Maatify/i18n view Project on GitHub
 * @note        Distributed in the hope that it will be useful - WITHOUT WARRANTY.
 */

declare(strict_types=1);

namespace Maatify\I18n\Tests\Unit;

use Maatify\I18n\Exception\I18nExceptionInterface;
use Maatify\I18n\Exception\I18nStorageException;
use Maatify\I18n\Repository\Mysql\PdoGateway;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * GA-F06 at the lowest level, without any database: how PDO result states are
 * classified. A genuine "no row" is the ONLY thing that may become null / empty
 * / false; every non-throwing failure state is an I18nStorageException, and a
 * thrown PDOException is never touched.
 */
final class PdoGatewayFailureStatesTest extends TestCase
{
    private static function gateway(?PDOStatement $stmt, bool $prepareFails = false): PdoGateway
    {
        return new PdoGateway(new class ($stmt, $prepareFails) extends PDO {
            public function __construct(private readonly ?PDOStatement $stmt, private readonly bool $prepareFails) {}

            /**
             * @param array<array-key, mixed> $options
             */
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                return $this->prepareFails || $this->stmt === null ? false : $this->stmt;
            }
        });
    }

    private static function statement(bool $executeOk, mixed $fetchResult, string $errorCode): PDOStatement
    {
        return new class ($executeOk, $fetchResult, $errorCode) extends PDOStatement {
            public function __construct(
                private readonly bool $executeOk,
                private readonly mixed $fetchResult,
                private readonly string $code,
            ) {}

            /**
             * @param array<array-key, mixed>|null $params
             */
            public function execute(?array $params = null): bool
            {
                return $this->executeOk;
            }

            public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
            {
                return $this->fetchResult;
            }

            public function errorCode(): string
            {
                return $this->code;
            }
        };
    }

    public function testAGenuineNoRowIsNullEmptyOrFalse(): void
    {
        $gateway = self::gateway(self::statement(true, false, '00000'));

        self::assertNull($gateway->fetchOne('SELECT 1', [], 'op'));
        self::assertSame([], $gateway->fetchAll('SELECT 1', [], 'op'));
        self::assertFalse($gateway->exists('SELECT 1', [], 'op'));
    }

    public function testARowIsReturnedAsIs(): void
    {
        $gateway = self::gateway(self::statement(true, ['id' => 1], '00000'));

        self::assertSame(['id' => 1], $gateway->fetchOne('SELECT 1', [], 'op'));
        self::assertTrue($gateway->exists('SELECT 1', [], 'op'));
    }

    public function testNonThrowingPrepareFailureIsAStorageExceptionNotAMiss(): void
    {
        $gateway = self::gateway(null, true);

        foreach ([
            static fn() => $gateway->fetchOne('SELECT 1', [], 'scope.getByCode'),
            static fn() => $gateway->fetchAll('SELECT 1', [], 'scope.listAll'),
            static fn() => $gateway->exists('SELECT 1', [], 'domainScope.isAllowed'),
            static fn() => $gateway->write('DELETE FROM t', [], 'x.delete'),
            static fn() => $gateway->scalarInt('SELECT COUNT(*)', [], 'stats.count'),
        ] as $call) {
            try {
                $call();
                self::fail('a prepare failure must not look like a miss');
            } catch (I18nStorageException $e) {
                self::assertInstanceOf(I18nExceptionInterface::class, $e);
                self::assertStringContainsString(':prepare', $e->getMessage());
            }
        }
    }

    public function testNonThrowingExecuteFailureIsAStorageException(): void
    {
        $gateway = self::gateway(self::statement(false, false, '00000'));

        $this->expectException(I18nStorageException::class);
        $this->expectExceptionMessage('translation.upsert:execute');
        $gateway->write('INSERT ...', [], 'translation.upsert');
    }

    public function testAFetchFalseWithAnErrorCodeIsAStorageExceptionNotAnEmptyResult(): void
    {
        $gateway = self::gateway(self::statement(true, false, 'HY000'));

        foreach ([
            static fn() => $gateway->fetchOne('SELECT 1', [], 'op'),
            static fn() => $gateway->fetchAll('SELECT 1', [], 'op'),
            static fn() => $gateway->exists('SELECT 1', [], 'op'),
        ] as $call) {
            try {
                $call();
                self::fail('a fetch error must not look like "no row"');
            } catch (I18nStorageException $e) {
                self::assertStringContainsString(':fetch', $e->getMessage());
            }
        }
    }

    public function testAScalarThatIsNotANumberIsAStorageException(): void
    {
        $stmt = new class extends PDOStatement {
            /**
             * @param array<array-key, mixed>|null $params
             */
            public function execute(?array $params = null): bool
            {
                return true;
            }

            public function fetchColumn(int $column = 0): mixed
            {
                return false;
            }
        };

        $this->expectException(I18nStorageException::class);
        self::gateway($stmt)->scalarInt('SELECT COUNT(*)', [], 'stats.count');
    }

    public function testOnlyTheMysqlDuplicateKeyDriverCodeIsADuplicate(): void
    {
        $make = static function (?array $errorInfo): PDOException {
            $e = new PDOException('x');
            $e->errorInfo = $errorInfo;

            return $e;
        };

        self::assertTrue(PdoGateway::isDuplicateKey($make(['23000', 1062, 'Duplicate entry'])));
        // SQLSTATE 23000 is a broad integrity class: FK (1452), NOT NULL (1048)... are NOT duplicates
        self::assertFalse(PdoGateway::isDuplicateKey($make(['23000', 1452, 'FK'])));
        self::assertFalse(PdoGateway::isDuplicateKey($make(['23000', 1048, 'null'])));
        self::assertFalse(PdoGateway::isDuplicateKey($make(['42S02', 1146, 'no table'])));
        self::assertFalse(PdoGateway::isDuplicateKey($make(null)));
    }
}
