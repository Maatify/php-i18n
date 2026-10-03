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

use RuntimeException;

/**
 * Parent-side handle of one real worker process (see worker.php).
 */
final class WorkerProcess
{
    /** @var resource */
    private $process;

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /** @var array<string, mixed>|null */
    private ?array $result = null;

    /**
     * @param array<string, string|int> $args
     */
    private function __construct(string $schema, string $operation, array $args, string $mode, ?string $barrier)
    {
        $spec = json_encode([
            'schema' => $schema,
            'operation' => $operation,
            'args' => $args,
            'mode' => $mode,
            'barrier' => $barrier,
        ], JSON_THROW_ON_ERROR);

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/worker.php', $spec],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start the concurrency worker.');
        }

        $this->process = $process;
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];

        // First event: the worker has connected and built its runtime.
        $ready = $this->readEvent();
        if (($ready['event'] ?? null) !== 'ready') {
            throw new RuntimeException('Worker did not become ready: ' . $this->stderrText());
        }
    }

    /**
     * @param array<string, string|int> $args
     */
    public static function start(
        string $schema,
        string $operation,
        array $args,
        string $mode = 'strict',
        ?string $barrier = null,
    ): self {
        return new self($schema, $operation, $args, $mode, $barrier);
    }

    /**
     * Blocks until the worker reported its outcome.
     *
     * @return array{ok: bool, class?: string, message?: string, value?: mixed}
     */
    public function outcome(): array
    {
        if ($this->result === null) {
            $event = $this->readEvent();
            if (($event['event'] ?? null) !== 'done') {
                throw new RuntimeException('Worker produced no result: ' . $this->stderrText());
            }
            $this->result = $event;
        }

        /** @var array{ok: bool, class?: string, message?: string, value?: mixed} $result */
        $result = $this->result;

        return $result;
    }

    public function close(): void
    {
        foreach ([$this->stdout, $this->stderr] as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (is_resource($this->process)) {
            proc_close($this->process);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readEvent(): array
    {
        $line = fgets($this->stdout);
        if ($line === false) {
            throw new RuntimeException('Worker closed its output: ' . $this->stderrText());
        }

        /** @var array<string, mixed> $event */
        $event = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

        return $event;
    }

    private function stderrText(): string
    {
        stream_set_blocking($this->stderr, false);

        return (string) stream_get_contents($this->stderr);
    }
}
