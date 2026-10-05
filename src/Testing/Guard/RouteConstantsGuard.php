<?php

declare(strict_types=1);

namespace ampf\Testing\Guard;

use ReflectionClass;

/**
 * An application's class of route constants lists exactly the route ids of its config/http.php (the constants
 * `ROUTE_ID_*`) and of its config/cli.php (`CLI_ROUTE_ID_*`): no constant without a route, no route without a constant,
 * no two of them of one value; and each transport's catch-all is its last route. Each configuration file is read on its
 * own. An application extends it in one class that names its project root, the constants class and the two catch-alls.
 */
abstract class RouteConstantsGuard extends AbstractGuard
{
    /** The prefix of the constants of the web's route ids. */
    protected const string WEB_PREFIX = 'ROUTE_ID_';

    /** The prefix of the constants of the command line's route ids. */
    protected const string CLI_PREFIX = 'CLI_ROUTE_ID_';

    /**
     * The class of the route constants.
     *
     * @return class-string
     */
    abstract protected static function routeConstantsClass(): string;

    /** The route id of the web's catch-all, which takes every route no other route takes. */
    abstract protected static function catchAllRouteId(): string;

    /** The route id of the command line's catch-all. */
    abstract protected static function cliCatchAllRouteId(): string;

    /**
     * The route ids of the application's config/<transport>.php, in its order.
     *
     * @return list<string>
     */
    protected static function routeIds(string $transport): array
    {
        return array_keys(static::routes(static::configurationFile($transport . '.php')));
    }

    /**
     * The route constants of either prefix, by their names, in their order.
     *
     * @return array<string, string>
     */
    protected static function routeConstants(): array
    {
        $constants = [];

        foreach (new ReflectionClass(static::routeConstantsClass())->getConstants() as $name => $value) {
            if (str_starts_with($name, static::WEB_PREFIX) || str_starts_with($name, static::CLI_PREFIX)) {
                self::assertIsString($value, 'The route constant ' . $name . ' is no text.');
                $constants[$name] = $value;
            }
        }

        return $constants;
    }

    public function testTheWebConstantsAreTheWebRoutes(): void
    {
        $this->assertTheConstantsAreTheRoutes(static::WEB_PREFIX, 'http');
    }

    public function testTheCommandLineConstantsAreTheCommandLineRoutes(): void
    {
        $this->assertTheConstantsAreTheRoutes(static::CLI_PREFIX, 'cli');
    }

    public function testNoTwoRouteConstantsShareAValue(): void
    {
        $names = [];

        foreach (static::routeConstants() as $name => $value) {
            $names[$value][] = $name;
        }

        $problems = [];

        foreach ($names as $value => $sharing) {
            if (count($sharing) > 1) {
                $problems[] = implode(' and ', $sharing) . ' share the value ' . $value . '.';
            }
        }

        self::assertEmpty($problems, implode(PHP_EOL, $problems));
    }

    public function testTheWebCatchAllIsTheLastWebRoute(): void
    {
        self::assertSame(
            static::catchAllRouteId(),
            array_last(static::routeIds('http')),
            'The catch-all ' . static::catchAllRouteId() . ' is not the last route of config/http.php.',
        );
    }

    public function testTheCommandLineCatchAllIsTheLastCommandLineRoute(): void
    {
        self::assertSame(
            static::cliCatchAllRouteId(),
            array_last(static::routeIds('cli')),
            'The catch-all ' . static::cliCatchAllRouteId() . ' is not the last route of config/cli.php.',
        );
    }

    /** The values of the constants of the prefix are the route ids of the transport's file, in any order. */
    protected function assertTheConstantsAreTheRoutes(string $prefix, string $transport): void
    {
        $routeIds = static::routeIds($transport);
        $values = array_filter(
            static::routeConstants(),
            static fn (string $name): bool => str_starts_with($name, $prefix),
            ARRAY_FILTER_USE_KEY,
        );
        sort($routeIds);
        sort($values);

        self::assertSame(
            $routeIds,
            $values,
            'The constants ' . $prefix . '* of ' . static::routeConstantsClass() . ' are not the routes of config/'
            . $transport . '.php.',
        );
    }
}
