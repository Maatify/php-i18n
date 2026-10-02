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

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Exception\DomainAlreadyExistsException;
use Maatify\I18n\Exception\DomainInUseException;
use Maatify\I18n\Exception\DomainNotFoundException;
use Maatify\I18n\Exception\I18nExceptionInterface;
use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\UpdateDomainMetadataCommand;
use Maatify\I18n\Management\Criteria\DomainListCriteria;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;
use Maatify\Persistence\Exception\InvalidOrderingOperationException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

/**
 * Package-owned domain management (GA-F04/F08/F09) against real MySQL.
 */
final class DomainManagementTest extends MysqlIntegrationTestCase
{
    private function idOf(string $code): int
    {
        return $this->scalarInt("SELECT id FROM maa_i18n_domains WHERE code = '" . $code . "'");
    }

    /**
     * @return list<string> codes in display order
     */
    private function orderedCodes(): array
    {
        $stmt = $this->pdo()->query('SELECT code FROM maa_i18n_domains ORDER BY sort_order ASC, id ASC');
        self::assertNotFalse($stmt);

        /** @var list<string> $codes */
        $codes = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $codes;
    }

    /**
     * @return list<int> sort_order values in display order
     */
    private function positions(): array
    {
        $stmt = $this->pdo()->query('SELECT sort_order FROM maa_i18n_domains ORDER BY sort_order ASC, id ASC');
        self::assertNotFalse($stmt);

        $positions = [];
        /** @var list<int|string> $values */
        $values = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($values as $value) {
            $positions[] = is_numeric($value) ? (int) $value : -1;
        }

        return $positions;
    }

    private function resetToOrdered(string ...$codes): void
    {
        $this->pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
        $this->pdo()->exec('DELETE FROM maa_i18n_domain_scopes');
        $this->pdo()->exec('DELETE FROM maa_i18n_domains');
        $this->pdo()->exec('SET FOREIGN_KEY_CHECKS=1');

        foreach ($codes as $code) {
            $this->domainManagement->create(new CreateDomainCommand($code, strtoupper($code)));
        }
    }

    public function testCreateAppendsToTheEndOfTheDisplayOrder(): void
    {
        $this->resetToOrdered();

        $first = $this->domainManagement->create(new CreateDomainCommand('a', 'A', 'first', true));
        $second = $this->domainManagement->create(new CreateDomainCommand('b', 'B', null, false));

        self::assertGreaterThan(0, $first);
        self::assertGreaterThan($first, $second);
        self::assertSame(['a', 'b'], $this->orderedCodes());
        self::assertSame([1, 2], $this->positions());

        $row = $this->managementRead->getDomain($second);
        self::assertSame('b', $row->code);
        self::assertFalse($row->isActive);
        self::assertNull($row->description);
    }

    public function testCreateDuplicateCodeFailsWithTheSemanticException(): void
    {
        $this->resetToOrdered('a');

        try {
            $this->domainManagement->create(new CreateDomainCommand('a', 'Again'));
            self::fail('Expected DomainAlreadyExistsException');
        } catch (DomainAlreadyExistsException $e) {
            self::assertInstanceOf(I18nExceptionInterface::class, $e);
        }

        self::assertSame(['a'], $this->orderedCodes());
    }

    public function testCommandsValidateTheirOwnContract(): void
    {
        foreach ([
            static fn() => new CreateDomainCommand('', 'Name'),
            static fn() => new CreateDomainCommand('   ', 'Name'),
            static fn() => new CreateDomainCommand(str_repeat('x', 64 + 1), 'Name'),
            static fn() => new CreateDomainCommand('ok', ''),
            static fn() => new CreateDomainCommand('ok', str_repeat('n', 128 + 1)),
            static fn() => new UpdateDomainMetadataCommand(0, 'n'),
            static fn() => new UpdateDomainMetadataCommand(1),
            static fn() => new UpdateDomainMetadataCommand(1, '  '),
        ] as $build) {
            try {
                $build();
                self::fail('Expected I18nInvalidArgumentException');
            } catch (I18nInvalidArgumentException $e) {
                self::assertInstanceOf(I18nExceptionInterface::class, $e);
            }
        }
    }

    public function testUpdateMetadataChangesOnlyTheSuppliedFields(): void
    {
        $this->resetToOrdered('a');
        $id = $this->idOf('a');

        $this->domainManagement->updateMetadata(new UpdateDomainMetadataCommand($id, 'New name'));
        $row = $this->managementRead->getDomain($id);
        self::assertSame(['a', 'New name', null], [$row->code, $row->name, $row->description]);

        $this->domainManagement->updateMetadata(new UpdateDomainMetadataCommand($id, null, 'About'));
        $row = $this->managementRead->getDomain($id);
        self::assertSame(['a', 'New name', 'About'], [$row->code, $row->name, $row->description]);
    }

    public function testMutationsOnAMissingRowFailWithNotFound(): void
    {
        foreach ([
            fn() => $this->domainManagement->updateMetadata(new UpdateDomainMetadataCommand(999999, 'x')),
            fn() => $this->domainManagement->setActive(999999, false),
            fn() => $this->domainManagement->changeCode(999999, 'zz'),
            fn() => $this->domainManagement->moveToPosition(999999, 1),
            fn() => $this->managementRead->getDomain(999999),
        ] as $call) {
            try {
                $call();
                self::fail('Expected DomainNotFoundException');
            } catch (DomainNotFoundException $e) {
                self::assertInstanceOf(I18nExceptionInterface::class, $e);
            }
        }
    }

    public function testSetActiveTogglesTheFlag(): void
    {
        $this->resetToOrdered('a');
        $id = $this->idOf('a');

        $this->domainManagement->setActive($id, false);
        self::assertFalse($this->managementRead->getDomain($id)->isActive);

        $this->domainManagement->setActive($id, true);
        self::assertTrue($this->managementRead->getDomain($id)->isActive);
    }

    public function testChangeCodeOfAnUnusedRowSucceeds(): void
    {
        $this->resetToOrdered('a');
        $id = $this->idOf('a');

        $this->domainManagement->changeCode($id, 'renamed');

        self::assertSame(['renamed'], $this->orderedCodes());
    }

    public function testChangeCodeIsRejectedWhenTheCodeIsUsedByAMapping(): void
    {
        $this->resetToOrdered('a');
        $this->pdo()->exec("INSERT INTO maa_i18n_domain_scopes (scope_code, domain_code) VALUES ('ct', 'a')");
        $id = $this->idOf('a');

        $this->expectException(DomainInUseException::class);
        try {
            $this->domainManagement->changeCode($id, 'renamed');
        } finally {
            self::assertSame(['a'], $this->orderedCodes());
        }
    }

    public function testChangeCodeIsRejectedWhenTheCodeIsUsedByAKey(): void
    {
        $this->resetToOrdered('a');
        $this->pdo()->exec("INSERT INTO maa_i18n_keys (scope, domain, key_part) VALUES ('ct', 'a', 'k')");
        $id = $this->idOf('a');

        $this->expectException(DomainInUseException::class);
        try {
            $this->domainManagement->changeCode($id, 'renamed');
        } finally {
            self::assertSame(['a'], $this->orderedCodes());
        }
    }

    public function testChangeCodeToATakenCodeFailsWithTheSemanticException(): void
    {
        $this->resetToOrdered('a', 'b');
        $id = $this->idOf('a');

        $this->expectException(DomainAlreadyExistsException::class);
        try {
            $this->domainManagement->changeCode($id, 'b');
        } finally {
            self::assertSame(['a', 'b'], $this->orderedCodes());
        }
    }

    public function testChangeCodeRejectsAnInvalidNewCode(): void
    {
        $this->resetToOrdered('a');
        $id = $this->idOf('a');

        $this->expectException(I18nInvalidArgumentException::class);
        $this->domainManagement->changeCode($id, '');
    }

    public function testMoveToPositionUsesTheStableOrderingAndKeepsTheOrderContiguous(): void
    {
        $this->resetToOrdered('x', 'y', 'z');

        $this->domainManagement->moveToPosition($this->idOf('z'), 1);
        self::assertSame(['z', 'x', 'y'], $this->orderedCodes());
        self::assertSame([1, 2, 3], $this->positions());

        $this->domainManagement->moveToPosition($this->idOf('z'), 3);
        self::assertSame(['x', 'y', 'z'], $this->orderedCodes());

        // above the maximum is clamped to the last position
        $this->domainManagement->moveToPosition($this->idOf('x'), 99);
        self::assertSame(['y', 'z', 'x'], $this->orderedCodes());
        self::assertSame([1, 2, 3], $this->positions());
    }

    public function testMoveToAnInvalidPositionIsRejectedByPersistence(): void
    {
        $this->resetToOrdered('x');

        $this->expectException(InvalidOrderingOperationException::class);
        $this->domainManagement->moveToPosition($this->idOf('x'), 0);
    }

    public function testSearchPaginatesFiltersAndOrdersByDisplayPosition(): void
    {
        $this->resetToOrdered('alpha', 'beta', 'gamma', 'a_b');
        $this->domainManagement->setActive($this->idOf('beta'), false);

        $page = $this->managementRead->searchDomains(new DomainListCriteria(page: new PageRequest(1, 2)));
        self::assertSame(4, $page->total);
        self::assertSame(4, $page->filtered);
        self::assertSame(2, $page->totalPages);
        self::assertTrue($page->hasNext);
        self::assertSame(['alpha', 'beta'], array_map(static fn($d) => $d->code, $page->data));

        $second = $this->managementRead->searchDomains(new DomainListCriteria(page: new PageRequest(2, 2)));
        self::assertSame(['gamma', 'a_b'], array_map(static fn($d) => $d->code, $second->data));

        $inactive = $this->managementRead->searchDomains(new DomainListCriteria(isActive: false));
        self::assertSame(4, $inactive->total);
        self::assertSame(1, $inactive->filtered);
        self::assertSame(['beta'], array_map(static fn($d) => $d->code, $inactive->data));

        $byCode = $this->managementRead->searchDomains(new DomainListCriteria(code: 'gamma'));
        self::assertSame(['gamma'], array_map(static fn($d) => $d->code, $byCode->data));

        // free text matches code OR name; '_' is a literal, not a wildcard
        $search = $this->managementRead->searchDomains(new DomainListCriteria(globalSearch: 'a_b'));
        self::assertSame(['a_b'], array_map(static fn($d) => $d->code, $search->data));

        $none = $this->managementRead->searchDomains(new DomainListCriteria(globalSearch: 'no-such-thing'));
        self::assertSame([], $none->data);
        self::assertSame(0, $none->filtered);
    }
}
