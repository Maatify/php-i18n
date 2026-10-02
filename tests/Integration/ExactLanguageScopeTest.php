<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Exception\InvalidLanguageCodeException;
use Maatify\I18n\Exception\LanguageCodeAlreadyInUseException;
use Maatify\I18n\Exception\TranslationKeyNotFoundException;
use Maatify\I18n\Repository\Mysql\MysqlI18nOperationalStatsRepository;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;
use PDO;
use PDOException;

/**
 * Real-database proof of the ADR-019 I18n contract on a schema that contains
 * ONLY the I18n tables — there is no `languages` table at all, so every
 * assertion below also proves I18n needs no Host language data.
 */
final class ExactLanguageScopeTest extends MysqlIntegrationTestCase
{
    // ── exact reads ─────────────────────────────────────────────────────────

    public function testExactArReadReturnsArOnly(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');
        $this->upsert('ar', $keyId, 'عنوان', null);
        $this->upsert('en', $keyId, 'Title', null);
        $this->upsert(null, $keyId, 'Neutral', null);

        self::assertSame('عنوان', $this->reader->getValue('ar', 'ct', 'home', 'title'));
    }

    public function testExactEnReadReturnsEnOnly(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');
        $this->upsert('ar', $keyId, 'عنوان', null);
        $this->upsert('en', $keyId, 'Title', null);

        self::assertSame('Title', $this->reader->getValue('en', 'ct', 'home', 'title'));
    }

    public function testExactNullReadReturnsUnlocalizedScopeOnly(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');
        $this->upsert('ar', $keyId, 'عنوان', null);
        $this->upsert(null, $keyId, 'Neutral', null);

        self::assertSame('Neutral', $this->reader->getValue(null, 'ct', 'home', 'title'));
    }

    public function testMissingArDoesNotFallBackToNull(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');
        $this->upsert(null, $keyId, 'Neutral', null);

        self::assertNull($this->reader->getValue('ar', 'ct', 'home', 'title'));
    }

    public function testMissingArDoesNotFallBackToEn(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');
        $this->upsert('en', $keyId, 'Title', null);

        self::assertNull($this->reader->getValue('ar', 'ct', 'home', 'title'));
        self::assertSame([], $this->domainReader->getDomainValues('ar', 'ct', 'home')->all());
    }

    public function testNullDoesNotFallBackToALanguageCode(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');
        $this->upsert('ar', $keyId, 'عنوان', null);
        $this->upsert('en', $keyId, 'Title', null);

        self::assertNull($this->reader->getValue(null, 'ct', 'home', 'title'));
        self::assertSame([], $this->domainReader->getDomainValues(null, 'ct', 'home')->all());
    }

    public function testDomainReadIsExactPerScope(): void
    {
        $a = $this->createKey('ct', 'home', 'a');
        $b = $this->createKey('ct', 'home', 'b');
        $this->upsert('ar', $a, 'A-ar', null);
        $this->upsert('en', $b, 'B-en', null);
        $this->upsert(null, $a, 'A-null', null);

        self::assertSame(['a' => 'A-ar'], $this->domainReader->getDomainValues('ar', 'ct', 'home')->all());
        self::assertSame(['b' => 'B-en'], $this->domainReader->getDomainValues('en', 'ct', 'home')->all());
        self::assertSame(['a' => 'A-null'], $this->domainReader->getDomainValues(null, 'ct', 'home')->all());
    }

    // ── no semantic language validation / storage contract ─────────────────

    public function testUnknownButSyntacticallyValidCodeIsStoredWithoutAnyLanguageLookup(): void
    {
        // There is no `languages` table in this schema: any lookup would fail loudly.
        $keyId = $this->createKey('ct', 'home', 'title');
        $this->upsert('xx-unknown', $keyId, 'Anything', null);

        self::assertSame('Anything', $this->reader->getValue('xx-unknown', 'ct', 'home', 'title'));
        self::assertSame(0, $this->scalarInt("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'languages'"));
    }

    public function testCodeIsStoredExactlyWithoutNormalization(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');
        $this->upsert('ar-EG', $keyId, 'Egypt', null);
        $this->upsert('AR', $keyId, 'Upper', null);
        $this->upsert('ar', $keyId, 'Lower', null);

        self::assertSame('Egypt', $this->reader->getValue('ar-EG', 'ct', 'home', 'title'));
        self::assertNull($this->reader->getValue('ar-eg', 'ct', 'home', 'title'));
        self::assertSame('Upper', $this->reader->getValue('AR', 'ct', 'home', 'title'));
        self::assertSame('Lower', $this->reader->getValue('ar', 'ct', 'home', 'title'));
    }

    public function testInvalidCodesAreRejectedByTheStorageContract(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');

        foreach (['', '   ', str_repeat('a', 17)] as $bad) {
            try {
                $this->upsert($bad, $keyId, 'x', null);
                self::fail('Expected InvalidLanguageCodeException for code of length ' . strlen($bad));
            } catch (InvalidLanguageCodeException) {
                $this->addToAssertionCount(1);
            }
        }

        // Reads are fail-soft for an invalid code.
        self::assertNull($this->reader->getValue('', 'ct', 'home', 'title'));
        self::assertSame(0, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_translations'));
    }

    public function testWriteForAnUnknownKeyStillFailsHard(): void
    {
        $this->expectException(TranslationKeyNotFoundException::class);
        $this->upsert('ar', 999999, 'x', null);
    }

    // ── persistence identity ───────────────────────────────────────────────

    public function testDatabaseRejectsDuplicateNullScopeForTheSameKey(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');
        $this->pdo()->exec("INSERT INTO maa_i18n_translations (key_id, language_code, value) VALUES ({$keyId}, NULL, 'one')");

        $this->expectException(PDOException::class);
        $this->pdo()->exec("INSERT INTO maa_i18n_translations (key_id, language_code, value) VALUES ({$keyId}, NULL, 'two')");
    }

    public function testDatabaseRejectsDuplicateExactCodeForTheSameKey(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');
        $this->pdo()->exec("INSERT INTO maa_i18n_translations (key_id, language_code, value) VALUES ({$keyId}, 'ar', 'one')");

        $this->expectException(PDOException::class);
        $this->pdo()->exec("INSERT INTO maa_i18n_translations (key_id, language_code, value) VALUES ({$keyId}, 'ar', 'two')");
    }

    public function testDatabaseRejectsEmptyWhitespaceAndOverlongCodes(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');

        foreach (["''", "'   '", "'" . str_repeat('a', 17) . "'"] as $literal) {
            try {
                $this->pdo()->exec("INSERT INTO maa_i18n_translations (key_id, language_code, value) VALUES ({$keyId}, {$literal}, 'x')");
                self::fail('Expected the database to reject language_code ' . $literal);
            } catch (PDOException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testSameKeyMayOwnNullArAndEnAndUpsertIsIdempotentPerScope(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');

        $nullId = $this->upsert(null, $keyId, 'n', null);
        $arId = $this->upsert('ar', $keyId, 'a', null);
        $enId = $this->upsert('en', $keyId, 'e', null);

        self::assertCount(3, array_unique([$nullId, $arId, $enId]));
        self::assertSame(3, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_translations'));

        // Upserting the same exact scope updates the same row, never adds one.
        self::assertSame($arId, $this->upsert('ar', $keyId, 'a2', null));
        self::assertSame($nullId, $this->upsert(null, $keyId, 'n2', null));
        self::assertSame(3, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_translations'));
        self::assertSame('a2', $this->reader->getValue('ar', 'ct', 'home', 'title'));
        self::assertSame('n2', $this->reader->getValue(null, 'ct', 'home', 'title'));
    }

    // ── summary / coverage ─────────────────────────────────────────────────

    public function testSummaryCountsAreExactPerScopeAndRowsExistOnlyWhereTranslationsExist(): void
    {
        $a = $this->createKey('ct', 'home', 'a');
        $b = $this->createKey('ct', 'home', 'b');
        $c = $this->createKey('ct', 'home', 'c');

        // No translation yet: no summary row for anything (no language universe).
        self::assertNull($this->summary->getRow('ct', 'home', 'ar'));
        self::assertSame(0, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_domain_language_summary'));

        $this->upsert('ar', $a, 'x', null);
        $this->upsert('ar', $b, 'x', null);
        $this->upsert('en', $a, 'x', null);
        $this->upsert(null, $c, 'x', null);

        self::assertSame(['total_keys' => 3, 'translated_count' => 2, 'missing_count' => 1], $this->summary->getRow('ct', 'home', 'ar'));
        self::assertSame(['total_keys' => 3, 'translated_count' => 1, 'missing_count' => 2], $this->summary->getRow('ct', 'home', 'en'));
        self::assertSame(['total_keys' => 3, 'translated_count' => 1, 'missing_count' => 2], $this->summary->getRow('ct', 'home', null));
        self::assertNull($this->summary->getRow('ct', 'home', 'fr'));

        // A key created later raises total_keys / missing_count of existing rows only.
        $this->createKey('ct', 'home', 'd');
        self::assertSame(['total_keys' => 4, 'translated_count' => 2, 'missing_count' => 2], $this->summary->getRow('ct', 'home', 'ar'));
        self::assertNull($this->summary->getRow('ct', 'home', 'fr'));

        // Deleting the last translation of an exact scope removes its row.
        $this->writer->deleteTranslation('en', $a);
        self::assertNull($this->summary->getRow('ct', 'home', 'en'));
        $this->writer->deleteTranslation('ar', $a);
        self::assertSame(['total_keys' => 4, 'translated_count' => 1, 'missing_count' => 3], $this->summary->getRow('ct', 'home', 'ar'));
    }

    public function testKeyMoveRecomputesBothScopeDomainsExactly(): void
    {
        $this->pdo()->exec("INSERT INTO maa_i18n_domains (code, name) VALUES ('cart', 'Cart')");
        $this->pdo()->exec("INSERT INTO maa_i18n_domain_scopes (scope_code, domain_code) VALUES ('ct', 'cart')");

        $a = $this->createKey('ct', 'home', 'a');
        $b = $this->createKey('ct', 'home', 'b');
        $this->upsert('ar', $a, 'x', null);
        $this->upsert('ar', $b, 'x', null);

        $this->renameKey($a, 'ct', 'cart', 'a');

        self::assertSame(['total_keys' => 1, 'translated_count' => 1, 'missing_count' => 0], $this->summary->getRow('ct', 'home', 'ar'));
        self::assertSame(['total_keys' => 1, 'translated_count' => 1, 'missing_count' => 0], $this->summary->getRow('ct', 'cart', 'ar'));
    }

    public function testRebuildIsDeterministicAndReadsOnlyAuthoritativeI18nTables(): void
    {
        $a = $this->createKey('ct', 'home', 'a');
        $b = $this->createKey('ct', 'home', 'b');
        $this->upsert('ar', $a, 'x', null);
        $this->upsert('en', $a, 'x', null);
        $this->upsert('en', $b, 'x', null);
        $this->upsert(null, $b, 'x', null);

        $incremental = $this->summarySnapshot();
        self::assertNotSame([], $incremental);

        // Corrupt the derived layers, then rebuild from authoritative tables only.
        $this->pdo()->exec('UPDATE maa_i18n_domain_language_summary SET translated_count = 99, missing_count = 99');
        $this->pdo()->exec('DELETE FROM maa_i18n_key_stats');

        $this->rebuilder->fullRebuild();
        $first = $this->summarySnapshot();
        $this->rebuilder->fullRebuild();
        $second = $this->summarySnapshot();

        self::assertSame($incremental, $first, 'rebuild must reproduce the incremental state exactly');
        self::assertSame($first, $second, 'rebuild must be deterministic/idempotent');
        self::assertSame(2, $this->scalarInt('SELECT translated_count FROM maa_i18n_key_stats WHERE key_id = ' . $b));
    }

    public function testDashboardReaderExposesExactCodeCountsWithoutLanguageTable(): void
    {
        $pdo = $this->pdo();

        $a = $this->createKey('ct', 'home', 'a');
        $b = $this->createKey('ct', 'home', 'b');
        $this->upsert('ar', $a, 'x', null);
        $this->upsert('ar', $b, 'x', null);
        $this->upsert('en', $a, 'x', null);

        $reader = new MysqlI18nOperationalStatsRepository($pdo);

        self::assertSame(2, $reader->totalKeyCount());

        $byCode = [];
        foreach ($reader->translatedCountByLanguageCode() as $row) {
            $byCode[$row->languageCode ?? '(null)'] = $row->count;
        }
        ksort($byCode);
        self::assertSame(['ar' => 2, 'en' => 1], $byCode);
    }

    // ── language-code rename (I18n side) ───────────────────────────────────

    public function testRekeyMovesTranslationsAndSummaryAndLeavesNoOrphans(): void
    {
        $a = $this->createKey('ct', 'home', 'a');
        $b = $this->createKey('ct', 'home', 'b');
        $this->upsert('ar', $a, 'A', null);
        $this->upsert('ar', $b, 'B', null);
        $this->upsert('en', $a, 'E', null);

        self::assertSame(2, $this->writer->rekeyLanguageCode('ar', 'ar-EG'));

        self::assertNull($this->reader->getValue('ar', 'ct', 'home', 'a'));
        self::assertSame('A', $this->reader->getValue('ar-EG', 'ct', 'home', 'a'));
        self::assertSame('B', $this->reader->getValue('ar-EG', 'ct', 'home', 'b'));
        self::assertSame('E', $this->reader->getValue('en', 'ct', 'home', 'a'));

        self::assertNull($this->summary->getRow('ct', 'home', 'ar'));
        self::assertSame(['total_keys' => 2, 'translated_count' => 2, 'missing_count' => 0], $this->summary->getRow('ct', 'home', 'ar-EG'));
        self::assertSame(0, $this->scalarInt("SELECT COUNT(*) FROM maa_i18n_translations WHERE language_code = 'ar'"));
    }

    public function testRekeyIntoACodeThatAlreadyOwnsTranslationsFailsAndChangesNothing(): void
    {
        $a = $this->createKey('ct', 'home', 'a');
        $this->upsert('ar', $a, 'A', null);
        $this->upsert('en', $a, 'E', null);

        try {
            $this->writer->rekeyLanguageCode('ar', 'en');
            self::fail('Expected LanguageCodeAlreadyInUseException');
        } catch (LanguageCodeAlreadyInUseException) {
            $this->addToAssertionCount(1);
        }

        self::assertSame('A', $this->reader->getValue('ar', 'ct', 'home', 'a'));
        self::assertSame('E', $this->reader->getValue('en', 'ct', 'home', 'a'));
    }

    // ── helpers ────────────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    private function summarySnapshot(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT scope, domain, language_code, total_keys, translated_count, missing_count
             FROM maa_i18n_domain_language_summary
             ORDER BY scope, domain, language_code_identity',
        );
        self::assertNotFalse($stmt);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }
}
