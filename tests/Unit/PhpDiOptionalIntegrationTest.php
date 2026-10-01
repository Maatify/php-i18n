<?php

/**
 * @copyright   ©2026 Maatify.dev
 * @Library     maatify/i18n
 * @Project     maatify:i18n
 * @author      Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @since       2026-10-01 00:00
 * @see         https://www.maatify.dev Maatify.dev
 * @link        https://github.com/Maatify/i18n view Project on GitHub
 * @note        Distributed in the hope that it will be useful - WITHOUT WARRANTY.
 */

declare(strict_types=1);

namespace Maatify\I18n\Tests\Unit;

use DateTimeZone;
use DI\ContainerBuilder;
use Maatify\I18n\Adapter\PhpDi\I18nBindings;
use Maatify\I18n\Consumer\Service\TranslationDomainReadService;
use Maatify\I18n\Consumer\Service\TranslationReadService;
use Maatify\I18n\Management\Service\I18nDomainManagementService;
use Maatify\I18n\Management\Service\I18nManagementReadService;
use Maatify\I18n\Management\Service\I18nOperationalReadService;
use Maatify\I18n\Management\Service\I18nScopeDomainManagementService;
use Maatify\I18n\Management\Service\I18nScopeManagementService;
use Maatify\I18n\Management\Service\I18nStatsRebuilder;
use Maatify\I18n\Management\Service\TranslationWriteService;
use Maatify\I18n\Service\I18nGovernancePolicyService;
use Maatify\Persistence\Pdo\Transaction\PdoTransactionRunner;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use Maatify\SharedCommon\Infrastructure\SystemClock;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * GA-F01: PHP-DI is a first-class OPTIONAL integration.
 *
 * Mode 1 - the Core is usable without PHP-DI (and without PSR-11).
 * Mode 2 - the PHP-DI adapter works when the suggested packages are installed.
 */
final class PhpDiOptionalIntegrationTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private static function composer(): array
    {
        $json = file_get_contents(self::root() . '/composer.json');
        self::assertIsString($json);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    // ── Mode 1: Core without PHP-DI ────────────────────────────────────────

    public function testPhpDiAndPsrContainerAreNotRuntimeRequirementsButAreSuggestedAndVerifiedInDev(): void
    {
        $composer = self::composer();
        /** @var array<string, string> $require */
        $require = $composer['require'];
        /** @var array<string, string> $requireDev */
        $requireDev = $composer['require-dev'];
        /** @var array<string, string> $suggest */
        $suggest = $composer['suggest'];

        foreach (['php-di/php-di', 'psr/container'] as $package) {
            self::assertArrayNotHasKey($package, $require, $package . ' must not be a mandatory runtime requirement');
            self::assertArrayHasKey($package, $requireDev, $package . ' is needed to verify the adapter');
            self::assertArrayHasKey($package, $suggest, $package . ' must be advertised as optional');
            self::assertNotSame('', trim($suggest[$package]));
        }

        self::assertArrayHasKey('maatify/persistence', $require);
        self::assertSame('^1.4', $require['maatify/persistence']);
    }

    public function testCoreSourceNeverReferencesPhpDiOrPsrContainer(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::root() . '/src', \FilesystemIterator::SKIP_DOTS),
        );

        $checked = 0;
        $offenders = [];
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/src/Adapter/')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            $checked++;

            if (preg_match('/\bDI\\\\|Psr\\\\Container|php-di/i', $source) === 1) {
                $offenders[] = $file->getPathname();
            }
        }

        self::assertGreaterThan(30, $checked);
        self::assertSame([], $offenders, 'the Core must not reference PHP-DI or PSR-11');
    }

    public function testTheCoreLoadsAndConstructsInAProcessWhereTheOptionalPackagesCannotBeAutoloaded(): void
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, self::root() . '/tests/Support/CoreWithoutPhpDi.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        self::assertSame(0, $status, 'core must work without PHP-DI: ' . $stderr . $stdout);
        self::assertStringStartsWith('CORE-OK', $stdout);
    }

    // ── Mode 2: PHP-DI adapter with the optional dependencies installed ────

    private static function stubPdo(): PDO
    {
        return (new \ReflectionClass(PDO::class))->newInstanceWithoutConstructor();
    }

    public function testTheAdapterResolvesEveryPublicServiceWhenPhpDiIsAvailable(): void
    {
        $builder = new ContainerBuilder();
        I18nBindings::register($builder);
        $builder->addDefinitions([
            PDO::class => self::stubPdo(),
            ClockInterface::class => new SystemClock(new DateTimeZone('UTC')),
        ]);
        $container = $builder->build();

        foreach ([
            TranslationReadService::class,
            TranslationDomainReadService::class,
            TranslationWriteService::class,
            I18nGovernancePolicyService::class,
            I18nScopeManagementService::class,
            I18nDomainManagementService::class,
            I18nScopeDomainManagementService::class,
            I18nManagementReadService::class,
            I18nOperationalReadService::class,
            I18nStatsRebuilder::class,
        ] as $service) {
            self::assertInstanceOf($service, $container->get($service), $service);
        }

        self::assertInstanceOf(PdoTransactionRunner::class, $container->get(TransactionRunnerInterface::class));
    }

    public function testAHostMayOverrideAnAdapterBinding(): void
    {
        $custom = new PdoTransactionRunner(self::stubPdo());

        $builder = new ContainerBuilder();
        I18nBindings::register($builder);
        $builder->addDefinitions([
            PDO::class => self::stubPdo(),
            ClockInterface::class => new SystemClock(new DateTimeZone('UTC')),
            TransactionRunnerInterface::class => $custom,
        ]);

        self::assertSame($custom, $builder->build()->get(TransactionRunnerInterface::class));
    }
}
