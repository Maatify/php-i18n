<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Management\Criteria\DomainTranslationGridCriteria;
use Maatify\I18n\Management\Criteria\LanguageTranslationValuesCriteria;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;
use PDOStatement;

final class TranslationTypeLifecycleTest extends MysqlIntegrationTestCase
{
    public function testTypeFlowsThroughThePublicLifecycleWithoutChangingIdentityOrDerivedCounts(): void
    {
        $bodyKey = $this->createKey('ct', 'home', 'body');
        $emptyKey = $this->createKey('ct', 'home', 'empty');
        $missingKey = $this->createKey('ct', 'home', 'missing');
        $markup = '<p>Welcome</p>';
        $opaqueType = ' Rich.Copy ';
        $secondaryType = 'client.rich-copy';

        $bodyId = $this->upsert('ar', $bodyKey, $markup, null);
        $this->upsert(null, $bodyKey, 'Neutral exact scope', $secondaryType);
        $this->upsert(null, $missingKey, 'Neutral ordinary text', null);
        $emptyId = $this->upsert('ar', $emptyKey, '', $opaqueType);

        $beforeTypeOnlyWrites = $this->derivedRows();
        self::assertSame($bodyId, $this->upsert('ar', $bodyKey, $markup, $opaqueType));
        $typed = $this->translations->getByLanguageAndKey('ar', $bodyKey);
        self::assertNotNull($typed);
        self::assertSame([$bodyId, $markup, $opaqueType], [$typed->id, $typed->value, $typed->type]);
        self::assertSame(
            ['value' => $markup, 'type' => $opaqueType],
            $this->reader->getTranslation('ar', 'ct', 'home', 'body')?->jsonSerialize(),
            'the public rich read preserves the consumer token byte-for-byte',
        );
        $persistedTypeStatement = $this->pdo()->prepare(
            'SELECT type FROM maa_i18n_translations WHERE id = :id',
        );
        $persistedTypeStatement->execute(['id' => $bodyId]);
        self::assertSame($opaqueType, $persistedTypeStatement->fetchColumn());

        self::assertSame($bodyId, $this->upsert('ar', $bodyKey, $markup, null));
        $cleared = $this->translations->getByLanguageAndKey('ar', $bodyKey);
        self::assertNotNull($cleared);
        self::assertSame([$bodyId, $markup, null], [$cleared->id, $cleared->value, $cleared->type]);
        self::assertSame($beforeTypeOnlyWrites, $this->derivedRows(), 'type-only writes do not alter derived counts');

        self::assertSame($markup, $this->reader->getValue('ar', 'ct', 'home', 'body'));
        self::assertSame(
            ['value' => $markup, 'type' => null],
            $this->reader->getTranslation('ar', 'ct', 'home', 'body')?->jsonSerialize(),
        );
        self::assertSame(
            ['value' => 'Neutral exact scope', 'type' => $secondaryType],
            $this->reader->getTranslation(null, 'ct', 'home', 'body')?->jsonSerialize(),
        );
        self::assertSame(
            ['value' => 'Neutral ordinary text', 'type' => null],
            $this->reader->getTranslation(null, 'ct', 'home', 'missing')?->jsonSerialize(),
        );
        self::assertNull($this->reader->getTranslation('ar', 'ct', 'home', 'missing'));

        // An empty translation remains an existing authoritative row with a type.
        self::assertSame('', $this->reader->getValue('ar', 'ct', 'home', 'empty'));
        self::assertSame(
            ['value' => '', 'type' => $opaqueType],
            $this->reader->getTranslation('ar', 'ct', 'home', 'empty')?->jsonSerialize(),
        );

        $valueOnly = $this->domainReader->getDomainValues('ar', 'ct', 'home');
        self::assertSame(['body' => $markup, 'empty' => ''], $valueOnly->values);
        $rich = $this->domainReader->getDomainTranslations('ar', 'ct', 'home');
        self::assertSame([$markup, null], [$rich->get('body')?->value, $rich->get('body')?->type]);
        self::assertSame(['', $opaqueType], [
            $rich->get('empty')?->value,
            $rich->get('empty')?->type,
        ]);
        self::assertNull($rich->get('missing'));

        $grid = $this->managementRead->pageDomainTranslationGrid(
            new DomainTranslationGridCriteria('ct', 'home', ['ar']),
        );
        $gridByKey = [];
        foreach ($grid->data as $row) {
            $gridByKey[$row->keyPart] = [$row->translationId, $row->value, $row->type];
        }
        self::assertSame([$bodyId, $markup, null], $gridByKey['body']);
        self::assertSame([$emptyId, '', $opaqueType], $gridByKey['empty']);
        self::assertSame([null, null, null], $gridByKey['missing']);

        $languageRows = $this->managementRead->pageLanguageTranslationValues(new LanguageTranslationValuesCriteria('ar'));
        $languageByKey = [];
        foreach ($languageRows->data as $row) {
            $languageByKey[$row->keyPart] = [$row->translationId, $row->value, $row->type];
        }
        self::assertSame([$bodyId, $markup, null], $languageByKey['body']);
        self::assertSame([$emptyId, '', $opaqueType], $languageByKey['empty']);
        self::assertSame([null, null, null], $languageByKey['missing']);

        $beforeRekeyTypeWrite = $this->derivedRows();
        self::assertSame($bodyId, $this->upsert('ar', $bodyKey, $markup, $opaqueType));
        self::assertSame($beforeRekeyTypeWrite, $this->derivedRows(), 'an arbitrary type-only change leaves derived rows intact');
        self::assertSame(2, $this->writer->rekeyLanguageCode('ar', 'ar-EG'));
        self::assertSame(
            ['value' => $markup, 'type' => $opaqueType],
            $this->reader->getTranslation('ar-EG', 'ct', 'home', 'body')?->jsonSerialize(),
        );
        self::assertSame(
            ['value' => '', 'type' => $opaqueType],
            $this->reader->getTranslation('ar-EG', 'ct', 'home', 'empty')?->jsonSerialize(),
        );
    }

    /**
     * @return array{summary: list<array<string, mixed>>, keyStats: list<array<string, mixed>>}
     */
    private function derivedRows(): array
    {
        $summaryStatement = $this->pdo()->query(
            'SELECT * FROM maa_i18n_domain_language_summary ORDER BY scope, domain, language_code_identity',
        );
        self::assertNotFalse($summaryStatement);

        $keyStatsStatement = $this->pdo()->query(
            'SELECT * FROM maa_i18n_key_stats ORDER BY key_id',
        );
        self::assertNotFalse($keyStatsStatement);

        return [
            'summary' => self::selectRows($summaryStatement),
            'keyStats' => self::selectRows($keyStatsStatement),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function selectRows(PDOStatement $statement): array
    {
        $rows = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                throw new \UnexpectedValueException('PDO returned a non-row result.');
            }

            $typedRow = [];
            foreach ($row as $key => $value) {
                if (!is_string($key)) {
                    throw new \UnexpectedValueException('PDO returned a row with a non-string column name.');
                }

                $typedRow[$key] = $value;
            }

            $rows[] = $typedRow;
        }

        return $rows;
    }
}
