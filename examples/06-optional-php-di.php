<?php

/**
 * Example 06 - Optional PHP-DI integration.
 *
 * Capability: resolve the I18n services from a PHP-DI container instead of
 * constructing them by hand. This is OPTIONAL: the Core never needs PHP-DI.
 *
 * Prerequisite (explicit): `composer require php-di/php-di` in your application
 * (it also brings `psr/container`). Without it, use Example 01 instead.
 *
 *   Input -> Public Call -> Result -> Boundary
 *   PDO + ClockInterface in the container -> I18nBindings::register
 *   -> autowired services -> identical behavior to the manually wired Core
 *
 * Usage guide: docs/guides/USAGE_GUIDE.md (section "Optional PHP-DI integration")
 */

declare(strict_types=1);

require __DIR__ . '/_support/bootstrap.php';

use DI\ContainerBuilder;
use Maatify\I18n\Adapter\PhpDi\I18nBindings;
use Maatify\I18n\Consumer\Service\TranslationReadService;
use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Management\Command\UpsertTranslationCommand;
use Maatify\I18n\Management\Service\I18nDomainManagementService;
use Maatify\I18n\Management\Service\I18nScopeDomainManagementService;
use Maatify\I18n\Management\Service\I18nScopeManagementService;
use Maatify\I18n\Management\Service\TranslationWriteService;
use Maatify\SharedCommon\Contracts\ClockInterface;
use Maatify\SharedCommon\Infrastructure\SystemClock;
use Psr\Container\ContainerInterface;

echo 'Example 06 - Optional PHP-DI integration', PHP_EOL;

/**
 * Typed access to a container entry (PSR-11 `get()` is untyped).
 *
 * @template T of object
 * @param class-string<T> $class
 *
 * @return T
 */
function resolve(ContainerInterface $container, string $class): object
{
    $service = $container->get($class);
    if (!$service instanceof $class) {
        throw new RuntimeException(sprintf('The container did not return %s.', $class));
    }

    return $service;
}

if (!class_exists(ContainerBuilder::class)) {
    throw new RuntimeException('This example requires php-di/php-di. Install it, or follow Example 01.');
}

$builder = new ContainerBuilder();
I18nBindings::register($builder);          // repositories + the shared transaction runner
$builder->addDefinitions([
    PDO::class => example_pdo(),           // YOUR connection
    ClockInterface::class => new SystemClock(new DateTimeZone('UTC')),
]);
$container = $builder->build();

// Services are autowired from the bound repositories.
$scopes = resolve($container, I18nScopeManagementService::class);
$domains = resolve($container, I18nDomainManagementService::class);
$assignments = resolve($container, I18nScopeDomainManagementService::class);
$writer = resolve($container, TranslationWriteService::class);
$reader = resolve($container, TranslationReadService::class);

$scopes->create(new CreateScopeCommand('web', 'Website'));
$domains->create(new CreateDomainCommand('home', 'Home page'));
$assignments->assign('web', 'home');
$keyId = $writer->createKey(new CreateKeyCommand('web', 'home', 'title'));
$writer->upsertTranslation(new UpsertTranslationCommand('en', $keyId, 'Welcome'));

example_expect('the container-built services behave like the manual Core', 'Welcome', $reader->getValue('en', 'web', 'home', 'title'));
example_expect('exact missing is unchanged', null, $reader->getValue('ar', 'web', 'home', 'title'));
