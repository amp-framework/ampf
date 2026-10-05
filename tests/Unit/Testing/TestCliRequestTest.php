<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Testing;

use ampf\Router\RouteResolver;
use ampf\Testing\TestCliRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 */
#[CoversClass(TestCliRequest::class)]
final class TestCliRequestTest extends TestCase
{
    public function testItIsTheCommandLineOfTheTest(): void
    {
        // The process's own command line (PHPUnit's) is not the request's
        $argv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['bin/index.php', 'other', 'arguments'];

        try {
            $request = $this->routed(new TestCliRequest(['user/create', 'ada', '--admin']));
        } finally {
            $_SERVER['argv'] = $argv;
        }

        self::assertSame('UserCreateController', $request->getController());
        self::assertSame(['ada', '--admin'], $request->getRouteParams());
        self::assertSame('bin/index.php help', $request->getCmd('help'), 'the script of an entry point');
    }

    public function testWithoutArgumentsItIsTheRouteOfNone(): void
    {
        $request = $this->routed(new TestCliRequest());

        self::assertSame('HelpController', $request->getController());
        self::assertSame([], $request->getRouteParams());
    }

    public function testItPrintsNothingAndKeepsItsResponse(): void
    {
        $request = new TestCliRequest(['help']);

        self::assertSame('', $request->getResponseBody());

        $request->setResponse('Usage: …' . PHP_EOL)->setExitCode(2);

        $this->expectOutputString('');
        self::assertSame($request, $request->flush());
        self::assertSame('Usage: …' . PHP_EOL, $request->getResponseBody(), 'what flush() would print stays');
        self::assertSame(2, $request->getExitCode());
    }

    private function routed(TestCliRequest $request): TestCliRequest
    {
        $resolver = new RouteResolver();
        $resolver->setConfig(['routes' => [
            'user/create' => ['pattern' => 'user/create', 'controller' => 'UserCreateController'],
            'help' => ['pattern' => '.*', 'controller' => 'HelpController'],
        ]]);
        $request->setRouteResolver($resolver);

        return $request;
    }
}
