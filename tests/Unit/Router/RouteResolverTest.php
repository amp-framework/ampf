<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Router;

use ampf\Bean\BeanFactory;
use ampf\Router\RouteResolver;
use ampf\Tests\Support\Router\SeamRouteResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

#[CoversClass(RouteResolver::class)]
final class RouteResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function provideMalformedConfigs(): iterable
    {
        yield 'no routes' => [['abc' => 'def'], 'The configuration has no routes.'];
        yield 'routes that are no array' => [['routes' => 'abc'], 'The configuration\'s routes must be an array, not string.'];
        yield 'empty routes' => [['routes' => []], 'The configuration has no routes.'];
        yield 'a route id that is a number' => [['routes' => [0 => []]], 'A route\'s id must be a non-blank string, not 0.'];
        yield 'an empty route id' => [['routes' => ['' => []]], 'A route\'s id must be a non-blank string, not \'\'.'];
        yield 'a blank route id' => [['routes' => [' ' => []]], 'A route\'s id must be a non-blank string, not \' \'.'];
        yield 'a route that is no array' => [
            ['routes' => ['defaultRoute' => 'fail']],
            'The route defaultRoute must be defined by an array.',
        ];
        yield 'a route with a list' => [
            ['routes' => ['defaultRoute' => ['abc']]],
            'The route defaultRoute must name its pattern and its controller, and nothing else.',
        ];
        yield 'a route without a controller' => [
            ['routes' => ['defaultRoute' => ['pattern' => 'abc']]],
            'The route defaultRoute must name its pattern and its controller, and nothing else.',
        ];
        yield 'a route without a pattern' => [
            ['routes' => ['defaultRoute' => ['controller' => 'abc']]],
            'The route defaultRoute must name its pattern and its controller, and nothing else.',
        ];
        yield 'a route with another option' => [
            ['routes' => ['defaultRoute' => ['controller' => 'abc', 'pattern' => 'def', 'foobar']]],
            'The route defaultRoute must name its pattern and its controller, and nothing else.',
        ];
        yield 'a pattern that is no string' => [
            ['routes' => ['defaultRoute' => ['controller' => 'abc', 'pattern' => 1]]],
            'The route defaultRoute must name its pattern and its controller by strings.',
        ];
        yield 'a controller that is no string' => [
            ['routes' => ['defaultRoute' => ['controller' => null, 'pattern' => 'def']]],
            'The route defaultRoute must name its pattern and its controller by strings.',
        ];
    }

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

            self::assertSame($cleanedMatches, $method->invoke($routeResolver, $matches, $allowedParams));
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

            self::assertSame($expectedReturn, $method->invoke($routeResolver, $routePattern));
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
            self::assertSame($routeParams, $method->invoke($routeResolver, $routePattern));
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
            self::assertSame($expected, $routeResolver->getRoutePatternByRouteID($routeId, $params), $expected);
        }
    }

    /** The parameters a route pattern does not name are handed back as they are (they become the query string). */
    public function testParametersTheRouteDoesNotNameAreHandedBack(): void
    {
        $routeResolver = new RouteResolver();
        $routeResolver->setConfig(['routes' => [
            'show' => ['controller' => 'ShowController', 'pattern' => 'show/(?P<pathInfo>.*)'],
        ]]);

        self::assertSame(
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

        self::assertSame('admin', $routeResolver->getRouteIDByRoutePattern('admin'));
        self::assertNull($routeResolver->getRouteIDByRoutePattern("admin\n"));
        self::assertSame(['step' => '2'], $routeResolver->getParamsByRoutePattern('password-reset/2'));
        self::assertNull($routeResolver->getRouteIDByRoutePattern("password-reset/2\n"));
    }

    /**
     * @param array<mixed> $config
     */
    #[DataProvider('provideMalformedConfigs')]
    public function testAMalformedConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        new RouteResolver()->setConfig($config);
    }

    public function testWithoutAConfigurationTheRoutesAreTheBeanConfigsReadOnce(): void
    {
        $beanFactory = new BeanFactory(['routes' => [
            'home' => ['pattern' => '', 'controller' => 'HomeController'],
            'show' => ['pattern' => 'show/(?P<id>[0-9]+)', 'controller' => 'ShowController'],
        ]]);
        $routeResolver = new RouteResolver();
        $routeResolver->setBeanFactory($beanFactory);

        self::assertSame('ShowController', $routeResolver->getControllerByRoutePattern('show/7'));
        self::assertSame('HomeController', $routeResolver->getControllerByRoutePattern(''));

        $beanFactory->set('Config', ['routes' => ['other' => ['pattern' => '', 'controller' => 'OtherController']]]);
        self::assertSame('HomeController', $routeResolver->getControllerByRoutePattern(''), 'the routes are read once');
    }

    public function testABeanConfigWithoutRoutesIsRefused(): void
    {
        $routeResolver = new RouteResolver();
        $routeResolver->setBeanFactory(new BeanFactory(['beans' => []]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The configuration has no routes.');

        $routeResolver->getControllerByRoutePattern('');
    }

    public function testASubclassChangesTheStepsOfTheResolution(): void
    {
        $resolver = new SeamRouteResolver();
        $resolver->setBeanFactory(new BeanFactory(['routes' => [
            'show' => ['pattern' => 'show/(?P<id>[0-9]+)', 'controller' => 'ShowController'],
        ]]));

        self::assertSame('ShowController', $resolver->getControllerByRoutePattern('show/7'));
        self::assertSame('show/8', $resolver->getRoutePatternByRouteID('show', ['id' => '8']));
        self::assertSame(
            [
                'cleanMatches',
                'getAdjustedRouteParams',
                'getConfig',
                'getControllerParamsByRoutePattern',
                'getRouteParams',
                'getRoutePattern',
                'routesOf',
                'validateRouteConfig',
            ],
            $resolver->getCalledMethods(),
        );
    }

    public function testATextNoPatternMatchesHasNoRoute(): void
    {
        $routeResolver = $this->routeResolver();

        self::assertNull($routeResolver->getControllerByRoutePattern('unknown'));
        self::assertNull($routeResolver->getRouteIDByRoutePattern('unknown'));
        self::assertNull($routeResolver->getParamsByRoutePattern('unknown'));
    }

    public function testTheFirstMatchingPatternWins(): void
    {
        $routeResolver = new RouteResolver();
        $routeResolver->setConfig(['routes' => [
            'new' => ['pattern' => 'user/new', 'controller' => 'NewController'],
            'show' => ['pattern' => 'user/(?P<name>[a-z]+)', 'controller' => 'ShowController'],
        ]]);

        self::assertSame('NewController', $routeResolver->getControllerByRoutePattern('user/new'));
        self::assertSame('ShowController', $routeResolver->getControllerByRoutePattern('user/newton'));
        self::assertSame(['name' => 'newton'], $routeResolver->getParamsByRoutePattern('user/newton'));
    }

    public function testAnUnknownRouteIdHasNoRoute(): void
    {
        $routeResolver = $this->routeResolver();

        self::assertNull($routeResolver->getRoutePatternByRouteID('unknown'));
        self::assertNull($routeResolver->getNotDefinedParams('unknown', ['id' => '1']));
    }

    public function testABlankRouteIdIsRefused(): void
    {
        foreach (['getRoutePatternByRouteID', 'getNotDefinedParams'] as $method) {
            try {
                $this->routeResolver()->{$method}(' ');
                self::fail($method . ' took a blank route id');
            } catch (RuntimeException $e) {
                self::assertSame('A route id must not be blank.', $e->getMessage());
            }
        }
    }

    public function testARouteWithoutItsParameterHasNoText(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing parameter id for the route pattern show/(?P<id>[0-9]+).');

        $this->routeResolver()->getRoutePatternByRouteID('show', ['other' => '1']);
    }

    public function testARouteWithoutCapturesIsItsPattern(): void
    {
        $routeResolver = $this->routeResolver();

        self::assertSame('about', $routeResolver->getRoutePatternByRouteID('about'));
        self::assertSame(['page' => '2'], $routeResolver->getNotDefinedParams('about', ['page' => '2']));
        self::assertSame([], $routeResolver->getNotDefinedParams('about'));
    }

    public function testSetConfigTakesConfigCorrectly(): void
    {
        $method = new ReflectionMethod(RouteResolver::class, 'getConfig');

        $routeResolver = new RouteResolver();
        $routeResolver->setConfig(['routes' => [
            'defaultRoute' => ['controller' => 'abc', 'pattern' => 'def'],
            'altRoute' => ['controller' => 'foo', 'pattern' => 'bar'],
        ]]);

        self::assertSame(
            [
                'defaultRoute' => ['controller' => 'abc', 'pattern' => 'def'],
                'altRoute' => ['controller' => 'foo', 'pattern' => 'bar'],
            ],
            $method->invoke($routeResolver),
        );
    }

    private function routeResolver(): RouteResolver
    {
        $routeResolver = new RouteResolver();
        $routeResolver->setConfig(['routes' => [
            'about' => ['pattern' => 'about', 'controller' => 'AboutController'],
            'show' => ['pattern' => 'show/(?P<id>[0-9]+)', 'controller' => 'ShowController'],
        ]]);

        return $routeResolver;
    }
}
