<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Router;

use ampf\Router\RouteResolver;

/**
 * The route resolver whose protected methods note their calls and then do their work, as an application's resolver
 * that changes one step would.
 */
final class SeamRouteResolver extends RouteResolver
{
    /**
     * @var list<string>
     */
    private array $calls = [];

    /**
     * The names of the protected methods called so far, each once, sorted.
     *
     * @return list<string>
     */
    public function getCalledMethods(): array
    {
        $methods = array_values(array_unique($this->calls));
        sort($methods);

        return $methods;
    }

    /**
     * @param array<int|string, string> $matches
     *
     * @param list<string> $allowedParams
     *
     * @return array<string, string>
     */

    protected function cleanMatches(array $matches, array $allowedParams): array
    {
        $this->calls[] = __FUNCTION__;

        return parent::cleanMatches($matches, $allowedParams);
    }

    /**
     * @param array<string, string> $params
     *
     * @return array{route: string, notUsedParams: array<string, string>}
     */

    protected function getAdjustedRouteParams(string $regex, ?array $params = null): array
    {
        $this->calls[] = __FUNCTION__;

        return parent::getAdjustedRouteParams($regex, $params);
    }

    /**
     * @return array<string, array{pattern: string, controller: string}>
     */

    protected function getConfig(): array
    {
        $this->calls[] = __FUNCTION__;

        return parent::getConfig();
    }

    /**
     * @return ?array{string, string, array<string, string>}
     */

    protected function getControllerParamsByRoutePattern(string $routePattern): ?array
    {
        $this->calls[] = __FUNCTION__;

        return parent::getControllerParamsByRoutePattern($routePattern);
    }

    /**
     * @return list<string>
     */

    protected function getRouteParams(string $regex): array
    {
        $this->calls[] = __FUNCTION__;

        return parent::getRouteParams($regex);
    }

    protected function getRoutePattern(string $routeID): ?string
    {
        $this->calls[] = __FUNCTION__;

        return parent::getRoutePattern($routeID);
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<string, array{pattern: string, controller: string}>
     */

    protected function routesOf(array $config): array
    {
        $this->calls[] = __FUNCTION__;

        return parent::routesOf($config);
    }

    /**
     * @return array<string, array{pattern: string, controller: string}>
     */

    protected function validateRouteConfig(mixed $config): array
    {
        $this->calls[] = __FUNCTION__;

        return parent::validateRouteConfig($config);
    }
}
