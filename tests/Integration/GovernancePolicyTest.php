<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Enum\I18nPolicyModeEnum;
use Maatify\I18n\Exception\DomainNotAllowedException;
use Maatify\I18n\Exception\DomainScopeViolationException;
use Maatify\I18n\Exception\ScopeNotAllowedException;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;

final class GovernancePolicyTest extends MysqlIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $pdo = $this->pdo();
        // Seeded by the base: ct / home (active, mapped). Add the rest of the matrix.
        $pdo->exec("INSERT INTO maa_i18n_scopes (code, name, is_active) VALUES ('off', 'Inactive scope', 0), ('free', 'Unmapped scope', 1)");
        $pdo->exec("INSERT INTO maa_i18n_domains (code, name, is_active) VALUES ('dead', 'Inactive domain', 0), ('loose', 'Unmapped domain', 1)");
    }

    // ── STRICT ─────────────────────────────────────────────────────────────

    public function testStrictAcceptsAnActiveMappedPair(): void
    {
        $this->policyFor(I18nPolicyModeEnum::STRICT)->assertScopeAndDomainAllowed('ct', 'home');
        self::assertTrue($this->policyFor(I18nPolicyModeEnum::STRICT)->isScopeAndDomainReadable('ct', 'home'));
    }

    public function testStrictRejectsMissingScope(): void
    {
        $this->expectException(ScopeNotAllowedException::class);
        $this->policyFor(I18nPolicyModeEnum::STRICT)->assertScopeAndDomainAllowed('nope', 'home');
    }

    public function testStrictRejectsInactiveScope(): void
    {
        $this->expectException(ScopeNotAllowedException::class);
        $this->policyFor(I18nPolicyModeEnum::STRICT)->assertScopeAndDomainAllowed('off', 'home');
    }

    public function testStrictRejectsMissingDomain(): void
    {
        $this->expectException(DomainNotAllowedException::class);
        $this->policyFor(I18nPolicyModeEnum::STRICT)->assertScopeAndDomainAllowed('ct', 'nope');
    }

    public function testStrictRejectsInactiveDomain(): void
    {
        $this->expectException(DomainNotAllowedException::class);
        $this->policyFor(I18nPolicyModeEnum::STRICT)->assertScopeAndDomainAllowed('ct', 'dead');
    }

    public function testStrictRejectsExistingPairWithoutAllowedMapping(): void
    {
        $this->expectException(DomainScopeViolationException::class);
        $this->policyFor(I18nPolicyModeEnum::STRICT)->assertScopeAndDomainAllowed('free', 'loose');
    }

    public function testStrictReadableIsFalseForEveryRejection(): void
    {
        $policy = $this->policyFor(I18nPolicyModeEnum::STRICT);

        self::assertFalse($policy->isScopeAndDomainReadable('nope', 'home'));
        self::assertFalse($policy->isScopeAndDomainReadable('off', 'home'));
        self::assertFalse($policy->isScopeAndDomainReadable('ct', 'nope'));
        self::assertFalse($policy->isScopeAndDomainReadable('ct', 'dead'));
        self::assertFalse($policy->isScopeAndDomainReadable('free', 'loose'));
    }

    // ── PERMISSIVE (current runtime semantics) ─────────────────────────────

    public function testPermissiveDoesNotFailForUnknownScopeOrDomain(): void
    {
        $policy = $this->policyFor(I18nPolicyModeEnum::PERMISSIVE);

        $policy->assertScopeAndDomainAllowed('nope', 'home');
        $policy->assertScopeAndDomainAllowed('ct', 'nope');
        $policy->assertScopeAndDomainAllowed('nope', 'nope2');

        self::assertTrue($policy->isScopeAndDomainReadable('nope', 'nope2'));
    }

    public function testPermissiveRejectsKnownInactiveScope(): void
    {
        $policy = $this->policyFor(I18nPolicyModeEnum::PERMISSIVE);

        try {
            $policy->assertScopeAndDomainAllowed('off', 'home');
            self::fail('Expected ScopeNotAllowedException');
        } catch (ScopeNotAllowedException) {
            self::assertFalse($policy->isScopeAndDomainReadable('off', 'home'));
        }
    }

    public function testPermissiveRejectsKnownInactiveDomain(): void
    {
        $policy = $this->policyFor(I18nPolicyModeEnum::PERMISSIVE);

        try {
            $policy->assertScopeAndDomainAllowed('ct', 'dead');
            self::fail('Expected DomainNotAllowedException');
        } catch (DomainNotAllowedException) {
            self::assertFalse($policy->isScopeAndDomainReadable('ct', 'dead'));
        }
    }

    public function testPermissiveRejectsMappingViolationWhenBothAreKnown(): void
    {
        $policy = $this->policyFor(I18nPolicyModeEnum::PERMISSIVE);

        try {
            $policy->assertScopeAndDomainAllowed('free', 'loose');
            self::fail('Expected DomainScopeViolationException');
        } catch (DomainScopeViolationException) {
            self::assertFalse($policy->isScopeAndDomainReadable('free', 'loose'));
        }
    }

    public function testPermissiveAcceptsAnActiveMappedPair(): void
    {
        $policy = $this->policyFor(I18nPolicyModeEnum::PERMISSIVE);

        $policy->assertScopeAndDomainAllowed('ct', 'home');
        self::assertTrue($policy->isScopeAndDomainReadable('ct', 'home'));
    }
}
