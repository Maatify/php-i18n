<?php

declare(strict_types=1);

/**
 * Concurrency worker: ONE real PHP process with ONE real MySQL connection.
 *
 * Usage: php worker.php '<json spec>'
 *   spec = {"schema": "...", "operation": "...", "args": {...}, "mode": "strict|permissive", "barrier": "name|null"}
 *
 * Protocol (stdout, one JSON line each):
 *   {"event":"ready"}                       before the optional barrier
 *   {"event":"done","ok":true,"value":...}  or
 *   {"event":"done","ok":false,"class":"FQCN","message":"..."}
 *
 * The connection settings come from the same I18N_IT_DB_* environment as the
 * suite; nothing is read from any Host configuration.
 */

use Maatify\I18n\Enum\I18nPolicyModeEnum;
use Maatify\I18n\Management\Command\CreateDomainCommand;
use Maatify\I18n\Management\Command\CreateKeyCommand;
use Maatify\I18n\Management\Command\CreateScopeCommand;
use Maatify\I18n\Tests\Support\Concurrency\Runtime;
use Maatify\I18n\Tests\Support\MysqlTestEnvironment;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

/** @var array{schema: string, operation: string, args: array<string, string|int>, mode?: string, barrier?: string|null} $spec */
$spec = json_decode((string) ($argv[1] ?? ''), true, 512, JSON_THROW_ON_ERROR);

$credentials = MysqlTestEnvironment::credentials();
if ($credentials === null) {
    fwrite(STDERR, "worker: no I18N_IT_DB_* credentials\n");
    exit(2);
}

$pdo = MysqlTestEnvironment::connect($credentials);
$pdo->exec('USE `' . $spec['schema'] . '`');

$runtime = Runtime::build(
    $pdo,
    ($spec['mode'] ?? 'strict') === 'permissive' ? I18nPolicyModeEnum::PERMISSIVE : I18nPolicyModeEnum::STRICT,
);

$emit = static function (array $payload): void {
    fwrite(STDOUT, json_encode($payload, JSON_THROW_ON_ERROR) . "\n");
    fflush(STDOUT);
};

$emit(['event' => 'ready']);

$barrier = $spec['barrier'] ?? null;
if (is_string($barrier)) {
    // Blocks until the parent releases the named lock: every worker is parked
    // here first, then they all start at the same instant.
    $pdo->prepare('SELECT GET_LOCK(:name, 60)')->execute(['name' => $barrier]);
    $pdo->prepare('SELECT RELEASE_LOCK(:name)')->execute(['name' => $barrier]);
}

$args = $spec['args'];

try {
    $value = match ($spec['operation']) {
        'createKey' => $runtime->writer->createKey(new CreateKeyCommand(
            (string) $args['scope'],
            (string) $args['domain'],
            (string) $args['key'],
        )),
        'createScope' => $runtime->scopes->create(new CreateScopeCommand((string) $args['code'], (string) $args['name'])),
        'createDomain' => $runtime->domains->create(new CreateDomainCommand((string) $args['code'], (string) $args['name'])),
        'changeScopeCode' => $runtime->scopes->changeCode((int) $args['id'], (string) $args['newCode']),
        'changeDomainCode' => $runtime->domains->changeCode((int) $args['id'], (string) $args['newCode']),
        'assign' => $runtime->scopeDomains->assign((string) $args['scope'], (string) $args['domain']),
        default => throw new InvalidArgumentException('unknown operation ' . $spec['operation']),
    };

    $emit(['event' => 'done', 'ok' => true, 'value' => $value]);
} catch (Throwable $e) {
    $emit(['event' => 'done', 'ok' => false, 'class' => $e::class, 'message' => $e->getMessage()]);
}
