<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Request;

use ampf\Request\CliRequest;
use ampf\Router\RouteResolver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 */
#[BackupGlobals(true)]
#[CoversClass(CliRequest::class)]
final class CliRequestTest extends TestCase
{
    public function testTheCommandLineIsPhpsWhenNoneIsGiven(): void
    {
        $_SERVER['argv'] = ['bin/index.php', 'cache/clear', 'all', 7, 'now'];

        $request = $this->request(null);

        self::assertSame('bin/index.php cache/clear', $request->getCmd('cache/clear'));
        self::assertSame(['all', 'now'], $request->getRouteParams(), 'what is no string is no argument');
    }

    public function testWithoutPhpsCommandLineThereIsNone(): void
    {
        $_SERVER['argv'] = 'not a list';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The command line names no script.');

        $this->request(null)->getCmd('help');
    }

    public function testTheFirstArgumentNamesTheRoute(): void
    {
        self::assertSame('CacheController', $this->request(['bin/index.php', 'cache/clear', 'all'])->getController());
    }

    public function testWithoutARouteTheStarRouteRuns(): void
    {
        self::assertSame('HelpController', $this->request(['bin/index.php'])->getController());
        self::assertSame('HelpController', $this->request(['bin/index.php', ' '])->getController());
    }

    public function testARouteWithoutAMatchIsRefused(): void
    {
        $request = $this->request(
            ['bin/index.php', 'unknown'],
            ['cache/clear' => ['pattern' => 'cache/clear', 'controller' => 'CacheController']],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No route matches the command line\'s route unknown.');

        $request->getController();
    }

    public function testTheArgumentsAfterTheRouteAreItsParameters(): void
    {
        self::assertSame([], $this->request(['bin/index.php'])->getRouteParams());
        self::assertSame([], $this->request(['bin/index.php', 'cache/clear'])->getRouteParams());
        self::assertSame(
            ['all', '--force'],
            $this->request(['bin/index.php', 'cache/clear', 'all', '--force'])->getRouteParams(),
        );
    }

    public function testTheCommandOfARouteHasItsParametersPutIn(): void
    {
        $request = $this->request(['bin/console', 'help']);

        self::assertSame('bin/console user/delete/42', $request->getActionCmd('user/delete', ['id' => '42']));
        self::assertSame('bin/console cache/clear', $request->getActionCmd('cache/clear'));
    }

    public function testTheCommandOfAnUnknownRouteIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There is no route missing.');

        $this->request(['bin/console'])->getActionCmd('missing');
    }

    public function testTheResponseIsPrintedOnce(): void
    {
        $request = $this->request(['bin/index.php']);

        self::assertSame($request, $request->setResponse('done' . PHP_EOL));

        $this->expectOutputString('done' . PHP_EOL);
        self::assertSame($request, $request->flush());
        $request->flush();
    }

    public function testTheExitCodeIsZeroUnlessAControllerSetsOne(): void
    {
        $request = $this->request(['bin/index.php']);

        self::assertSame(0, $request->getExitCode());
        self::assertSame($request, $request->setExitCode(3));
        self::assertSame(3, $request->getExitCode());
        self::assertSame(254, $request->setExitCode(254)->getExitCode());
        self::assertSame(0, $request->setExitCode(0)->getExitCode());
    }

    public function testAnExitCodeOutsideTheRangeIsRefused(): void
    {
        foreach ([-1, 255] as $exitCode) {
            try {
                $this->request(['bin/index.php'])->setExitCode($exitCode);
                self::fail('accepted ' . $exitCode);
            } catch (InvalidArgumentException $e) {
                self::assertSame('An exit code is a number from 0 to 254, not ' . $exitCode . '.', $e->getMessage());
            }
        }
    }

    /**
     * @param ?list<string> $argv
     * @param ?array<string, array{pattern: string, controller: string}> $routes
     */
    private function request(?array $argv, ?array $routes = null): CliRequest
    {
        $resolver = new RouteResolver();
        $resolver->setConfig(['routes' => $routes ?? [
            'cache/clear' => ['pattern' => 'cache/clear', 'controller' => 'CacheController'],
            'user/delete' => ['pattern' => 'user/delete/(?P<id>[0-9]+)', 'controller' => 'UserController'],
            'help' => ['pattern' => '(?P<pathInfo>.*)', 'controller' => 'HelpController'],
        ]]);

        $request = new CliRequest($argv);
        $request->setRouteResolver($resolver);

        return $request;
    }
}
