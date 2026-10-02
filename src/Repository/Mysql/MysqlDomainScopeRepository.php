<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository\Mysql;

use LogicException;
use Maatify\I18n\DTO\DomainOptionCollectionDTO;
use Maatify\I18n\DTO\DomainOptionDTO;
use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\DomainScopeAlreadyAssignedException;
use Maatify\I18n\Repository\DomainScopeRepositoryInterface;
use PDO;
use PDOException;

/**
 * Persists and reads domain scope records through the package-owned MySQL schema.
 */
final readonly class MysqlDomainScopeRepository implements DomainScopeRepositoryInterface
{
    private PdoGateway $gateway;

    public function __construct(PDO $pdo)
    {
        $this->gateway = new PdoGateway($pdo);
    }

    public function isDomainAllowedForScope(
        string $scopeCode,
        string $domainCode,
        LockModeEnum $lock = LockModeEnum::NONE,
    ): bool {
        return $this->gateway->exists(
            'SELECT 1 FROM maa_i18n_domain_scopes
             WHERE scope_code = :scope
               AND domain_code = :domain
             LIMIT 1' . $this->lockSuffix($lock),
            ['scope' => $scopeCode, 'domain' => $domainCode],
            'domainScope.isAllowed',
        );
    }

    public function listDomainsForScope(string $scopeCode): array
    {
        $rows = $this->gateway->fetchAll(
            'SELECT domain_code
             FROM maa_i18n_domain_scopes
             WHERE scope_code = :scope
             ORDER BY domain_code ASC',
            ['scope' => $scopeCode],
            'domainScope.listDomains',
        );

        $codes = [];
        foreach ($rows as $row) {
            $codes[] = Row::string($row, 'domain_code');
        }

        return $codes;
    }

    public function listDomainOptionsForScope(string $scopeCode): DomainOptionCollectionDTO
    {
        $rows = $this->gateway->fetchAll(
            'SELECT d.code, d.name
             FROM maa_i18n_domains d
             INNER JOIN maa_i18n_domain_scopes ds
                 ON ds.domain_code = d.code
             WHERE ds.scope_code = :scope
             ORDER BY d.code ASC',
            ['scope' => $scopeCode],
            'domainScope.listOptions',
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = new DomainOptionDTO(Row::string($row, 'code'), Row::string($row, 'name'));
        }

        return new DomainOptionCollectionDTO($items);
    }

    public function hasDomainsForScope(string $scopeCode, LockModeEnum $lock = LockModeEnum::NONE): bool
    {
        return $this->gateway->exists(
            'SELECT 1 FROM maa_i18n_domain_scopes WHERE scope_code = :code LIMIT 1' . $this->lockSuffix($lock),
            ['code' => $scopeCode],
            'domainScope.hasDomains',
        );
    }

    public function hasScopesForDomain(string $domainCode, LockModeEnum $lock = LockModeEnum::NONE): bool
    {
        return $this->gateway->exists(
            'SELECT 1 FROM maa_i18n_domain_scopes WHERE domain_code = :code LIMIT 1' . $this->lockSuffix($lock),
            ['code' => $domainCode],
            'domainScope.hasScopes',
        );
    }

    public function assign(string $scopeCode, string $domainCode): void
    {
        try {
            $this->gateway->write(
                'INSERT INTO maa_i18n_domain_scopes (scope_code, domain_code)
                 VALUES (:scope_code, :domain_code)',
                ['scope_code' => $scopeCode, 'domain_code' => $domainCode],
                'domainScope.assign',
            );
        } catch (PDOException $e) {
            if (PdoGateway::isDuplicateKey($e)) {
                throw new DomainScopeAlreadyAssignedException($scopeCode, $domainCode);
            }

            throw $e;
        }
    }

    /**
     * @return bool whether the exact assignment row was deleted
     */
    public function unassign(string $scopeCode, string $domainCode): bool
    {
        return $this->gateway->write(
            'DELETE FROM maa_i18n_domain_scopes
             WHERE scope_code = :scope_code
               AND domain_code = :domain_code',
            ['scope_code' => $scopeCode, 'domain_code' => $domainCode],
            'domainScope.unassign',
        ) > 0;
    }

    private function lockSuffix(LockModeEnum $lock): string
    {
        if ($lock !== LockModeEnum::NONE && !$this->gateway->pdo()->inTransaction()) {
            throw new LogicException('A locking read requires an active transaction.');
        }

        return $lock->sqlSuffix();
    }
}
