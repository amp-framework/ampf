<?php

declare(strict_types=1);

namespace ampf\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The fixture application's command line entry point, run as a command: its output, its errors and its exit code.
 */
#[CoversNothing]
final class CliApplicationTest extends TestCase
{
    public function testACommandPrintsItsResponseAndSucceeds(): void
    {
        self::assertSame([0, 'Hello Ada!' . PHP_EOL, ''], $this->command('greet', 'Ada'));
        self::assertSame([0, 'Hello nobody!' . PHP_EOL, ''], $this->command('greet'));
    }

    public function testACommandThatFailsEndsWithItsExitCode(): void
    {
        self::assertSame([3, 'Something failed.' . PHP_EOL, ''], $this->command('fail'));
    }

    public function testTheTraitsOfTheApplicationAreItsGeneratorsOutput(): void
    {
        self::assertSame([0, '1 traits, 0 would change' . PHP_EOL, ''], $this->command('beanAccess/generate', 'check'));
    }

    public function testAnUnknownCommandIsAnErrorInTheLog(): void
    {
        [$exitCode, $output, $errors] = $this->command('unknown');

        self::assertSame(255, $exitCode);
        self::assertSame('', $output, 'nothing displayed');
        self::assertStringContainsString('No route matches the command line\'s route unknown.', $errors);
    }

    /**
     * @return array{int, string, string} the exit code, the output and the error output
     */
    private function command(string ...$arguments): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/App/bin/index.php', ...array_values($arguments)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);

        $output = (string)stream_get_contents($pipes[1]);
        $errors = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output, $errors];
    }
}
