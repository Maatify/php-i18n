<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository\Mysql;

use Maatify\I18n\DTO\KeyTranslationSummaryDTO;
use Maatify\I18n\DTO\LanguageTranslationValueDTO;
use Maatify\I18n\DTO\TranslationGridRowDTO;
use Maatify\I18n\Management\Criteria\DomainKeySummaryCriteria;
use Maatify\I18n\Management\Criteria\DomainTranslationGridCriteria;
use Maatify\I18n\Management\Criteria\LanguageTranslationValuesCriteria;
use Maatify\I18n\Repository\TranslationQueryRepositoryInterface;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use Maatify\Persistence\Pdo\Pagination\PdoPaginator;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;
use PDO;

/**
 * Persists and reads translation query records through the package-owned MySQL schema.
 */
final readonly class MysqlTranslationQueryRepository implements TranslationQueryRepositoryInterface
{
    private PdoGateway $gateway;

    public function __construct(
        PDO $pdo,
        private PdoPaginator $paginator = new PdoPaginator(),
    ) {
        $this->gateway = new PdoGateway($pdo);
    }

    public function pageDomainKeySummaries(DomainKeySummaryCriteria $criteria): PageResult
    {
        $codes = array_values(array_unique($criteria->languageCodes));
        $totalLanguages = count($codes);

        // translated = rows of the key owning exactly one of the supplied codes
        $translatedParams = [];
        if ($codes === []) {
            $translatedSql = '0';
        } else {
            $placeholders = [];
            foreach ($codes as $i => $code) {
                $placeholders[] = ':lc' . $i;
                $translatedParams['lc' . $i] = $code;
            }
            $translatedSql = '(SELECT COUNT(*) FROM maa_i18n_translations t
                               WHERE t.key_id = k.id
                                 AND t.language_code IN (' . implode(',', $placeholders) . '))';
        }

        $where = ['k.scope = :scope', 'k.domain = :domain'];
        $filterParams = ['scope' => $criteria->scopeCode, 'domain' => $criteria->domainCode];

        if ($criteria->globalSearch !== null && trim($criteria->globalSearch) !== '') {
            $like = Row::like(trim($criteria->globalSearch));
            $where[] = '(k.key_part LIKE :g_key OR k.description LIKE :g_desc)';
            $filterParams['g_key'] = $like;
            $filterParams['g_desc'] = $like;
        }

        if ($criteria->keyId !== null) {
            $where[] = 'k.id = :key_id';
            $filterParams['key_id'] = $criteria->keyId;
        }

        if ($criteria->keyPart !== null) {
            $where[] = 'k.key_part = :key_part';
            $filterParams['key_part'] = trim($criteria->keyPart);
        }

        $inner = 'SELECT k.id, k.key_part, k.description,
                         ' . $totalLanguages . ' AS total_languages,
                         (' . $totalLanguages . ' - ' . $translatedSql . ') AS missing_count
                  FROM maa_i18n_keys k
                  WHERE ' . implode(' AND ', $where);

        $outerWhere = $criteria->onlyMissing ? ' WHERE x.missing_count > 0' : '';
        $params = $filterParams + $translatedParams;

        $descriptor = new PdoPaginationQueryDescriptor(
            totalSql: 'SELECT COUNT(*) FROM maa_i18n_keys WHERE scope = :scope AND domain = :domain',
            totalParams: ['scope' => $criteria->scopeCode, 'domain' => $criteria->domainCode],
            filteredCountSql: 'SELECT COUNT(*) FROM (' . $inner . ') x' . $outerWhere,
            filteredCountParams: $params,
            dataSql: 'SELECT x.id, x.key_part, x.description, x.total_languages, x.missing_count
                      FROM (' . $inner . ') x' . $outerWhere,
            dataParams: $params,
        );

        $config = new PaginationConfig(
            sortWhitelist: new SortWhitelist([
                'id' => 'x.id',
                'key_part' => 'x.key_part',
                'missing_count' => 'x.missing_count',
            ]),
            defaultSortBy: 'key_part',
            defaultSortDirection: SortDirectionEnum::ASC,
            tieBreakerSortBy: 'id',
            tieBreakerDirection: SortDirectionEnum::ASC,
        );

        return $this->paginator->paginate(
            $this->gateway->pdo(),
            $descriptor,
            $criteria->page,
            $config,
            static fn(array $row): KeyTranslationSummaryDTO => new KeyTranslationSummaryDTO(
                Row::int($row, 'id'),
                Row::string($row, 'key_part'),
                Row::nullableString($row, 'description'),
                Row::int($row, 'total_languages'),
                Row::int($row, 'missing_count'),
            ),
        );
    }

    public function pageDomainTranslationGrid(DomainTranslationGridCriteria $criteria): PageResult
    {
        $codes = array_values(array_unique($criteria->languageCodes));

        // Supplied exact codes as a derived table. Prefix is unique per use so
        // every placeholder appears once per statement.
        $codeTable = static function (string $prefix) use ($codes): array {
            if ($codes === []) {
                return [
                    'SELECT CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_bin AS language_code FROM DUAL WHERE 1 = 0',
                    [],
                ];
            }

            $parts = [];
            $params = [];
            foreach ($codes as $i => $code) {
                $name = $prefix . $i;
                $parts[] = 'SELECT CAST(:' . $name . ' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_bin'
                    . ($i === 0 ? ' AS language_code' : '');
                $params[$name] = $code;
            }

            return [implode(' UNION ALL ', $parts), $params];
        };

        $where = ['k.scope = :scope', 'k.domain = :domain'];
        $filterParams = ['scope' => $criteria->scopeCode, 'domain' => $criteria->domainCode];

        if ($criteria->globalSearch !== null && trim($criteria->globalSearch) !== '') {
            $like = Row::like(trim($criteria->globalSearch));
            $or = [
                'k.key_part LIKE :g_key',
                'k.description LIKE :g_desc',
                't.value LIKE :g_value',
                'lc.language_code COLLATE utf8mb4_unicode_ci LIKE :g_code',
            ];
            $filterParams['g_key'] = $like;
            $filterParams['g_desc'] = $like;
            $filterParams['g_value'] = $like;
            $filterParams['g_code'] = $like;

            $matched = array_values(array_unique($criteria->globalSearchLanguageCodes));
            if ($matched !== []) {
                $in = [];
                foreach ($matched as $i => $code) {
                    $in[] = ':g_lc' . $i;
                    $filterParams['g_lc' . $i] = $code;
                }
                $or[] = 'lc.language_code IN (' . implode(',', $in) . ')';
            }

            $where[] = '(' . implode(' OR ', $or) . ')';
        }

        if ($criteria->keyId !== null) {
            $where[] = 'k.id = :key_id';
            $filterParams['key_id'] = $criteria->keyId;
        }

        if ($criteria->keyPartLike !== null) {
            $where[] = 'k.key_part LIKE :key_part_like';
            $filterParams['key_part_like'] = Row::like(trim($criteria->keyPartLike));
        }

        if ($criteria->valueLike !== null) {
            $where[] = 't.value LIKE :value_like';
            $filterParams['value_like'] = Row::like(trim($criteria->valueLike));
        }

        $from = static fn(string $codeSql): string => 'FROM maa_i18n_keys k
             CROSS JOIN (' . $codeSql . ') lc
             LEFT JOIN maa_i18n_translations t
                 ON t.key_id = k.id
                AND t.language_code = lc.language_code';

        [$totalCodeSql, $totalCodeParams] = $codeTable('t_lc');
        [$filteredCodeSql, $filteredCodeParams] = $codeTable('f_lc');
        [$dataCodeSql, $dataCodeParams] = $codeTable('d_lc');

        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $descriptor = new PdoPaginationQueryDescriptor(
            totalSql: 'SELECT COUNT(*) ' . $from($totalCodeSql) . ' WHERE k.scope = :scope AND k.domain = :domain',
            totalParams: ['scope' => $criteria->scopeCode, 'domain' => $criteria->domainCode] + $totalCodeParams,
            filteredCountSql: 'SELECT COUNT(*) ' . $from($filteredCodeSql) . $whereSql,
            filteredCountParams: $filterParams + $filteredCodeParams,
            dataSql: 'SELECT t.id AS translation_id, k.id AS key_id, k.key_part, k.description,
                             lc.language_code, t.value, t.type ' . $from($dataCodeSql) . $whereSql,
            dataParams: $filterParams + $dataCodeParams,
        );

        $config = new PaginationConfig(
            sortWhitelist: new SortWhitelist([
                'key_part' => 'k.key_part',
                'language_code' => 'lc.language_code',
            ]),
            defaultSortBy: 'key_part',
            defaultSortDirection: SortDirectionEnum::ASC,
            tieBreakerSortBy: 'language_code',
            tieBreakerDirection: SortDirectionEnum::ASC,
        );

        return $this->paginator->paginate(
            $this->gateway->pdo(),
            $descriptor,
            $criteria->page,
            $config,
            static fn(array $row): TranslationGridRowDTO => new TranslationGridRowDTO(
                Row::nullableInt($row, 'translation_id'),
                Row::int($row, 'key_id'),
                Row::string($row, 'key_part'),
                Row::nullableString($row, 'description'),
                Row::string($row, 'language_code'),
                Row::nullableString($row, 'value'),
                Row::nullableString($row, 'type'),
            ),
        );
    }

    public function pageLanguageTranslationValues(LanguageTranslationValuesCriteria $criteria): PageResult
    {
        $where = [];
        $filterParams = [];

        if ($criteria->globalSearch !== null && trim($criteria->globalSearch) !== '') {
            $like = Row::like(trim($criteria->globalSearch));
            $where[] = '(k.scope LIKE :g_scope OR k.domain LIKE :g_domain OR k.key_part LIKE :g_key OR t.value LIKE :g_value)';
            $filterParams['g_scope'] = $like;
            $filterParams['g_domain'] = $like;
            $filterParams['g_key'] = $like;
            $filterParams['g_value'] = $like;
        }

        if ($criteria->id !== null) {
            $where[] = 'k.id = :id';
            $filterParams['id'] = $criteria->id;
        }

        if ($criteria->scopeLike !== null) {
            $where[] = 'k.scope LIKE :scope_like';
            $filterParams['scope_like'] = Row::like(trim($criteria->scopeLike));
        }

        if ($criteria->domainLike !== null) {
            $where[] = 'k.domain LIKE :domain_like';
            $filterParams['domain_like'] = Row::like(trim($criteria->domainLike));
        }

        if ($criteria->keyPartLike !== null) {
            $where[] = 'k.key_part LIKE :key_part_like';
            $filterParams['key_part_like'] = Row::like(trim($criteria->keyPartLike));
        }

        if ($criteria->valueLike !== null) {
            $where[] = 't.value LIKE :value_like';
            $filterParams['value_like'] = Row::like(trim($criteria->valueLike));
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $from = 'FROM maa_i18n_keys k
             LEFT JOIN maa_i18n_translations t
                 ON t.key_id = k.id
                AND t.language_code = :language_code';
        $joinParams = ['language_code' => $criteria->languageCode];

        $descriptor = new PdoPaginationQueryDescriptor(
            totalSql: 'SELECT COUNT(*) FROM maa_i18n_keys',
            totalParams: [],
            filteredCountSql: 'SELECT COUNT(*) ' . $from . $whereSql,
            filteredCountParams: $filterParams + $joinParams,
            dataSql: 'SELECT k.id AS key_id, k.scope, k.domain, k.key_part,
                             t.id AS translation_id, t.value, t.type,
                             COALESCE(t.created_at, k.created_at) AS created_at,
                             t.updated_at ' . $from . $whereSql,
            dataParams: $filterParams + $joinParams,
        );

        $config = new PaginationConfig(
            sortWhitelist: new SortWhitelist([
                'key_id' => 'k.id',
                'scope' => 'k.scope',
                'domain' => 'k.domain',
                'key_part' => 'k.key_part',
            ]),
            defaultSortBy: 'key_id',
            defaultSortDirection: SortDirectionEnum::ASC,
            tieBreakerSortBy: 'key_id',
            tieBreakerDirection: SortDirectionEnum::ASC,
        );

        return $this->paginator->paginate(
            $this->gateway->pdo(),
            $descriptor,
            $criteria->page,
            $config,
            static fn(array $row): LanguageTranslationValueDTO => new LanguageTranslationValueDTO(
                Row::int($row, 'key_id'),
                Row::string($row, 'scope'),
                Row::string($row, 'domain'),
                Row::string($row, 'key_part'),
                Row::nullableInt($row, 'translation_id'),
                Row::nullableString($row, 'value'),
                Row::nullableString($row, 'type'),
                Row::string($row, 'created_at'),
                Row::nullableString($row, 'updated_at'),
            ),
        );
    }
}
