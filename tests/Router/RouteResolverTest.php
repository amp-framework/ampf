<?php

declare(strict_types=1);

namespace ampfTest\Router;

use ampf\router\impl\DefaultRouteResolver;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * @covers \ampf\router\impl\DefaultRouteResolver
 *
 * @internal
 */
class RouteResolverTest extends TestCase
{
    public function testCleanMatches(): void
    {
        $method = new ReflectionMethod(DefaultRouteResolver::class, 'cleanMatches');
        $method->setAccessible(true);

        $routeResolver = new DefaultRouteResolver();

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
        $method = new ReflectionMethod(DefaultRouteResolver::class, 'getControllerParamsByRoutePattern');
        $method->setAccessible(true);

        $routeResolver = new DefaultRouteResolver();

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
        $method = new ReflectionMethod(DefaultRouteResolver::class, 'getRouteParams');
        $method->setAccessible(true);

        $routeResolver = new DefaultRouteResolver();

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

    public function testSetConfigThrowsExceptionIfConfigDoesntContainRoutes(): void
    {
        $routeResolver = new DefaultRouteResolver();

        static::expectException(RuntimeException::class);
        /** @phpstan-ignore-next-line */
        $routeResolver->setConfig(['abc' => 'def']);
    }

    public function testSetConfigThrowsExceptionIfRoutesAreNoArray(): void
    {
        $routeResolver = new DefaultRouteResolver();

        static::expectException(RuntimeException::class);
        /** @phpstan-ignore-next-line */
        $routeResolver->setConfig(['routes' => 'abc']);
    }

    public function testSetConfigThrowsExceptionIfRoutesAreEmpty(): void
    {
        $routeResolver = new DefaultRouteResolver();

        static::expectException(RuntimeException::class);
        $routeResolver->setConfig(['routes' => []]);
    }

    public function testSetConfigThrowsExceptionIfRoutesContainNonStringKey(): void
    {
        $routeResolver = new DefaultRouteResolver();

        static::expectException(RuntimeException::class);
        /** @phpstan-ignore-next-line */
        $routeResolver->setConfig(['routes' => [0 => []]]);
    }

    public function testSetConfigThrowsExceptionIfRoutesContainNonEmptyStringKey(): void
    {
        $routeResolver = new DefaultRouteResolver();

        static::expectException(RuntimeException::class);
        /** @phpstan-ignore-next-line */
        $routeResolver->setConfig(['routes' => ['' => []]]);
    }

    public function testSetConfigThrowsExceptionIfRoutesContainNoRouteOptions(): void
    {
        $routeResolver = new DefaultRouteResolver();

        static::expectException(RuntimeException::class);
        /** @phpstan-ignore-next-line */
        $routeResolver->setConfig(['routes' => ['defaultRoute' => 'fail']]);
    }

    public function testSetConfigThrowsExceptionIfRouteOptionsInvalidKeys(): void
    {
        $routeResolver = new DefaultRouteResolver();

        static::expectException(RuntimeException::class);
        /** @phpstan-ignore-next-line */
        $routeResolver->setConfig(['routes' => ['defaultRoute' => ['abc']]]);
    }

    public function testSetConfigThrowsExceptionIfRouteOptionsMissingController(): void
    {
        $routeResolver = new DefaultRouteResolver();

        static::expectException(RuntimeException::class);
        /** @phpstan-ignore-next-line */
        $routeResolver->setConfig(['routes' => ['defaultRoute' => ['pattern' => 'abc']]]);
    }

    public function testSetConfigThrowsExceptionIfRouteOptionsMissingPattern(): void
    {
        $routeResolver = new DefaultRouteResolver();

        static::expectException(RuntimeException::class);
        /** @phpstan-ignore-next-line */
        $routeResolver->setConfig(['routes' => ['defaultRoute' => ['controller' => 'abc']]]);
    }

    public function testSetConfigThrowsExceptionIfRouteOptionsSuperflousArg(): void
    {
        $routeResolver = new DefaultRouteResolver();

        static::expectException(RuntimeException::class);
        $routeResolver->setConfig(['routes' => ['defaultRoute' => ['controller' => 'abc', 'pattern' => 'def', 'foobar']]]);
    }

    public function testSetConfigTakesConfigCorrectly(): void
    {
        $method = new ReflectionMethod(DefaultRouteResolver::class, 'getConfig');
        $method->setAccessible(true);

        $routeResolver = new DefaultRouteResolver();
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
