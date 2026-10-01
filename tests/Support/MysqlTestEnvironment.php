<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Support;

use PDO;
use PDOException;

/**
 * Package-owned MySQL test environment contract (no Host dependency).
 *
 * Environment variables:
 * - I18N_IT_DB_HOST, I18N_IT_DB_USER, I18N_IT_DB_PASS (required to connect)
 * - I18N_IT_DB_PORT (optional, default 3306)
 * - I18N_IT_REQUIRED=1 turns "MySQL unavailable / credentials missing"
 *   into a hard failure instead of a skip.
 *
 * Nothing is read from any Host `.env` and nothing is hardcoded.
 */
final class MysqlTestEnvironment
{
    public static function isRequired(): bool
    {
        return self::env('I18N_IT_REQUIRED') === '1';
    }

    /**
     * @return array{dsn: string, user: string, pass: string}|null
     */
    public static function credentials(): ?array
    {
        $host = self::env('I18N_IT_DB_HOST');
        $user = self::env('I18N_IT_DB_USER');
        $pass = getenv('I18N_IT_DB_PASS');

        if ($host === null || $user === null || $pass === false) {
            return null;
        }

        $portRaw = self::env('I18N_IT_DB_PORT');
        $port = $portRaw !== null && ctype_digit($portRaw) ? (int) $portRaw : 3306;

        return [
            'dsn' => sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
            'user' => $user,
            'pass' => $pass,
        ];
    }

    /**
     * @param array{dsn: string, user: string, pass: string} $credentials
     *
     * @throws PDOException
     */
    public static function connect(array $credentials): PDO
    {
        return new PDO($credentials['dsn'], $credentials['user'], $credentials['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function randomSchemaName(string $prefix): string
    {
        return 'i18n_it_' . $prefix . '_' . bin2hex(random_bytes(6));
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
