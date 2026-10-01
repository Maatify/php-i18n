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

use Maatify\I18n\Exception\DomainNotFoundException;
use Maatify\I18n\Exception\DomainScopeAlreadyAssignedException;
use Maatify\I18n\Exception\DomainScopeNotAssignedException;
use Maatify\I18n\Exception\ScopeNotFoundException;
use Maatify\I18n\Management\Criteria\ScopeDomainListCriteria;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

final class ScopeDomainAssignmentTest extends MysqlIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // base seeds ct / home (mapped); add a second scope + two more domains
        $this->pdo()->exec("INSERT INTO maa_i18n_scopes (code, name) VALUES ('ad', 'Admin')");
        $this->pdo()->exec("INSERT INTO maa_i18n_domains (code, name, sort_order) VALUES ('auth', 'Auth', 2), ('cart', 'Cart', 3)");
    }

    public function testAssignThenUnassignIsReadableThroughThePublicApi(): void
    {
        $this->scopeDomainManagement->assign('ad', 'auth');

        self::assertTrue($this->domainScopes->isDomainAllowedForScope('ad', 'auth'));
        self::assertSame(['auth'], $this->domainScopes->listDomainsForScope('ad'));
        $options = $this->managementRead->listDomainOptionsForScope('ad');
        self::assertSame(['auth'], array_map(static fn($o) => $o->code, $options->items));
        self::assertSame('Auth', $options->items[0]->name);

        $this->scopeDomainManagement->unassign('ad', 'auth');

        self::assertFalse($this->domainScopes->isDomainAllowedForScope('ad', 'auth'));
        self::assertSame([], $this->domainScopes->listDomainsForScope('ad'));
        self::assertTrue($this->managementRead->listDomainOptionsForScope('ad')->isEmpty());
    }

    public function testAssignTwiceFailsWithTheSemanticException(): void
    {
        $this->scopeDomainManagement->assign('ad', 'auth');

        $this->expectException(DomainScopeAlreadyAssignedException::class);
        $this->scopeDomainManagement->assign('ad', 'auth');
    }

    public function testUnassignWhenNotAssignedFails(): void
    {
        $this->expectException(DomainScopeNotAssignedException::class);
        $this->scopeDomainManagement->unassign('ad', 'cart');
    }

    public function testUnknownScopeOrDomainIsNotFound(): void
    {
        foreach ([
            [ScopeNotFoundException::class, 'nope', 'auth'],
            [DomainNotFoundException::class, 'ad', 'nope'],
        ] as [$exception, $scope, $domain]) {
            foreach (['assign', 'unassign'] as $operation) {
                try {
                    $this->scopeDomainManagement->{$operation}($scope, $domain);
                    self::fail($operation . ' should fail with ' . $exception);
                } catch (\Throwable $e) {
                    self::assertInstanceOf($exception, $e);
                }
            }
        }
    }

    public function testPagedDomainsCarryTheAssignmentFlagAndTheAssignedFilter(): void
    {
        $this->scopeDomainManagement->assign('ad', 'auth');

        $all = $this->managementRead->searchScopeDomains(new ScopeDomainListCriteria('ad'));
        self::assertSame(3, $all->total);
        self::assertSame(3, $all->filtered);
        $flags = [];
        foreach ($all->data as $row) {
            $flags[$row->code] = $row->assigned;
        }
        self::assertSame(['home' => false, 'auth' => true, 'cart' => false], $flags);

        $assigned = $this->managementRead->searchScopeDomains(new ScopeDomainListCriteria('ad', assigned: true));
        self::assertSame(['auth'], array_map(static fn($r) => $r->code, $assigned->data));
        self::assertSame(1, $assigned->filtered);
        self::assertSame(3, $assigned->total);

        $unassigned = $this->managementRead->searchScopeDomains(new ScopeDomainListCriteria('ad', assigned: false));
        self::assertSame(['home', 'cart'], array_map(static fn($r) => $r->code, $unassigned->data));

        // another scope's assignments are independent
        $ct = $this->managementRead->searchScopeDomains(new ScopeDomainListCriteria('ct', assigned: true));
        self::assertSame(['home'], array_map(static fn($r) => $r->code, $ct->data));

        $paged = $this->managementRead->searchScopeDomains(new ScopeDomainListCriteria('ad', page: new PageRequest(1, 2)));
        self::assertCount(2, $paged->data);
        self::assertSame(2, $paged->totalPages);
    }
}
