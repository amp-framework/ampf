<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Router;

use ampf\Router\RouteResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

#[CoversClass(RouteResolver::class)]
class RouteResolverTest extends TestCase
{
    public function testCleanMatches(): void
    {
        $method = new ReflectionMethod(RouteResolver::class, 'cleanMatches');

        $routeResolver = new RouteResolver();

        $matchPairs = [
            [[], [], []],
            [[0 => 'abc'], [], []],
            [[], [0], []],
            [[0 => 'abc'], [0], [0 => 'abc']],
            [[0 => 'abc'], ['abc'], []],
            [[0 => 'abc'], ['0'], []],
            [['abc' => 'def'], ['abc'], ['abc' => 'def']],
            [['abc' => 'def', 'foo' => 'bar'], [0, 'foo'], ['foo' => 'bar']],
            [['abc' => 'def', 'foo' => 'bar'], ['abc', 'foo'], ['abc' => 'def', 'foo' => 'bar']],
            [['abc' => 'def', 'foo' => 'bar'], [100 => 'abc', 200 => 'foo'], ['abc' => 'def', 'foo' => 'bar']],
        ];

        foreach ($matchPairs as $matchPair) {
            [$matches, $allowedParams, $cleanedMatches] = $matchPair;

            static::assertSame($cleanedMatches, $method->invoke($routeResolver, $matches, $allowedParams));
        }
    }

    public function testGetControllerParamsByRoutePattern(): void
    {
        $method = new ReflectionMethod(RouteResolver::class, 'getControllerParamsByRoutePattern');

        $routeResolver = new RouteResolver();

        $defaultRouteConfig = ['controller' => 'DefaultController', 'pattern' => 'index'];
        $paramRoute = ['controller' => 'ParamController', 'pattern' => 'user/info/(?P<userId>.*)'];
        $twoParamsRoute = ['controller' => 'TwoParamsController', 'pattern' => 'user/info/(?P<userId>.*)/(?P<action>.*)'];
        $pathInfoRoute = ['controller' => 'HelpController', 'pattern' => '(?P<pathInfo>.*)'];

        $inputOutputPairs = [
            [
                ['routes' => ['defaultRoute' => $defaultRouteConfig]],
                'nonMatchingRoute',
                null,
            ],
            [
                ['routes' => ['defaultRoute' => $defaultRouteConfig]],
                'index',
                ['defaultRoute', 'DefaultController', []],
            ],
            [
                ['routes' => ['defaultRoute' => $defaultRouteConfig, 'paramRoute' => $paramRoute]],
                'user/info/123',
                ['paramRoute', 'ParamController', ['userId' => '123']],
            ],
            [
                ['routes' => ['defaultRoute' => $defaultRouteConfig, 'twoParamsRoute' => $twoParamsRoute]],
                'user/info/123',
                null,
            ],
            [
                ['routes' => ['defaultRoute' => $defaultRouteConfig, 'twoParamsRoute' => $twoParamsRoute]],
                'user/info/123/',
                ['twoParamsRoute', 'TwoParamsController', ['userId' => '123', 'action' => '']],
            ],
            [
                ['routes' => ['defaultRoute' => $defaultRouteConfig, 'twoParamsRoute' => $twoParamsRoute]],
                'user/info/123/delete',
                ['twoParamsRoute', 'TwoParamsController', ['userId' => '123', 'action' => 'delete']],
            ],
            [
                ['routes' => ['defaultRoute' => $defaultRouteConfig, 'helpRoute' => $pathInfoRoute]],
                '',
                ['helpRoute', 'HelpController', ['pathInfo' => '']],
            ],
            [
                ['routes' => ['defaultRoute' => $defaultRouteConfig, 'helpRoute' => $pathInfoRoute]],
                'generic/test/catch/all',
                ['helpRoute', 'HelpController', ['pathInfo' => 'generic/test/catch/all']],
            ],
        ];

        foreach ($inputOutputPairs as $inputOutputPair) {
            [$config, $routePattern, $expectedReturn] = $inputOutputPair;

            $routeResolver->setConfig($config);

            static::assertSame($expectedReturn, $method->invoke($routeResolver, $routePattern));
        }
    }

    public function testGetRouteParams(): void
    {
        $method = new ReflectionMethod(RouteResolver::class, 'getRouteParams');

        $routeResolver = new RouteResolver();

        $regexPairs = [
            '' => [],
            'index' => [],
            'statistics/index' => [],
            '(?P<pathInfo>.*)' => ['pathInfo'],
            'user/info/(?P<userId>.*)' => ['userId'],
            '(?P<param1>.*)/(?P<param2>.*)' => ['param1', 'param2'],
            '1$$invalid/\%regex^[' => [],
            '1$$invalid/(?P<regexParam>.*)\%regex^[' => ['regexParam'],
            '/^index$/' => [],
            '/^index(?P<testParm>.*)$/' => ['testParm'],
        ];

        foreach ($regexPairs as $routePattern => $routeParams) {
            static::assertSame($routeParams, $method->invoke($routeResolver, $routePattern));
        }
    }

    /**
     * A route parameter is the text of one path segment in a link: whatever it holds, it cannot end the segment,
     * start a query or a fragment, or break the line of a Location header.
     */
    public function testRouteParametersAreEncodedIntoTheLink(): void
    {
        $routeResolver = new RouteResolver();
        $routeResolver->setConfig(['routes' => [
            'show' => ['controller' => 'ShowController', 'pattern' => 'show/(?P<pathInfo>.*)'],
            'twoParams' => ['controller' => 'TwoController', 'pattern' => 'user/(?P<userId>.*)/(?P<action>.*)'],
            'step' => ['controller' => 'StepController', 'pattern' => 'password-reset/(?P<step>\d+)'],
        ]]);

        $links = [
            // What applications pass (ids, ids with a suffix, words) comes out as it went in
            ['show', ['pathInfo' => '4711'], 'show/4711'],
            ['show', ['pathInfo' => '4711-a1b2c3d4'], 'show/4711-a1b2c3d4'],
            ['step', ['step' => '2'], 'password-reset/2'],
            ['twoParams', ['userId' => '42', 'action' => 'delete'], 'user/42/delete'],
            ['show', ['pathInfo' => 'A_b.c~d-e'], 'show/A_b.c~d-e'],
            // Everything else is percent-encoded
            ['show', ['pathInfo' => "1\r\nLocation: https://evil.example/"], 'show/1%0D%0ALocation%3A%20https%3A%2F%2Fevil.example%2F'],
            ['show', ['pathInfo' => '1/../../admin'], 'show/1%2F..%2F..%2Fadmin'],
            ['show', ['pathInfo' => '1?stkn=x#top'], 'show/1%3Fstkn%3Dx%23top'],
            ['show', ['pathInfo' => '100%'], 'show/100%25'],
            ['show', ['pathInfo' => '\evil.example'], 'show/%5Cevil.example'],
            ['show', ['pathInfo' => 'Grüße'], 'show/Gr%C3%BC%C3%9Fe'],
            ['twoParams', ['userId' => 'a b', 'action' => "\0"], 'user/a%20b/%00'],
        ];

        foreach ($links as [$routeId, $params, $expected]) {
            static::assertSame($expected, $routeResolver->getRoutePatternByRouteID($routeId, $params), $expected);
        }
    }

    /** The parameters a route pattern does not name are handed back as they are (they become the query string). */
    public function testParametersTheRouteDoesNotNameAreHandedBack(): void
    {
        $routeResolver = new RouteResolver();
        $routeResolver->setConfig(['routes' => [
            'show' => ['controller' => 'ShowController', 'pattern' => 'show/(?P<pathInfo>.*)'],
        ]]);

        static::assertSame(
            ['re' => 'a b/c'],
            $routeResolver->getNotDefinedParams('show', ['pathInfo' => '1', 're' => 'a b/c']),
        );
    }

    /** "$" ends the route: a route with a line feed after it matches no pattern that does not take one. */
    public function testAPatternDoesNotMatchBeforeAFinalLineFeed(): void
    {
        $routeResolver = new RouteResolver();
        $routeResolver->setConfig(['routes' => [
            'admin' => ['controller' => 'AdminController', 'pattern' => 'admin'],
            'step' => ['controller' => 'StepController', 'pattern' => 'password-reset/(?P<step>\d+)'],
        ]]);

        static::assertSame('admin', $routeResolver->getRouteIDByRoutePattern('admin'));
        static::assertNull($routeResolver->getRouteIDByRoutePattern("admin\n"));
        static::assertSame(['step' => '2'], $routeResolver->getParamsByRoutePattern('password-reset/2'));
        static::assertNull($routeResolver->getRouteIDByRoutePattern("password-reset/2\n"));
    }

    public function testSetConfigThrowsExceptionIfConfigDoesntContainRoutes(): void
    {
        $routeResolver = new RouteResolver();

        static::expectException(RuntimeException::class);
        $routeResolver->setConfig(['abc' => 'def']);
    }

    public function testSetConfigThrowsExceptionIfRoutesAreNoArray(): void
    {
        $routeResolver = new RouteResolver();

        static::expectException(RuntimeException::class);
        $routeResolver->setConfig(['routes' => 'abc']);
    }

    public function testSetConfigThrowsExceptionIfRoutesAreEmpty(): void
    {
        $routeResolver = new RouteResolver();

        static::expectException(RuntimeException::class);
        $routeResolver->setConfig(['routes' => []]);
    }

    public function testSetConfigThrowsExceptionIfRoutesContainNonStringKey(): void
    {
        $routeResolver = new RouteResolver();

        static::expectException(RuntimeException::class);
        $routeResolver->setConfig(['routes' => [0 => []]]);
    }

    public function testSetConfigThrowsExceptionIfRoutesContainNonEmptyStringKey(): void
    {
        $routeResolver = new RouteResolver();

        static::expectException(RuntimeException::class);
        $routeResolver->setConfig(['routes' => ['' => []]]);
    }

    public function testSetConfigThrowsExceptionIfRoutesContainNoRouteOptions(): void
    {
        $routeResolver = new RouteResolver();

        static::expectException(RuntimeException::class);
        $routeResolver->setConfig(['routes' => ['defaultRoute' => 'fail']]);
    }

    public function testSetConfigThrowsExceptionIfRouteOptionsInvalidKeys(): void
    {
        $routeResolver = new RouteResolver();

        static::expectException(RuntimeException::class);
        $routeResolver->setConfig(['routes' => ['defaultRoute' => ['abc']]]);
    }

    public function testSetConfigThrowsExceptionIfRouteOptionsMissingController(): void
    {
        $routeResolver = new RouteResolver();

        static::expectException(RuntimeException::class);
        $routeResolver->setConfig(['routes' => ['defaultRoute' => ['pattern' => 'abc']]]);
    }

    public function testSetConfigThrowsExceptionIfRouteOptionsMissingPattern(): void
    {
        $routeResolver = new RouteResolver();

        static::expectException(RuntimeException::class);
        $routeResolver->setConfig(['routes' => ['defaultRoute' => ['controller' => 'abc']]]);
    }

    public function testSetConfigThrowsExceptionIfRouteOptionsSuperflousArg(): void
    {
        $routeResolver = new RouteResolver();

        static::expectException(RuntimeException::class);
        $routeResolver->setConfig([
            'routes' => [
                'defaultRoute' => ['controller' => 'abc', 'pattern' => 'def', 'foobar'],
            ],
        ]);
    }

    public function testSetConfigTakesConfigCorrectly(): void
    {
        $method = new ReflectionMethod(RouteResolver::class, 'getConfig');

        $routeResolver = new RouteResolver();
        $routeResolver->setConfig(['routes' => [
            'defaultRoute' => ['controller' => 'abc', 'pattern' => 'def'],
            'altRoute' => ['controller' => 'foo', 'pattern' => 'bar'],
        ]]);

        static::assertSame(
            [
                'defaultRoute' => ['controller' => 'abc', 'pattern' => 'def'],
                'altRoute' => ['controller' => 'foo', 'pattern' => 'bar'],
            ],
            $method->invoke($routeResolver),
        );
    }
}
