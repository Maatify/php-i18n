<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Deterministic proof of the exit-status contract of
 * scripts/ci/run-phpunit-with-mysql.sh. Uses repository-owned fake docker and
 * verification shims, so it needs neither Docker nor MySQL.
 */
final class IntegrationLifecycleStatusTest extends TestCase
{
    private string $markerDir = '';

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . '/i18n_lifecycle_' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($dir, 0700));
        $this->markerDir = $dir;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->markerDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->markerDir);
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function statusCases(): array
    {
        return [
            'verification 0 / cleanup 0' => [0, 0, 0],
            'verification 7 / cleanup 0' => [7, 0, 7],
            'verification 0 / cleanup 9' => [0, 9, 9],
            'verification 7 / cleanup 9' => [7, 9, 7],
        ];
    }

    #[DataProvider('statusCases')]
    public function testExitStatusPropagation(int $verification, int $cleanup, int $expected): void
    {
        [$process, $pipes] = $this->start([
            'I18N_IT_FAKE_VERIFY_STATUS' => (string) $verification,
            'I18N_IT_FAKE_DOWN_STATUS' => (string) $cleanup,
        ]);

        self::assertSame($expected, $this->finish($process, $pipes));
        self::assertFileExists($this->markerDir . '/down', 'teardown must always run');
    }

    public function testFailedStartupStillTearsDownAndPreservesStatus(): void
    {
        [$process, $pipes] = $this->start([
            'I18N_IT_FAKE_UP_STATUS' => '5',
            'I18N_IT_FAKE_DOWN_STATUS' => '9',
        ]);

        self::assertSame(5, $this->finish($process, $pipes));
        self::assertFileExists($this->markerDir . '/down');
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function signals(): array
    {
        return [
            'SIGINT' => [2, 130],
            'SIGTERM' => [15, 143],
        ];
    }

    #[DataProvider('signals')]
    public function testSignalsAreNonZeroAndStillTearDown(int $signal, int $expected): void
    {
        [$process, $pipes] = $this->start(['I18N_IT_FAKE_VERIFY_HANG' => '1']);

        $deadline = microtime(true) + 15.0;
        while (!is_file($this->markerDir . '/verify-started')) {
            if (microtime(true) > $deadline) {
                proc_terminate($process, 9);
                self::fail('Lifecycle never reached the verification step.');
            }
            usleep(20000);
        }

        proc_terminate($process, $signal);

        self::assertSame($expected, $this->finish($process, $pipes));
        self::assertFileExists($this->markerDir . '/down', 'teardown must run on signals');
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array{resource, array<int, resource>}
     */
    private function start(array $overrides): array
    {
        $root = dirname(__DIR__, 2);
        $env = array_merge(
            ([
                'PATH' => (string) getenv('PATH'),
                'I18N_IT_DOCKER_BIN' => $root . '/tests/Support/Lifecycle/fake-docker.sh',
                'I18N_IT_VERIFY_BIN' => $root . '/tests/Support/Lifecycle/fake-verify.sh',
                'I18N_IT_FAKE_MARKER_DIR' => $this->markerDir,
            ]),
            $overrides,
        );

        $process = proc_open(
            ['bash', $root . '/scripts/ci/run-phpunit-with-mysql.sh'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
            $env,
        );
        self::assertIsResource($process);

        /** @var array<int, resource> $pipes */
        return [$process, $pipes];
    }

    /**
     * @param resource            $process
     * @param array<int, resource> $pipes
     */
    private function finish($process, array $pipes): int
    {
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }
}
