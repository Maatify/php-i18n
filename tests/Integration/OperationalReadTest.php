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

use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;

/**
 * Package-owned operational facts the Host composes (counts, coverage,
 * exact-code aggregates) - persisted-result semantics on real MySQL.
 */
final class OperationalReadTest extends MysqlIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo()->exec("INSERT INTO maa_i18n_scopes (code, name) VALUES ('ad', 'Admin')");
        $this->pdo()->exec("INSERT INTO maa_i18n_domains (code, name, sort_order) VALUES ('auth', 'Auth', 1), ('cart', 'Cart', 2)");
        $this->pdo()->exec("INSERT INTO maa_i18n_domain_scopes (scope_code, domain_code) VALUES ('ct', 'auth'), ('ct', 'cart'), ('ad', 'auth')");

        $h1 = $this->createKey('ct', 'home', 'h1');
        $h2 = $this->createKey('ct', 'home', 'h2');
        $a1 = $this->createKey('ct', 'auth', 'a1');
        $c1 = $this->createKey('ct', 'cart', 'c1');
        $x1 = $this->createKey('ad', 'auth', 'x1');

        $this->upsert('ar', $h1, 'x', null);
        $this->upsert('ar', $h2, 'x', null);
        $this->upsert('en', $h1, 'x', null);
        $this->upsert('ar', $a1, 'x', null);
        $this->upsert(null, $c1, 'x', null);
        $this->upsert('AR', $x1, 'x', null); // another exact code than 'ar'
    }

    public function testTotalsAndGroupings(): void
    {
        self::assertSame(5, $this->operationalRead->totalKeyCount());

        $byCode = [];
        foreach ($this->operationalRead->translatedCountByLanguageCode() as $row) {
            $byCode[$row->languageCode ?? '(null)'] = $row->count;
        }
        ksort($byCode);
        self::assertSame(['(null)' => 1, 'AR' => 1, 'ar' => 3, 'en' => 1], $byCode);

        $byScope = [];
        foreach ($this->operationalRead->keyCountByScope() as $row) {
            $byScope[$row->label] = $row->count;
        }
        self::assertSame(['Website' => 4, 'Admin' => 1], $byScope);

        self::assertSame($this->scalarInt('SELECT COUNT(*) FROM maa_i18n_domain_language_summary'), $this->operationalRead->summaryRowCount());
        self::assertGreaterThan(0, $this->operationalRead->summaryRowCount());
    }

    public function testScopeCoverageCountsOnlyAssignedDomainsAndNonNullExactCodes(): void
    {
        // ct is mapped to home (seed), auth and cart: 2 + 1 + 1 keys
        $coverage = $this->operationalRead->scopeKeyCoverage('ct');

        self::assertSame(4, $coverage->totalKeys);
        $byCode = [];
        foreach ($coverage->translatedByLanguage as $row) {
            $byCode[(string) $row->languageCode] = $row->count;
        }
        // NULL scope is not a language; 'ar' = h1,h2,a1 ; 'en' = h1
        self::assertSame(['ar' => 3, 'en' => 1], $byCode);

        $ad = $this->operationalRead->scopeKeyCoverage('ad');
        self::assertSame(1, $ad->totalKeys);
        self::assertSame(['AR' => 1], array_column(
            array_map(static fn($r) => ['c' => (string) $r->languageCode, 'n' => $r->count], $ad->translatedByLanguage),
            'n',
            'c',
        ));

        $none = $this->operationalRead->scopeKeyCoverage('unknown');
        self::assertSame(0, $none->totalKeys);
        self::assertSame([], $none->translatedByLanguage);
    }

    public function testDomainCoverageIsPerExactCodeMostMissingFirst(): void
    {
        $rows = $this->operationalRead->domainCoverage('ct', 'ar');

        $facts = [];
        foreach ($rows as $row) {
            $facts[$row->domainCode] = [$row->totalKeys, $row->translatedCount];
        }
        ksort($facts);
        self::assertSame(['auth' => [1, 1], 'cart' => [1, 0], 'home' => [2, 2]], $facts);
        // missing first: cart (1 missing) before the fully translated ones
        self::assertSame('cart', $rows[0]->domainCode);

        $en = $this->operationalRead->domainCoverage('ct', 'en');
        $enFacts = [];
        foreach ($en as $row) {
            $enFacts[$row->domainCode] = $row->translatedCount;
        }
        ksort($enFacts);
        self::assertSame(['auth' => 0, 'cart' => 0, 'home' => 1], $enFacts);

        // a code that owns no rows: every domain of the scope, translated = 0
        $none = $this->operationalRead->domainCoverage('ct', 'xx-no-rows');
        self::assertCount(3, $none);
        self::assertSame([0, 0, 0], array_map(static fn($r) => $r->translatedCount, $none));
    }

    public function testDomainCoverageRejectsAnEmptyCode(): void
    {
        foreach (['', " \t\n", str_repeat('x', 17)] as $invalid) {
            try {
                $this->operationalRead->domainCoverage('ct', $invalid);
                self::fail('Expected the exact language-code contract to reject the input.');
            } catch (I18nInvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testDomainCoverageUsesAValidLanguageCodeExactlyAsSupplied(): void
    {
        $keyId = $this->createKey('ct', 'home', 'custom-code');
        $this->upsert('custom.CODE', $keyId, 'value', null);

        $counts = [];
        foreach ($this->operationalRead->domainCoverage('ct', 'custom.CODE') as $row) {
            $counts[$row->domainCode] = $row->translatedCount;
        }

        ksort($counts);
        self::assertSame(['auth' => 0, 'cart' => 0, 'home' => 1], $counts);
    }

    public function testSummaryAndKeyStatsRebuildKeepsTheFactsEquivalent(): void
    {
        $before = [
            $this->operationalRead->translatedCountByLanguageCode(),
            $this->operationalRead->scopeKeyCoverage('ct'),
        ];

        $this->rebuilder->fullRebuild();

        self::assertEquals($before[0], $this->operationalRead->translatedCountByLanguageCode());
        self::assertEquals($before[1], $this->operationalRead->scopeKeyCoverage('ct'));
    }
}
