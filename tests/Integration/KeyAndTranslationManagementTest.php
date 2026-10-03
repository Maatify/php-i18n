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

use Maatify\I18n\Exception\I18nExceptionInterface;
use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Exception\InvalidLanguageCodeException;
use Maatify\I18n\Exception\TranslationKeyNotFoundException;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\RenameKeyCommand;
use Maatify\I18n\Management\Command\UpsertTranslationCommand;
use Maatify\I18n\Management\Criteria\DomainKeySummaryCriteria;
use Maatify\I18n\Management\Criteria\DomainTranslationGridCriteria;
use Maatify\I18n\Management\Criteria\KeyListCriteria;
use Maatify\I18n\Management\Criteria\LanguageTranslationValuesCriteria;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;
use Maatify\Persistence\Pdo\Pagination\PageRequest;

final class KeyAndTranslationManagementTest extends MysqlIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo()->exec("INSERT INTO maa_i18n_scopes (code, name) VALUES ('ad', 'Admin')");
        $this->pdo()->exec("INSERT INTO maa_i18n_domains (code, name) VALUES ('auth', 'Auth')");
        $this->pdo()->exec("INSERT INTO maa_i18n_domain_scopes (scope_code, domain_code) VALUES ('ct', 'auth'), ('ad', 'auth')");
    }

    /**
     * @param list<string> $codes
     */
    private function summaryCriteria(
        array $codes,
        bool $onlyMissing = false,
        ?string $search = null,
        ?PageRequest $page = null,
    ): DomainKeySummaryCriteria {
        return new DomainKeySummaryCriteria(
            'ct',
            'home',
            $codes,
            globalSearch: $search,
            onlyMissing: $onlyMissing,
            page: $page ?? new PageRequest(),
        );
    }

    /**
     * @param list<string> $codes
     * @param list<string> $matched
     */
    private function gridCriteria(
        array $codes,
        ?string $search = null,
        array $matched = [],
        ?string $value = null,
    ): DomainTranslationGridCriteria {
        return new DomainTranslationGridCriteria(
            'ct',
            'home',
            $codes,
            globalSearch: $search,
            globalSearchLanguageCodes: $matched,
            valueLike: $value,
        );
    }

    public function testCommandsValidateTheirOwnContract(): void
    {
        foreach ([
            static fn() => new CreateKeyCommand('', 'home', 'k'),
            static fn() => new CreateKeyCommand('ct', ' ', 'k'),
            static fn() => new CreateKeyCommand('ct', 'home', ''),
            static fn() => new CreateKeyCommand(str_repeat('s', 33), 'home', 'k'),
            static fn() => new CreateKeyCommand('ct', str_repeat('d', 65), 'k'),
            static fn() => new CreateKeyCommand('ct', 'home', str_repeat('k', 129)),
            static fn() => new CreateKeyCommand('ct', 'home', 'k', str_repeat('d', 256)),
            static fn() => new RenameKeyCommand(0, 'ct', 'home', 'k'),
            static fn() => new RenameKeyCommand(1, 'ct', 'home', ' '),
            static fn() => new UpsertTranslationCommand(
                languageCode: 'ar',
                keyId: 0,
                value: 'v',
                type: null,
            ),
        ] as $build) {
            try {
                $build();
                self::fail('Expected I18nInvalidArgumentException');
            } catch (I18nInvalidArgumentException $e) {
                self::assertInstanceOf(I18nExceptionInterface::class, $e);
            }
        }

        $this->expectException(InvalidLanguageCodeException::class);
        new UpsertTranslationCommand(
            languageCode: '   ',
            keyId: 1,
            value: 'v',
            type: null,
        );
    }

    public function testAnEmptyValueIsAValidAuthoritativeTranslation(): void
    {
        $key = $this->createKey('ct', 'home', 'k');

        $this->upsert('ar', $key, '', null);

        $row = $this->translations->getByLanguageAndKey('ar', $key);
        self::assertNotNull($row);
        self::assertSame('', $row->value);
    }

    public function testKeySearchIsScopedPaginatedAndFiltered(): void
    {
        foreach (['alpha', 'beta', 'gamma'] as $k) {
            $this->createKey('ct', 'home', $k);
        }
        $this->createKey('ct', 'auth', 'login');
        $this->createKey('ad', 'auth', 'other-scope');

        $page = $this->managementRead->searchKeys(new KeyListCriteria('ct', page: new PageRequest(1, 3)));
        // `total` = global unfiltered key population (5 keys exist); `filtered` = scope + optional filters (4)
        self::assertSame(5, $page->total);
        self::assertSame(4, $page->filtered);
        self::assertSame(2, $page->totalPages);
        self::assertSame(['alpha', 'beta', 'gamma'], array_map(static fn($k) => $k->key, $page->data));

        $byDomain = $this->managementRead->searchKeys(new KeyListCriteria('ct', domainLike: 'auth'));
        self::assertSame(['login'], array_map(static fn($k) => $k->key, $byDomain->data));

        $search = $this->managementRead->searchKeys(new KeyListCriteria('ct', globalSearch: 'amm'));
        self::assertSame(['gamma'], array_map(static fn($k) => $k->key, $search->data));

        $filteredOut = $this->managementRead->searchKeys(new KeyListCriteria('ct', domainLike: 'auth'));
        self::assertSame([5, 1], [$filteredOut->total, $filteredOut->filtered]);

        $none = $this->managementRead->searchKeys(new KeyListCriteria('unknown-scope'));
        self::assertSame([5, 0], [$none->total, $none->filtered]);
        self::assertSame([], $none->data);

        $detail = $this->managementRead->getKey($page->data[0]->id);
        self::assertSame('alpha', $detail->key);

        $this->expectException(TranslationKeyNotFoundException::class);
        $this->managementRead->getKey(999999);
    }

    public function testRenameMovesTheKeyAndItsDerivedSummaryFollows(): void
    {
        $a = $this->createKey('ct', 'home', 'a');
        $this->upsert('ar', $a, 'x', null);

        $this->renameKey($a, 'ct', 'auth', 'a2');

        $row = $this->keys->getById($a);
        self::assertNotNull($row);
        self::assertSame(['ct', 'auth', 'a2'], [$row->scope, $row->domain, $row->key]);
        self::assertSame(['total_keys' => 1, 'translated_count' => 1, 'missing_count' => 0], $this->summary->getRow('ct', 'auth', 'ar'));
        self::assertNull($this->summary->getRow('ct', 'home', 'ar'));
    }

    public function testDomainKeySummaryCountsOnlyTheSuppliedExactCodes(): void
    {
        $a = $this->createKey('ct', 'home', 'a');
        $b = $this->createKey('ct', 'home', 'b');
        $c = $this->createKey('ct', 'home', 'c');
        $this->upsert('ar', $a, 'x', null);
        $this->upsert('en', $a, 'x', null);
        $this->upsert('ar', $b, 'x', null);
        $this->upsert('AR', $c, 'x', null); // different exact code: never matches 'ar'
        $this->upsert(null, $c, 'x', null);

        $all = $this->managementRead->pageDomainKeySummaries($this->summaryCriteria(['ar', 'en']));
        $byKey = [];
        foreach ($all->data as $row) {
            $byKey[$row->keyPart] = [$row->totalLanguages, $row->missingCount];
        }
        self::assertSame(['a' => [2, 0], 'b' => [2, 1], 'c' => [2, 2]], $byKey);
        self::assertSame(3, $all->total);

        $missing = $this->managementRead->pageDomainKeySummaries($this->summaryCriteria(['ar', 'en'], true));
        self::assertSame(['b', 'c'], array_map(static fn($r) => $r->keyPart, $missing->data));
        self::assertSame(2, $missing->filtered);
        self::assertSame(3, $missing->total);

        $noCodes = $this->managementRead->pageDomainKeySummaries($this->summaryCriteria([]));
        self::assertSame([0, 0], [$noCodes->data[0]->totalLanguages, $noCodes->data[0]->missingCount]);

        $search = $this->managementRead->pageDomainKeySummaries($this->summaryCriteria(['ar'], false, 'b'));
        self::assertSame(['b'], array_map(static fn($r) => $r->keyPart, $search->data));

        $paged = $this->managementRead->pageDomainKeySummaries($this->summaryCriteria(['ar'], false, null, new PageRequest(2, 2)));
        self::assertSame(['c'], array_map(static fn($r) => $r->keyPart, $paged->data));
        self::assertTrue($paged->hasPrevious);
    }

    public function testDomainTranslationGridIsKeysTimesSuppliedCodes(): void
    {
        $a = $this->createKey('ct', 'home', 'a');
        $b = $this->createKey('ct', 'home', 'b');
        $this->upsert('ar', $a, 'مرحبا', null);
        $this->upsert('en', $b, 'Hello', null);

        $page = $this->managementRead->pageDomainTranslationGrid($this->gridCriteria(['ar', 'en']));
        self::assertSame(4, $page->total);
        self::assertSame(4, $page->filtered);
        $cells = [];
        foreach ($page->data as $row) {
            $cells[] = [$row->keyPart, $row->languageCode, $row->value];
        }
        self::assertSame([
            ['a', 'ar', 'مرحبا'],
            ['a', 'en', null],
            ['b', 'ar', null],
            ['b', 'en', 'Hello'],
        ], $cells);
        self::assertNotNull($page->data[0]->translationId);
        self::assertNull($page->data[1]->translationId);

        $byValue = $this->managementRead->pageDomainTranslationGrid($this->gridCriteria(['ar', 'en'], null, [], 'ell'));
        self::assertSame([['b', 'en']], array_map(static fn($r) => [$r->keyPart, $r->languageCode], $byValue->data));

        // free text: key part, value, the exact code, or Host-matched codes
        $bySearch = $this->managementRead->pageDomainTranslationGrid($this->gridCriteria(['ar', 'en'], 'zzz', ['en']));
        self::assertSame([['a', 'en'], ['b', 'en']], array_map(static fn($r) => [$r->keyPart, $r->languageCode], $bySearch->data));

        $empty = $this->managementRead->pageDomainTranslationGrid($this->gridCriteria([]));
        self::assertSame([0, 0, []], [$empty->total, $empty->filtered, $empty->data]);
    }

    public function testLanguageTranslationValuesListEveryKeyForOneExactCode(): void
    {
        $a = $this->createKey('ct', 'home', 'a');
        $b = $this->createKey('ct', 'auth', 'b');
        $this->upsert('ar', $a, 'A-ar', null);
        $this->upsert('AR', $b, 'B-AR', null);

        $page = $this->managementRead->pageLanguageTranslationValues(new LanguageTranslationValuesCriteria('ar'));
        self::assertSame(2, $page->total);
        self::assertSame([['a', 'A-ar'], ['b', null]], array_map(static fn($r) => [$r->keyPart, $r->value], $page->data));
        self::assertNotNull($page->data[0]->translationId);
        self::assertNull($page->data[1]->translationId);

        $unknown = $this->managementRead->pageLanguageTranslationValues(new LanguageTranslationValuesCriteria('xx'));
        self::assertSame([null, null], array_map(static fn($r) => $r->value, $unknown->data));

        $filtered = $this->managementRead->pageLanguageTranslationValues(
            new LanguageTranslationValuesCriteria('ar', valueLike: 'A-'),
        );
        self::assertSame(['a'], array_map(static fn($r) => $r->keyPart, $filtered->data));
        self::assertSame(1, $filtered->filtered);
        self::assertSame(2, $filtered->total);
    }
}
