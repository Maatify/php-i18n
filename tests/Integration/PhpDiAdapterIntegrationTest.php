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

namespace Maatify\I18n\Tests\Integration;

use DateTimeZone;
use DI\ContainerBuilder;
use Maatify\I18n\Adapter\PhpDi\I18nBindings;
use Maatify\I18n\Consumer\Service\TranslationReadService;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\UpsertTranslationCommand;
use Maatify\I18n\Management\Service\TranslationWriteService;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;
use Maatify\SharedCommon\Contracts\ClockInterface;
use Maatify\SharedCommon\Infrastructure\SystemClock;
use PDO;

/**
 * GA-F01 mode 2 on the real engine: services resolved from the optional PHP-DI
 * adapter behave exactly like the plainly constructed core.
 */
final class PhpDiAdapterIntegrationTest extends MysqlIntegrationTestCase
{
    public function testServicesFromTheAdapterWriteAndReadThroughRealMysql(): void
    {
        $builder = new ContainerBuilder();
        I18nBindings::register($builder);
        $builder->addDefinitions([
            PDO::class => $this->pdo(),
            ClockInterface::class => new SystemClock(new DateTimeZone('UTC')),
        ]);
        $container = $builder->build();

        $writer = $container->get(TranslationWriteService::class);
        $reader = $container->get(TranslationReadService::class);
        self::assertInstanceOf(TranslationWriteService::class, $writer);
        self::assertInstanceOf(TranslationReadService::class, $reader);

        $keyId = $writer->createKey(new CreateKeyCommand('ct', 'home', 'title'));
        $writer->upsertTranslation(new UpsertTranslationCommand('ar', $keyId, 'عنوان'));

        self::assertSame('عنوان', $reader->getValue('ar', 'ct', 'home', 'title'));
        self::assertNull($reader->getValue('en', 'ct', 'home', 'title'));
        self::assertSame(1, $this->scalarInt('SELECT COUNT(*) FROM maa_i18n_domain_language_summary'));
    }
}
