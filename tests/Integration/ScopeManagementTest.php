<?php

/**
 * @copyright   ©2026 Maatify.dev
 * @Library     maatify/php-i18n
 * @Project     maatify:php-i18n
 * @author      Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @since       2026-10-01 00:00
 * @see         https://www.maatify.dev Maatify.dev
 * @link        https://github.com/Maatify/php-i18n view Project on GitHub
 * @note        Distributed in the hope that it will be useful - WITHOUT WARRANTY.
 */

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Exception\ScopeAlreadyExistsException;
use Maatify\I18n\Exception\ScopeInUseException;
use Maatify\I18n\Exception\ScopeNotFoundException;
use Maatify\I18n\Exception\I18nExceptionInterface;
use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Management\Command\UpdateScopeMetadataCommand;
use Maatify\I18n\Management\Criteria\ScopeListCriteria;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;
use Maatify\Persistence\Exception\InvalidOrderingOperationException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

/**
 * Package-owned scope management (GA-F04/F08/F09) against real MySQL.
 */
final class ScopeManagementTest extends MysqlIntegrationTestCase
{
    private function idOf(string $code): int
    {
        return $this->scalarInt("SELECT id FROM maa_i18n_scopes WHERE code = '" . $code . "'");
    }

    /**
     * @return list<string> codes in display order
     */
    private function orderedCodes(): array
    {
        $stmt = $this->pdo()->query('SELECT code FROM maa_i18n_scopes ORDER BY sort_order ASC, id ASC');
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
        $stmt = $this->pdo()->query('SELECT sort_order FROM maa_i18n_scopes ORDER BY sort_order ASC, id ASC');
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
        $this->pdo()->exec('DELETE FROM maa_i18n_scopes');
        $this->pdo()->exec('SET FOREIGN_KEY_CHECKS=1');

        foreach ($codes as $code) {
            $this->scopeManagement->create(new CreateScopeCommand($code, strtoupper($code)));
        }
    }

    public function testCreateAppendsToTheEndOfTheDisplayOrder(): void
    {
        $this->resetToOrdered();

        $first = $this->scopeManagement->create(new CreateScopeCommand('a', 'A', 'first', true));
        $second = $this->scopeManagement->create(new CreateScopeCommand('b', 'B', null, false));

        self::assertGreaterThan(0, $first);
        self::assertGreaterThan($first, $second);
        self::assertSame(['a', 'b'], $this->orderedCodes());
        self::assertSame([1, 2], $this->positions());

        $row = $this->managementRead->getScope($second);
        self::assertSame('b', $row->code);
        self::assertFalse($row->isActive);
        self::assertNull($row->description);
    }

    public function testCreateDuplicateCodeFailsWithTheSemanticException(): void
    {
        $this->resetToOrdered('a');

        try {
            $this->scopeManagement->create(new CreateScopeCommand('a', 'Again'));
            self::fail('Expected ScopeAlreadyExistsException');
        } catch (ScopeAlreadyExistsException $e) {
            self::assertInstanceOf(I18nExceptionInterface::class, $e);
        }

        self::assertSame(['a'], $this->orderedCodes());
    }

    public function testCommandsValidateTheirOwnContract(): void
    {
        foreach ([
            static fn() => new CreateScopeCommand('', 'Name'),
            static fn() => new CreateScopeCommand('   ', 'Name'),
            static fn() => new CreateScopeCommand(str_repeat('x', 32 + 1), 'Name'),
            static fn() => new CreateScopeCommand('ok', ''),
            static fn() => new CreateScopeCommand('ok', str_repeat('n', 64 + 1)),
            static fn() => new UpdateScopeMetadataCommand(0, 'n'),
            static fn() => new UpdateScopeMetadataCommand(1),
            static fn() => new UpdateScopeMetadataCommand(1, '  '),
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

        $this->scopeManagement->updateMetadata(new UpdateScopeMetadataCommand($id, 'New name'));
        $row = $this->managementRead->getScope($id);
        self::assertSame(['a', 'New name', null], [$row->code, $row->name, $row->description]);

        $this->scopeManagement->updateMetadata(new UpdateScopeMetadataCommand($id, null, 'About'));
        $row = $this->managementRead->getScope($id);
        self::assertSame(['a', 'New name', 'About'], [$row->code, $row->name, $row->description]);
    }

    public function testMutationsOnAMissingRowFailWithNotFound(): void
    {
        foreach ([
            fn() => $this->scopeManagement->updateMetadata(new UpdateScopeMetadataCommand(999999, 'x')),
            fn() => $this->scopeManagement->setActive(999999, false),
            fn() => $this->scopeManagement->changeCode(999999, 'zz'),
            fn() => $this->scopeManagement->moveToPosition(999999, 1),
            fn() => $this->managementRead->getScope(999999),
        ] as $call) {
            try {
                $call();
                self::fail('Expected ScopeNotFoundException');
            } catch (ScopeNotFoundException $e) {
                self::assertInstanceOf(I18nExceptionInterface::class, $e);
            }
        }
    }

    public function testSetActiveTogglesTheFlag(): void
    {
        $this->resetToOrdered('a');
        $id = $this->idOf('a');

        $this->scopeManagement->setActive($id, false);
        self::assertFalse($this->managementRead->getScope($id)->isActive);

        $this->scopeManagement->setActive($id, true);
        self::assertTrue($this->managementRead->getScope($id)->isActive);
    }

    public function testChangeCodeOfAnUnusedRowSucceeds(): void
    {
        $this->resetToOrdered('a');
        $id = $this->idOf('a');

        $this->scopeManagement->changeCode($id, 'renamed');

        self::assertSame(['renamed'], $this->orderedCodes());
    }

    public function testChangeCodeIsRejectedWhenTheCodeIsUsedByAMapping(): void
    {
        $this->resetToOrdered('a');
        $this->pdo()->exec("INSERT INTO maa_i18n_domain_scopes (scope_code, domain_code) VALUES ('a', 'home')");
        $id = $this->idOf('a');

        $this->expectException(ScopeInUseException::class);
        try {
            $this->scopeManagement->changeCode($id, 'renamed');
        } finally {
            self::assertSame(['a'], $this->orderedCodes());
        }
    }

    public function testChangeCodeIsRejectedWhenTheCodeIsUsedByAKey(): void
    {
        $this->resetToOrdered('a');
        $this->pdo()->exec("INSERT INTO maa_i18n_keys (scope, domain, key_part) VALUES ('a', 'home', 'k')");
        $id = $this->idOf('a');

        $this->expectException(ScopeInUseException::class);
        try {
            $this->scopeManagement->changeCode($id, 'renamed');
        } finally {
            self::assertSame(['a'], $this->orderedCodes());
        }
    }

    public function testChangeCodeToATakenCodeFailsWithTheSemanticException(): void
    {
        $this->resetToOrdered('a', 'b');
        $id = $this->idOf('a');

        $this->expectException(ScopeAlreadyExistsException::class);
        try {
            $this->scopeManagement->changeCode($id, 'b');
        } finally {
            self::assertSame(['a', 'b'], $this->orderedCodes());
        }
    }

    public function testChangeCodeRejectsAnInvalidNewCode(): void
    {
        $this->resetToOrdered('a');
        $id = $this->idOf('a');

        $this->expectException(I18nInvalidArgumentException::class);
        $this->scopeManagement->changeCode($id, '');
    }

    public function testMoveToPositionUsesTheStableOrderingAndKeepsTheOrderContiguous(): void
    {
        $this->resetToOrdered('x', 'y', 'z');

        $this->scopeManagement->moveToPosition($this->idOf('z'), 1);
        self::assertSame(['z', 'x', 'y'], $this->orderedCodes());
        self::assertSame([1, 2, 3], $this->positions());

        $this->scopeManagement->moveToPosition($this->idOf('z'), 3);
        self::assertSame(['x', 'y', 'z'], $this->orderedCodes());

        // above the maximum is clamped to the last position
        $this->scopeManagement->moveToPosition($this->idOf('x'), 99);
        self::assertSame(['y', 'z', 'x'], $this->orderedCodes());
        self::assertSame([1, 2, 3], $this->positions());
    }

    public function testMoveToAnInvalidPositionIsRejectedByPersistence(): void
    {
        $this->resetToOrdered('x');

        $this->expectException(InvalidOrderingOperationException::class);
        $this->scopeManagement->moveToPosition($this->idOf('x'), 0);
    }

    public function testSearchPaginatesFiltersAndOrdersByDisplayPosition(): void
    {
        $this->resetToOrdered('alpha', 'beta', 'gamma', 'a_b');
        $this->scopeManagement->setActive($this->idOf('beta'), false);

        $page = $this->managementRead->searchScopes(new ScopeListCriteria(page: new PageRequest(1, 2)));
        self::assertSame(4, $page->total);
        self::assertSame(4, $page->filtered);
        self::assertSame(2, $page->totalPages);
        self::assertTrue($page->hasNext);
        self::assertSame(['alpha', 'beta'], array_map(static fn($d) => $d->code, $page->data));

        $second = $this->managementRead->searchScopes(new ScopeListCriteria(page: new PageRequest(2, 2)));
        self::assertSame(['gamma', 'a_b'], array_map(static fn($d) => $d->code, $second->data));

        $inactive = $this->managementRead->searchScopes(new ScopeListCriteria(isActive: false));
        self::assertSame(4, $inactive->total);
        self::assertSame(1, $inactive->filtered);
        self::assertSame(['beta'], array_map(static fn($d) => $d->code, $inactive->data));

        $byCode = $this->managementRead->searchScopes(new ScopeListCriteria(code: 'gamma'));
        self::assertSame(['gamma'], array_map(static fn($d) => $d->code, $byCode->data));

        // free text matches code OR name; '_' is a literal, not a wildcard
        $search = $this->managementRead->searchScopes(new ScopeListCriteria(globalSearch: 'a_b'));
        self::assertSame(['a_b'], array_map(static fn($d) => $d->code, $search->data));

        $none = $this->managementRead->searchScopes(new ScopeListCriteria(globalSearch: 'no-such-thing'));
        self::assertSame([], $none->data);
        self::assertSame(0, $none->filtered);
    }
}
