<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Exception\InvalidLanguageCodeException;
use Maatify\I18n\Service\MissingCounterService;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;
use PDO;
use PDOStatement;

/**
 * Protects direct custom-write counter callbacks against real MySQL state.
 */
final class MissingCounterServiceIntegrationTest extends MysqlIntegrationTestCase
{
    public function testInvalidTranslationCallbacksLeaveDerivedPersistenceUnchanged(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');
        $counter = $this->missingCounterService();

        $this->translations->upsert('custom.CODE', $keyId, 'Exact translation', null);
        $counter->onTranslationCreated('custom.CODE', $keyId);
        $before = $this->derivedState();

        foreach (['', " \t\n", str_repeat('x', 17)] as $invalidCode) {
            $this->assertRejectedWithoutDerivedMutation(
                fn() => $counter->onTranslationCreated($invalidCode, $keyId),
                $before,
                'onTranslationCreated',
                $invalidCode,
            );
            $this->assertRejectedWithoutDerivedMutation(
                fn() => $counter->onTranslationDeleted($invalidCode, $keyId),
                $before,
                'onTranslationDeleted',
                $invalidCode,
            );
        }
    }

    public function testDirectCallbacksMaintainExactAndUnlocalizedScopesAfterRealWrites(): void
    {
        $keyId = $this->createKey('ct', 'home', 'title');
        $counter = $this->missingCounterService();

        $this->translations->upsert('custom.CODE', $keyId, 'Exact translation', null);
        $counter->onTranslationCreated('custom.CODE', $keyId);

        self::assertSame(
            ['total_keys' => 1, 'translated_count' => 1, 'missing_count' => 0],
            $this->summary->getRow('ct', 'home', 'custom.CODE'),
        );
        self::assertNull($this->summary->getRow('ct', 'home', 'custom.code'));
        self::assertSame(1, $this->keyStats->getTranslatedCount($keyId));
        self::assertSame('custom.CODE', $this->translations->getByLanguageAndKey('custom.CODE', $keyId)?->languageCode);

        $this->translations->upsert(null, $keyId, 'Unlocalized translation', null);
        $counter->onTranslationCreated(null, $keyId);

        self::assertSame(
            ['total_keys' => 1, 'translated_count' => 1, 'missing_count' => 0],
            $this->summary->getRow('ct', 'home', null),
        );
        self::assertSame(2, $this->keyStats->getTranslatedCount($keyId));

        self::assertTrue($this->translations->deleteByLanguageAndKey(null, $keyId));
        $counter->onTranslationDeleted(null, $keyId);

        self::assertNull($this->summary->getRow('ct', 'home', null));
        self::assertNotNull($this->summary->getRow('ct', 'home', 'custom.CODE'));
        self::assertSame(1, $this->keyStats->getTranslatedCount($keyId));

        self::assertTrue($this->translations->deleteByLanguageAndKey('custom.CODE', $keyId));
        $counter->onTranslationDeleted('custom.CODE', $keyId);

        self::assertNull($this->summary->getRow('ct', 'home', 'custom.CODE'));
        self::assertSame(0, $this->keyStats->getTranslatedCount($keyId));
    }

    public function testRekeyValidatesBothCodesAndRebuildsFromRealAuthoritativeWrites(): void
    {
        $keyIds = [
            $this->createKey('ct', 'home', 'first'),
            $this->createKey('ct', 'home', 'second'),
        ];
        $counter = $this->missingCounterService();
        $oldCode = 'custom.CODE';
        $newCode = 'target.EXACT';

        foreach ($keyIds as $keyId) {
            $this->translations->upsert($oldCode, $keyId, 'Translation ' . $keyId, null);
            $counter->onTranslationCreated($oldCode, $keyId);
        }

        $beforeInvalidRekeys = $this->derivedState();
        foreach (['', " \t\n", str_repeat('x', 17)] as $invalidCode) {
            $this->assertRejectedWithoutDerivedMutation(
                fn() => $counter->onLanguageCodeRekeyed($invalidCode, $newCode),
                $beforeInvalidRekeys,
                'onLanguageCodeRekeyed with invalid oldCode',
                $invalidCode,
            );
            $this->assertRejectedWithoutDerivedMutation(
                fn() => $counter->onLanguageCodeRekeyed($oldCode, $invalidCode),
                $beforeInvalidRekeys,
                'onLanguageCodeRekeyed with invalid newCode',
                $invalidCode,
            );
        }

        $keyStatsBeforeRekey = $this->keyStatsRows();
        self::assertSame(2, $this->translations->rekeyLanguageCode($oldCode, $newCode));
        $counter->onLanguageCodeRekeyed($oldCode, $newCode);

        self::assertNull($this->summary->getRow('ct', 'home', $oldCode));
        self::assertSame(
            ['total_keys' => 2, 'translated_count' => 2, 'missing_count' => 0],
            $this->summary->getRow('ct', 'home', $newCode),
        );
        self::assertNull($this->summary->getRow('ct', 'home', 'target.exact'));
        self::assertFalse($this->translations->hasAnyForLanguage($oldCode));
        self::assertTrue($this->translations->hasAnyForLanguage($newCode));
        self::assertSame($keyStatsBeforeRekey, $this->keyStatsRows());
    }

    private function missingCounterService(): MissingCounterService
    {
        return new MissingCounterService($this->summary, $this->keys, $this->keyStats);
    }

    /**
     * @param callable(): void $callback
     * @param array{
     *     summaries: list<array<string, mixed>>,
     *     keyStats: list<array<string, mixed>>
     * } $before
     */
    private function assertRejectedWithoutDerivedMutation(
        callable $callback,
        array $before,
        string $operation,
        string $invalidCode,
    ): void {
        try {
            $callback();
            self::fail($operation . ' should reject invalid language code of length ' . strlen($invalidCode));
        } catch (InvalidLanguageCodeException) {
            $this->addToAssertionCount(1);
        }

        self::assertSame(
            $before,
            $this->derivedState(),
            $operation . ' changed derived persistence for invalid language code of length ' . strlen($invalidCode),
        );
    }

    /**
     * @return array{
     *     summaries: list<array<string, mixed>>,
     *     keyStats: list<array<string, mixed>>
     * }
     */
    private function derivedState(): array
    {
        $summaryStatement = $this->pdo()->query(
            'SELECT * FROM maa_i18n_domain_language_summary ORDER BY scope, domain, language_code_identity',
        );
        self::assertNotFalse($summaryStatement);

        return [
            'summaries' => $this->fetchRows($summaryStatement),
            'keyStats' => $this->keyStatsRows(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function keyStatsRows(): array
    {
        $keyStatsStatement = $this->pdo()->query(
            'SELECT * FROM maa_i18n_key_stats ORDER BY key_id',
        );
        self::assertNotFalse($keyStatsStatement);

        return $this->fetchRows($keyStatsStatement);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRows(PDOStatement $statement): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }
}
