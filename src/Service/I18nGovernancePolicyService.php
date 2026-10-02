<?php

declare(strict_types=1);

namespace Maatify\I18n\Service;

use Maatify\I18n\DTO\DomainDTO;
use Maatify\I18n\DTO\ScopeDTO;
use Maatify\I18n\Enum\I18nPolicyModeEnum;
use Maatify\I18n\Enum\LockModeEnum;
use Maatify\I18n\Exception\DomainNotAllowedException;
use Maatify\I18n\Exception\DomainScopeViolationException;
use Maatify\I18n\Exception\ScopeNotAllowedException;
use Maatify\I18n\Repository\DomainRepositoryInterface;
use Maatify\I18n\Repository\DomainScopeRepositoryInterface;
use Maatify\I18n\Repository\ScopeRepositoryInterface;

/**
 * Applies the configured governance policy to I18n key and domain operations.
 */
final readonly class I18nGovernancePolicyService
{
    public function __construct(
        private ScopeRepositoryInterface $scopeRepository,
        private DomainRepositoryInterface $domainRepository,
        private DomainScopeRepositoryInterface $domainScopeRepository,
        private I18nPolicyModeEnum $mode = I18nPolicyModeEnum::STRICT,
    ) {}

    /**
     * STRICT: throws domain-specific policy exceptions
     * PERMISSIVE: same rules but softer entry conditions
     *
     * Non-locking: used by reads and by checks that do not create usage.
     */
    public function assertScopeAndDomainAllowed(
        string $scope,
        string $domain,
    ): void {
        $violation = $this->violationFor(
            $scope,
            $domain,
            $this->scopeRepository->getByCode($scope),
            $this->domainRepository->getByCode($domain),
            LockModeEnum::NONE,
        );

        if ($violation !== null) {
            throw $violation;
        }
    }

    /**
     * Same decision as {@see self::assertScopeAndDomainAllowed()} for a
     * mutation that CREATES usage of the scope / domain (key create, key
     * move). It takes SHARE row locks in the deterministic order
     * scope -> domain -> mapping (requires an active transaction), so a
     * concurrent scope/domain code change - which takes the UPDATE lock on the
     * same governance row before checking usage - cannot commit between the
     * policy decision and the usage becoming visible.
     */
    public function assertScopeAndDomainAllowedForUsage(
        string $scope,
        string $domain,
    ): void {
        $scopeDto = $this->scopeRepository->getByCode($scope, LockModeEnum::SHARE);
        $domainDto = $this->domainRepository->getByCode($domain, LockModeEnum::SHARE);

        $violation = $this->violationFor($scope, $domain, $scopeDto, $domainDto, LockModeEnum::SHARE);

        if ($violation !== null) {
            throw $violation;
        }
    }

    /**
     * FAIL-SOFT read helper.
     *
     * Only the three policy rejections are soft. A storage failure is never a
     * policy denial: it propagates (thrown PDOException unchanged,
     * I18nStorageException for a non-throwing failure state).
     */
    public function isScopeAndDomainReadable(
        string $scope,
        string $domain,
    ): bool {
        return $this->violationFor(
            $scope,
            $domain,
            $this->scopeRepository->getByCode($scope),
            $this->domainRepository->getByCode($domain),
            LockModeEnum::NONE,
        ) === null;
    }

    private function violationFor(
        string $scope,
        string $domain,
        ?ScopeDTO $scopeDto,
        ?DomainDTO $domainDto,
        LockModeEnum $lock,
    ): ScopeNotAllowedException|DomainNotAllowedException|DomainScopeViolationException|null {
        if ($this->mode === I18nPolicyModeEnum::STRICT) {
            if ($scopeDto === null || !$scopeDto->isActive) {
                return new ScopeNotAllowedException($scope);
            }

            if ($domainDto === null || !$domainDto->isActive) {
                return new DomainNotAllowedException($domain);
            }

            if (!$this->domainScopeRepository->isDomainAllowedForScope($scope, $domain, $lock)) {
                return new DomainScopeViolationException($scope, $domain);
            }

            return null;
        }

        if ($scopeDto !== null && !$scopeDto->isActive) {
            return new ScopeNotAllowedException($scope);
        }

        if ($domainDto !== null && !$domainDto->isActive) {
            return new DomainNotAllowedException($domain);
        }

        if ($scopeDto !== null && $domainDto !== null
            && !$this->domainScopeRepository->isDomainAllowedForScope($scope, $domain, $lock)) {
            return new DomainScopeViolationException($scope, $domain);
        }

        return null;
    }
}
