<?php

declare(strict_types=1);

namespace Maatify\I18n\Repository\Mysql;

use Maatify\I18n\Repository\DomainLanguageSummaryRepositoryInterface;
use Maatify\I18n\ValueObject\LanguageCode;
use PDO;

/**
 * Exact-scope derived summary (ADR-019).
 *
 * Every statement reads only maa_i18n_keys / maa_i18n_translations /
 * maa_i18n_domain_language_summary. It never reads a Host language table and never
 * materializes a row for a language that has no translations.
 */
final readonly class MysqlDomainLanguageSummaryRepository implements DomainLanguageSummaryRepositoryInterface
{
    private PdoGateway $gateway;

    public function __construct(PDO $pdo)
    {
        $this->gateway = new PdoGateway($pdo);
    }

    /* ==========================================================
     * INCREMENTAL
     * ========================================================== */

    /**
     * Increment total and missing counts on existing derived rows for this
     * scope/domain; this does not create rows for language scopes without translations.
     */
    public function incrementTotalKeys(string $scope, string $domain): void
    {
        $this->run(
            'UPDATE maa_i18n_domain_language_summary
             SET total_keys = total_keys + 1,
                 missing_count = missing_count + 1
             WHERE scope = :scope
               AND domain = :domain',
            ([
                'scope'  => $scope,
                'domain' => $domain,
            ]),
        );
    }

    /** Rebuild all language rows for this scope/domain from keys and translations. */
    public function decrementTotalKeys(string $scope, string $domain): void
    {
        // safest: derived table → rebuild this scope+domain instead of guessing deltas
        $this->rebuildScopeDomain($scope, $domain);
    }

    /**
     * Rebuild this exact nullable language scope from authoritative keys and
     * translations; null is the unlocalized scope, and a row is removed when
     * no translation remains. The supplied non-null code is not normalized.
     */
    public function refreshExactScope(
        string $scope,
        string $domain,
        ?string $languageCode,
    ): void {
        $identity = LanguageCode::fromNullable($languageCode)->identity();

        $this->run(
            'DELETE FROM maa_i18n_domain_language_summary
             WHERE scope = :del_scope
               AND domain = :del_domain
               AND language_code_identity = :del_identity',
            ([
                'del_scope'    => $scope,
                'del_domain'   => $domain,
                'del_identity' => $identity,
            ]),
        );

        $this->run(
            'INSERT INTO maa_i18n_domain_language_summary
                (scope, domain, language_code, total_keys, translated_count, missing_count)
             SELECT
                :ins_scope,
                :ins_domain,
                :ins_code,
                kt.total_keys,
                x.translated_count,
                kt.total_keys - x.translated_count
             FROM (
                SELECT COUNT(*) AS total_keys
                FROM maa_i18n_keys
                WHERE scope = :tk_scope
                  AND domain = :tk_domain
             ) kt
             JOIN (
                SELECT COUNT(*) AS translated_count
                FROM maa_i18n_keys k
                JOIN maa_i18n_translations t
                    ON t.key_id = k.id
                WHERE k.scope = :tr_scope
                  AND k.domain = :tr_domain
                  AND t.language_code_identity = :tr_identity
             ) x
             WHERE x.translated_count > 0',
            ([
                'ins_scope'   => $scope,
                'ins_domain'  => $domain,
                'ins_code'    => $languageCode,
                'tk_scope'    => $scope,
                'tk_domain'   => $domain,
                'tr_scope'    => $scope,
                'tr_domain'   => $domain,
                'tr_identity' => $identity,
            ]),
        );
    }

    /**
     * Rebuild every derived row for this exact language scope from authoritative
     * keys and translations; null selects the unlocalized scope, and no row is
     * left when that scope has no translations. Non-null codes are not normalized.
     */
    public function rebuildLanguageCode(?string $languageCode): void
    {
        $identity = LanguageCode::fromNullable($languageCode)->identity();

        $this->run(
            'DELETE FROM maa_i18n_domain_language_summary
             WHERE language_code_identity = :del_identity',
            ['del_identity' => $identity],
        );

        $this->run(
            'INSERT INTO maa_i18n_domain_language_summary
                (scope, domain, language_code, total_keys, translated_count, missing_count)
             SELECT
                x.scope,
                x.domain,
                x.language_code,
                kt.total_keys,
                x.translated_count,
                kt.total_keys - x.translated_count
             FROM (
                SELECT
                    k.scope,
                    k.domain,
                    t.language_code,
                    COUNT(*) AS translated_count
                FROM maa_i18n_keys k
                JOIN maa_i18n_translations t
                    ON t.key_id = k.id
                WHERE t.language_code_identity = :tr_identity
                GROUP BY k.scope, k.domain, t.language_code
             ) x
             JOIN (
                SELECT scope, domain, COUNT(*) AS total_keys
                FROM maa_i18n_keys
                GROUP BY scope, domain
             ) kt
                ON kt.scope = x.scope
               AND kt.domain = x.domain',
            ['tr_identity' => $identity],
        );
    }

    /* ==========================================================
     * DIRECT OPS
     * ========================================================== */

    /** Clear only the derived summary table. */
    public function truncate(): void
    {
        $this->gateway->write('DELETE FROM maa_i18n_domain_language_summary', [], 'summary.truncate');
    }

    /* ==========================================================
     * REBUILD (SQL-Driven, authoritative tables only)
     * ========================================================== */

    /**
     * Upsert summary aggregates for language scopes present in authoritative
     * keys and translations. The full rebuild flow clears this derived table first.
     */
    public function rebuildAll(): void
    {
        $sql = '
        INSERT INTO maa_i18n_domain_language_summary
            (scope, domain, language_code, total_keys, translated_count, missing_count)
        SELECT
            x.scope,
            x.domain,
            x.language_code,
            kt.total_keys,
            x.translated_count,
            kt.total_keys - x.translated_count
        FROM (
            SELECT
                k.scope,
                k.domain,
                t.language_code,
                COUNT(*) AS translated_count
            FROM maa_i18n_keys k
            JOIN maa_i18n_translations t
                ON t.key_id = k.id
            GROUP BY k.scope, k.domain, t.language_code
        ) x
        JOIN (
            SELECT scope, domain, COUNT(*) AS total_keys
            FROM maa_i18n_keys
            GROUP BY scope, domain
        ) kt
            ON kt.scope = x.scope
           AND kt.domain = x.domain
        ON DUPLICATE KEY UPDATE
            total_keys = VALUES(total_keys),
            translated_count = VALUES(translated_count),
            missing_count = VALUES(missing_count)
    ';

        $this->gateway->write($sql, [], 'summary.rebuildAll');
    }

    /**
     * Replace all derived language rows for one scope/domain from authoritative
     * keys and translations; scopes without translations have no summary row.
     */
    public function rebuildScopeDomain(string $scope, string $domain): void
    {
        $this->run(
            'DELETE FROM maa_i18n_domain_language_summary
             WHERE scope = :del_scope
               AND domain = :del_domain',
            ([
                'del_scope'  => $scope,
                'del_domain' => $domain,
            ]),
        );

        $this->run(
            'INSERT INTO maa_i18n_domain_language_summary
                (scope, domain, language_code, total_keys, translated_count, missing_count)
             SELECT
                :ins_scope,
                :ins_domain,
                x.language_code,
                kt.total_keys,
                x.translated_count,
                kt.total_keys - x.translated_count
             FROM (
                SELECT
                    t.language_code,
                    COUNT(*) AS translated_count
                FROM maa_i18n_keys k
                JOIN maa_i18n_translations t
                    ON t.key_id = k.id
                WHERE k.scope = :tr_scope
                  AND k.domain = :tr_domain
                GROUP BY t.language_code
             ) x
             JOIN (
                SELECT COUNT(*) AS total_keys
                FROM maa_i18n_keys
                WHERE scope = :tk_scope
                  AND domain = :tk_domain
             ) kt',
            ([
                'ins_scope'  => $scope,
                'ins_domain' => $domain,
                'tr_scope'   => $scope,
                'tr_domain'  => $domain,
                'tk_scope'   => $scope,
                'tk_domain'  => $domain,
            ]),
        );
    }

    /* ==========================================================
     * READ
     * ========================================================== */

    /**
     * Read the row for this exact nullable language scope; null selects the
     * unlocalized scope. A missing row means no translation exists for it.
     *
     * @return array{
     *     total_keys: int,
     *     translated_count: int,
     *     missing_count: int
     * }|null
     */
    public function getRow(
        string $scope,
        string $domain,
        ?string $languageCode,
    ): ?array {
        $row = $this->gateway->fetchOne(
            'SELECT total_keys, translated_count, missing_count
             FROM maa_i18n_domain_language_summary
             WHERE scope = :scope
               AND domain = :domain
               AND language_code_identity = :language_identity',
            ([
                'scope'             => $scope,
                'domain'            => $domain,
                'language_identity' => LanguageCode::fromNullable($languageCode)->identity(),
            ]),
            'summary.getRow',
        );

        if ($row === null) {
            return null;
        }

        return [
            'total_keys'       => Row::int($row, 'total_keys'),
            'translated_count' => Row::int($row, 'translated_count'),
            'missing_count'    => Row::int($row, 'missing_count'),
        ];
    }

    /**
     * @param array<string, string|null> $params
     */
    private function run(string $sql, array $params): void
    {
        $this->gateway->write($sql, $params, 'summary.write');
    }
}
