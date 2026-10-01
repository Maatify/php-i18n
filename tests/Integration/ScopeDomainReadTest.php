<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\DTO\DomainDTO;
use Maatify\I18n\DTO\ScopeDTO;
use Maatify\I18n\Management\Service\I18nDomainReadService;
use Maatify\I18n\Management\Service\I18nScopeReadService;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;

final class ScopeDomainReadTest extends MysqlIntegrationTestCase
{
    /**
     * @param iterable<ScopeDTO> $items
     *
     * @return list<string>
     */
    private static function scopeCodes(iterable $items): array
    {
        $codes = [];
        foreach ($items as $item) {
            $codes[] = $item->code;
        }
        sort($codes);

        return $codes;
    }

    /**
     * @param iterable<DomainDTO> $items
     *
     * @return list<string>
     */
    private static function domainCodes(iterable $items): array
    {
        $codes = [];
        foreach ($items as $item) {
            $codes[] = $item->code;
        }
        sort($codes);

        return $codes;
    }

    public function testListScopesReturnsAllAndListActiveScopesReturnsOnlyActive(): void
    {
        $this->pdo()->exec("INSERT INTO maa_i18n_scopes (code, name, is_active) VALUES ('off', 'Off', 0)");

        $service = new I18nScopeReadService($this->scopes);

        self::assertSame(['ct', 'off'], self::scopeCodes($service->listScopes()));
        self::assertSame(['ct'], self::scopeCodes($service->listActiveScopes()));
    }

    public function testListScopesIsEmptyWhenNoScopesExist(): void
    {
        $this->pdo()->exec('DELETE FROM maa_i18n_scopes');

        $service = new I18nScopeReadService($this->scopes);

        self::assertTrue($service->listScopes()->isEmpty());
        self::assertTrue($service->listActiveScopes()->isEmpty());
    }

    public function testListDomainsForScopeReturnsMappedDomainsOnly(): void
    {
        $this->pdo()->exec("INSERT INTO maa_i18n_domains (code, name) VALUES ('auth', 'Auth'), ('unmapped', 'Unmapped')");
        $this->pdo()->exec("INSERT INTO maa_i18n_domain_scopes (scope_code, domain_code) VALUES ('ct', 'auth')");

        $service = new I18nDomainReadService($this->domains, $this->domainScopes);

        self::assertSame(['auth', 'home'], self::domainCodes($service->listDomainsForScope('ct')));
    }

    public function testListDomainsForUnknownOrUnmappedScopeIsEmptyAndDoesNotThrow(): void
    {
        $this->pdo()->exec("INSERT INTO maa_i18n_scopes (code, name) VALUES ('free', 'Unmapped')");

        $service = new I18nDomainReadService($this->domains, $this->domainScopes);

        self::assertTrue($service->listDomainsForScope('nope')->isEmpty());
        self::assertTrue($service->listDomainsForScope('free')->isEmpty());
    }
}
