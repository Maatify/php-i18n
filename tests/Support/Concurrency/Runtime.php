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

namespace Maatify\I18n\Tests\Support\Concurrency;

use DateTimeZone;
use Maatify\I18n\Enum\I18nPolicyModeEnum;
use Maatify\I18n\Management\Service\I18nDomainManagementService;
use Maatify\I18n\Management\Service\I18nScopeDomainManagementService;
use Maatify\I18n\Management\Service\I18nScopeManagementService;
use Maatify\I18n\Management\Service\TranslationWriteService;
use Maatify\I18n\Repository\Mysql\MysqlDomainLanguageSummaryRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlKeyStatsRepository;
use Maatify\I18n\Repository\Mysql\MysqlScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationKeyRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationRepository;
use Maatify\I18n\Service\I18nGovernancePolicyService;
use Maatify\I18n\Service\MissingCounterService;
use Maatify\Persistence\Pdo\Transaction\PdoTransactionRunner;
use Maatify\SharedCommon\Infrastructure\SystemClock;
use PDO;

/**
 * The core object graph for ONE connection, built by plain construction (no
 * container). Shared by the Integration base case and the concurrency worker
 * processes so both exercise the same wiring.
 */
final readonly class Runtime
{
    public function __construct(
        public TranslationWriteService $writer,
        public I18nScopeManagementService $scopes,
        public I18nDomainManagementService $domains,
        public I18nScopeDomainManagementService $scopeDomains,
    ) {}

    public static function build(PDO $pdo, I18nPolicyModeEnum $mode = I18nPolicyModeEnum::STRICT): self
    {
        $tx = new PdoTransactionRunner($pdo);
        $scopes = new MysqlScopeRepository($pdo);
        $domains = new MysqlDomainRepository($pdo);
        $domainScopes = new MysqlDomainScopeRepository($pdo);
        $keys = new MysqlTranslationKeyRepository($pdo);
        $policy = new I18nGovernancePolicyService($scopes, $domains, $domainScopes, $mode);

        return new self(
            new TranslationWriteService(
                $tx,
                $keys,
                new MysqlTranslationRepository($pdo, new SystemClock(new DateTimeZone('UTC'))),
                $policy,
                new MissingCounterService(
                    new MysqlDomainLanguageSummaryRepository($pdo),
                    $keys,
                    new MysqlKeyStatsRepository($pdo),
                ),
            ),
            new I18nScopeManagementService($tx, $scopes, $domainScopes, $keys),
            new I18nDomainManagementService($tx, $domains, $domainScopes, $keys),
            new I18nScopeDomainManagementService($tx, $scopes, $domains, $domainScopes),
        );
    }
}
