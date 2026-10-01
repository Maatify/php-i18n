<?php

/**
 * Support code for the smoke-executed examples. It is NOT part of the Package
 * and NOT part of its public API.
 *
 * It does two things only:
 * - loads the Package production autoload (`vendor/autoload.php`);
 * - provisions a disposable MySQL schema for an example run, using the same
 *   `I18N_IT_DB_*` environment contract as the Package-owned MySQL lifecycle
 *   (`scripts/ci/with-mysql.sh`), so the examples never need a second service
 *   definition. In your own application you pass your own `PDO` instead.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

/**
 * A PDO connected to a fresh, disposable database that already contains the
 * canonical Package schema (`schema/schema.i18n.sql`). The database is dropped
 * when the example process ends.
 */
function example_pdo(): PDO
{
    $env = static function (string $name): string {
        $value = getenv($name);
        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf(
                'Missing %s. Run examples through `composer check:examples` (it provides a disposable MySQL).',
                $name,
            ));
        }

        return $value;
    };

    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $env('I18N_IT_DB_HOST'), (int) $env('I18N_IT_DB_PORT')),
        $env('I18N_IT_DB_USER'),
        $env('I18N_IT_DB_PASS'),
        ([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]),
    );

    $schema = 'i18n_it_ex_' . bin2hex(random_bytes(6));
    $pdo->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    register_shutdown_function(static function () use ($pdo, $schema): void {
        $pdo->exec('DROP DATABASE IF EXISTS `' . $schema . '`');
    });
    $pdo->exec('USE `' . $schema . '`');

    $sql = file_get_contents(dirname(__DIR__, 2) . '/schema/schema.i18n.sql');
    if ($sql === false) {
        throw new RuntimeException('schema/schema.i18n.sql is missing.');
    }
    $pdo->exec($sql);

    return $pdo;
}

/**
 * Prints a result line and fails loudly when the documented behavior is not
 * what the Package returned, so a smoke run proves the example is still true.
 */
function example_expect(string $what, mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "Example expectation failed: %s\n  expected: %s\n  actual:   %s",
            $what,
            var_export($expected, true),
            var_export($actual, true),
        ));
    }

    echo '  ok: ', $what, PHP_EOL;
}

/**
 * @param class-string<Throwable> $exception
 */
function example_expect_throws(string $what, string $exception, callable $action): void
{
    try {
        $action();
    } catch (Throwable $e) {
        if ($e instanceof $exception) {
            echo '  ok: ', $what, ' -> ', $exception, PHP_EOL;

            return;
        }

        throw new RuntimeException(sprintf('%s: expected %s, got %s', $what, $exception, $e::class), 0, $e);
    }

    throw new RuntimeException(sprintf('%s: expected %s, nothing was thrown', $what, $exception));
}
