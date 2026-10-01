<?php

declare(strict_types=1);

namespace Maatify\I18n\Adapter\PhpDi;

use DI\Container;
use DI\ContainerBuilder;
use Maatify\I18n\Repository\DomainLanguageSummaryRepositoryInterface;
use Maatify\I18n\Repository\DomainRepositoryInterface;
use Maatify\I18n\Repository\DomainScopeRepositoryInterface;
use Maatify\I18n\Repository\I18nOperationalStatsRepositoryInterface;
use Maatify\I18n\Repository\KeyStatsRepositoryInterface;
use Maatify\I18n\Repository\Mysql\MysqlDomainLanguageSummaryRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainRepository;
use Maatify\I18n\Repository\Mysql\MysqlDomainScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlI18nOperationalStatsRepository;
use Maatify\I18n\Repository\Mysql\MysqlKeyStatsRepository;
use Maatify\I18n\Repository\Mysql\MysqlScopeRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationKeyRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationQueryRepository;
use Maatify\I18n\Repository\Mysql\MysqlTranslationRepository;
use Maatify\I18n\Repository\ScopeRepositoryInterface;
use Maatify\I18n\Repository\TranslationKeyRepositoryInterface;
use Maatify\I18n\Repository\TranslationQueryRepositoryInterface;
use Maatify\I18n\Repository\TranslationRepositoryInterface;
use Maatify\Persistence\Pdo\Transaction\PdoTransactionRunner;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;
use Psr\Container\ContainerInterface;

/**
 * OPTIONAL PHP-DI integration of the I18n library.
 *
 * --------------------------------------------------------------------------
 * STATUS
 * --------------------------------------------------------------------------
 * First-class, opt-in. The I18n core (everything outside Adapter/) never
 * references PHP-DI or PSR-11 and is fully usable by constructing the
 * repositories and services directly. This adapter is the only place that
 * needs `php-di/php-di` and `psr/container`; both are `suggest` entries of
 * composer.json, not runtime requirements.
 *
 * --------------------------------------------------------------------------
 * PURPOSE
 * --------------------------------------------------------------------------
 * Maps I18n contracts (interfaces) to their MySQL implementations. Services
 * (policy, read/write/management services, rebuilder, ...) are final readonly
 * classes with interface-typed constructors, so PHP-DI autowires them from
 * these bindings.
 *
 * --------------------------------------------------------------------------
 * DESIGN PRINCIPLES
 * --------------------------------------------------------------------------
 * - No knowledge of any Host application.
 * - Relies only on external contracts: PDO and the shared Clock.
 * - Transactions use maatify/persistence (PdoTransactionRunner) on the same
 *   PDO connection as the repositories.
 *
 * --------------------------------------------------------------------------
 * HOST CUSTOMIZATION
 * --------------------------------------------------------------------------
 * A host application MAY override any binding after calling register():
 *
 *   I18nBindings::register($builder);
 *   $builder->addDefinitions([
 *       DomainLanguageSummaryRepositoryInterface::class => CustomRepository::class,
 *   ]);
 *
 * --------------------------------------------------------------------------
 * REQUIREMENTS
 * --------------------------------------------------------------------------
 * The host container must provide:
 * - PDO (the connection used for ALL I18n persistence and transactions)
 * - Maatify\SharedCommon\Contracts\ClockInterface
 *
 * This class contains NO business logic: dependency wiring only.
 */
final class I18nBindings
{
    /**
     * @param ContainerBuilder<Container> $builder
     */
    public static function register(ContainerBuilder $builder): void
    {
        $builder->addDefinitions([

            TransactionRunnerInterface::class => static function (ContainerInterface $c): TransactionRunnerInterface {
                $pdo = $c->get(PDO::class);
                assert($pdo instanceof PDO);

                return new PdoTransactionRunner($pdo);
            },

            DomainLanguageSummaryRepositoryInterface::class => static function (ContainerInterface $c): DomainLanguageSummaryRepositoryInterface {
                $pdo = $c->get(PDO::class);
                assert($pdo instanceof PDO);

                return new MysqlDomainLanguageSummaryRepository($pdo);
            },

            DomainRepositoryInterface::class => static function (ContainerInterface $c): DomainRepositoryInterface {
                $pdo = $c->get(PDO::class);
                assert($pdo instanceof PDO);

                return new MysqlDomainRepository($pdo);
            },

            DomainScopeRepositoryInterface::class => static function (ContainerInterface $c): DomainScopeRepositoryInterface {
                $pdo = $c->get(PDO::class);
                assert($pdo instanceof PDO);

                return new MysqlDomainScopeRepository($pdo);
            },

            KeyStatsRepositoryInterface::class => static function (ContainerInterface $c): KeyStatsRepositoryInterface {
                $pdo = $c->get(PDO::class);
                assert($pdo instanceof PDO);

                return new MysqlKeyStatsRepository($pdo);
            },

            ScopeRepositoryInterface::class => static function (ContainerInterface $c): ScopeRepositoryInterface {
                $pdo = $c->get(PDO::class);
                assert($pdo instanceof PDO);

                return new MysqlScopeRepository($pdo);
            },

            TranslationKeyRepositoryInterface::class => static function (ContainerInterface $c): TranslationKeyRepositoryInterface {
                $pdo = $c->get(PDO::class);
                assert($pdo instanceof PDO);

                return new MysqlTranslationKeyRepository($pdo);
            },

            TranslationRepositoryInterface::class => static function (ContainerInterface $c): TranslationRepositoryInterface {
                $pdo = $c->get(PDO::class);
                assert($pdo instanceof PDO);
                $clock = $c->get(ClockInterface::class);
                assert($clock instanceof ClockInterface);

                return new MysqlTranslationRepository($pdo, $clock);
            },

            TranslationQueryRepositoryInterface::class => static function (ContainerInterface $c): TranslationQueryRepositoryInterface {
                $pdo = $c->get(PDO::class);
                assert($pdo instanceof PDO);

                return new MysqlTranslationQueryRepository($pdo);
            },

            I18nOperationalStatsRepositoryInterface::class => static function (ContainerInterface $c): I18nOperationalStatsRepositoryInterface {
                $pdo = $c->get(PDO::class);
                assert($pdo instanceof PDO);

                return new MysqlI18nOperationalStatsRepository($pdo);
            },

        ]);
    }
}
