<?php

/**
 * Convenience wiring for examples 02-06 so they can focus on their own
 * capability. Example 01 shows the same wiring written out explicitly: that is
 * the supported way to build the Core in your application.
 */

declare(strict_types=1);

use Maatify\I18n\Consumer\Service\TranslationDomainReadService;
use Maatify\I18n\Consumer\Service\TranslationReadService;
use Maatify\I18n\Enum\I18nPolicyModeEnum;
use Maatify\I18n\Management\Service\I18nDomainManagementService;
use Maatify\I18n\Management\Service\I18nManagementReadService;
use Maatify\I18n\Management\Service\I18nOperationalReadService;
use Maatify\I18n\Management\Service\I18nScopeDomainManagementService;
use Maatify\I18n\Management\Service\I18nScopeManagementService;
use Maatify\I18n\Management\Service\I18nStatsRebuilder;
use Maatify\I18n\Management\Service\TranslationWriteService;
use Maatify\I18n\Repository\Mysql\MysqlDomainLanguageSummaryRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlI18nOperationalStatsRepository;
use Maatify\I18n\Repository\Mysql\MysqlKeyStatsRepository;
use Maatify\I18n\Repository\Mysql\MysqlScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationKeyRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationQueryRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationRepository;
use Maatify\I18n\Service\I18nGovernancePolicyService;
use Maatify\I18n\Service\MissingCounterService;
use Maatify\Persistence\Pdo\Transaction\PdoTransactionRunner;
use Maatify\SharedCommon\Infrastructure\SystemClock;

final readonly class ExampleCore
{
    public function __construct(
        public I18nScopeManagementService $scopes,
        public I18nDomainManagementService $domains,
        public I18nScopeDomainManagementService $assignments,
        public TranslationWriteService $writer,
        public TranslationReadService $reader,
        public TranslationDomainReadService $domainReader,
        public I18nManagementReadService $managementRead,
        public I18nOperationalReadService $operationalRead,
        public I18nStatsRebuilder $rebuilder,
    ) {}

    public static function from(PDO $pdo): self
    {
        $clock = new SystemClock(new DateTimeZone('UTC'));
        $scopes = new MysqlScopeRepository($pdo);
        $domains = new MysqlDomainRepository($pdo);
        $domainScopes = new MysqlDomainScopeRepository($pdo);
        $keys = new MysqlTranslationKeyRepository($pdo);
        $translations = new MysqlTranslationRepository($pdo, $clock);
        $summary = new MysqlDomainLanguageSummaryRepository($pdo);
        $keyStats = new MysqlKeyStatsRepository($pdo);
        $tx = new PdoTransactionRunner($pdo);

        $policy = new I18nGovernancePolicyService($scopes, $domains, $domainScopes, I18nPolicyModeEnum::STRICT);
        $counter = new MissingCounterService($summary, $keys, $keyStats);

        return new self(
            new I18nScopeManagementService($tx, $scopes, $domainScopes, $keys),
            new I18nDomainManagementService($tx, $domains, $domainScopes, $keys),
            new I18nScopeDomainManagementService($tx, $scopes, $domains, $domainScopes),
            new TranslationWriteService($tx, $keys, $translations, $policy, $counter),
            new TranslationReadService($keys, $translations),
            new TranslationDomainReadService($keys, $translations, $policy),
            new I18nManagementReadService(
                $scopes,
                $domains,
                $domainScopes,
                $keys,
                new MysqlTranslationQueryRepository($pdo),
            ),
            new I18nOperationalReadService(new MysqlI18nOperationalStatsRepository($pdo)),
            new I18nStatsRebuilder($tx, $summary, $keyStats),
        );
    }
}
