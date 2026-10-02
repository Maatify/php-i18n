<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository\Mysql;

use Maatify\I18n\DTO\DomainCoverageDTO;
use Maatify\I18n\DTO\I18nLanguageCodeCountDTO;
use Maatify\I18n\DTO\I18nStatCountDTO;
use Maatify\I18n\DTO\ScopeKeyCoverageDTO;
use Maatify\I18n\Repository\I18nOperationalStatsRepositoryInterface;
use PDO;

/**
 * Persists and reads i18n operational stats records through the package-owned MySQL schema.
 */
final readonly class MysqlI18nOperationalStatsRepository implements I18nOperationalStatsRepositoryInterface
{
    private PdoGateway $gateway;

    public function __construct(PDO $pdo)
    {
        $this->gateway = new PdoGateway($pdo);
    }

    /** Return the package-owned count of all translation keys. */
    public function totalKeyCount(): int
    {
        return $this->gateway->scalarInt('SELECT COUNT(*) FROM maa_i18n_keys', [], 'stats.totalKeys');
    }

    /**
     * Return derived translated counts per exact language scope; a null code
     * represents the unlocalized scope, and absent codes have no entry. The
     * Host supplies language metadata and any zero-count language entries.
     *
     * @return list<I18nLanguageCodeCountDTO>
     */
    public function translatedCountByLanguageCode(): array
    {
        $rows = $this->gateway->fetchAll(
            'SELECT
                 dls.language_code AS language_code,
                 SUM(dls.translated_count) AS cnt
             FROM maa_i18n_domain_language_summary dls
             GROUP BY dls.language_code',
            [],
            'stats.translatedByLanguage',
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = new I18nLanguageCodeCountDTO(
                Row::nullableString($row, 'language_code'),
                Row::int($row, 'cnt'),
            );
        }

        return $items;
    }

    /**
     * Return package-owned key counts grouped by scope name, highest count first.
     *
     * @return list<I18nStatCountDTO>
     */
    public function keyCountByScope(): array
    {
        $rows = $this->gateway->fetchAll(
            'SELECT
                 s.name AS label,
                 COUNT(*) AS cnt
             FROM maa_i18n_keys k
             JOIN maa_i18n_scopes s ON s.code = k.scope
             GROUP BY k.scope, s.name
             ORDER BY cnt DESC',
            [],
            'stats.keysByScope',
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = new I18nStatCountDTO(Row::string($row, 'label'), Row::int($row, 'cnt'));
        }

        return $items;
    }

    /** Return the number of rows currently stored in the derived language summary. */
    public function summaryRowCount(): int
    {
        return $this->gateway->scalarInt(
            'SELECT COUNT(*) FROM maa_i18n_domain_language_summary',
            [],
            'stats.summaryRows',
        );
    }

    /**
     * Return assigned-domain key totals and translated counts for exact
     * non-null language codes in this scope. The Host supplies language
     * metadata and any zero-count language entries.
     */
    public function scopeKeyCoverage(string $scopeCode): ScopeKeyCoverageDTO
    {
        $total = $this->gateway->scalarInt(
            'SELECT COUNT(*)
             FROM maa_i18n_keys k
             JOIN maa_i18n_domain_scopes ds
                 ON ds.scope_code = k.scope
                AND ds.domain_code = k.domain
             WHERE k.scope = :scope',
            ['scope' => $scopeCode],
            'stats.scopeTotalKeys',
        );

        $rows = $this->gateway->fetchAll(
            'SELECT
                 dls.language_code AS language_code,
                 SUM(dls.translated_count) AS cnt
             FROM maa_i18n_domain_language_summary dls
             JOIN maa_i18n_domain_scopes ds
                 ON ds.scope_code = dls.scope
                AND ds.domain_code = dls.domain
             WHERE dls.scope = :scope
               AND dls.language_code IS NOT NULL
             GROUP BY dls.language_code
             ORDER BY dls.language_code ASC',
            ['scope' => $scopeCode],
            'stats.scopeTranslatedByLanguage',
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = new I18nLanguageCodeCountDTO(Row::string($row, 'language_code'), Row::int($row, 'cnt'));
        }

        return new ScopeKeyCoverageDTO($total, $items);
    }

    /**
     * Return coverage for assigned domains in this exact scope and language
     * code, limited to domains with keys and ordered by most missing, then
     * display position.
     *
     * @return list<DomainCoverageDTO>
     */
    public function domainCoverage(string $scopeCode, string $languageCode): array
    {
        $rows = $this->gateway->fetchAll(
            'SELECT
                 d.id AS domain_id,
                 d.code AS domain_code,
                 d.name AS domain_name,
                 kt.total_keys AS total_keys,
                 COALESCE(dls.translated_count, 0) AS translated_count
             FROM (
                 SELECT k.domain, COUNT(*) AS total_keys
                 FROM maa_i18n_keys k
                 WHERE k.scope = :scope_keys
                 GROUP BY k.domain
             ) kt
             JOIN maa_i18n_domain_scopes ds
                 ON ds.scope_code = :scope_map
                AND ds.domain_code = kt.domain
             JOIN maa_i18n_domains d
                 ON d.code = kt.domain
             LEFT JOIN maa_i18n_domain_language_summary dls
                 ON dls.scope = :scope_summary
                AND dls.domain = kt.domain
                AND dls.language_code = :language_code
             ORDER BY (kt.total_keys - COALESCE(dls.translated_count, 0)) DESC, d.sort_order ASC',
            ([
                'scope_keys' => $scopeCode,
                'scope_map' => $scopeCode,
                'scope_summary' => $scopeCode,
                'language_code' => $languageCode,
            ]),
            'stats.domainCoverage',
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = new DomainCoverageDTO(
                Row::int($row, 'domain_id'),
                Row::string($row, 'domain_code'),
                Row::string($row, 'domain_name'),
                Row::int($row, 'total_keys'),
                Row::int($row, 'translated_count'),
            );
        }

        return $items;
    }
}
