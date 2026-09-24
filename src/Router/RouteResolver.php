<?php

declare(strict_types=1);

namespace ampf\Router;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use RuntimeException;

/**
 * The routes of the merged configuration (`routes`: route id => `pattern` and `controller`), in both directions: a
 * route's text to its route id, controller bean and parameters — the first pattern that matches the whole text, the
 * named captures as parameters —, and a route id with its parameters back to the route's text.
 */
class RouteResolver implements BeanFactoryAccessInterface, RouteResolverInterface
{
    use BeanFactoryAccess;

    /**
     * @var ?array<string, array{pattern: string, controller: string}>
     */
    protected ?array $routes = null;

    public function getControllerByRoutePattern(string $routePattern): ?string
    {
        return $this->getControllerParamsByRoutePattern($routePattern)[1] ?? null;
    }

    /**
     * @param array<string, string> $params
     *
     * @return ?array<string, string>
     */
    public function getNotDefinedParams(string $routeID, ?array $params = null): ?array
    {
        $routePattern = $this->getRoutePattern($routeID);

        if ($routePattern === null) {
            return null;
        }

        return $this->getAdjustedRouteParams($routePattern, $params)['notUsedParams'];
    }

    /**
     * @return ?array<string, string>
     */
    public function getParamsByRoutePattern(string $routePattern): ?array
    {
        return $this->getControllerParamsByRoutePattern($routePattern)[2] ?? null;
    }

    public function getRouteIDByRoutePattern(string $routePattern): ?string
    {
        return $this->getControllerParamsByRoutePattern($routePattern)[0] ?? null;
    }

    /**
     * @param array<string, string> $params
     */
    public function getRoutePatternByRouteID(string $routeID, ?array $params = null): ?string
    {
        $routePattern = $this->getRoutePattern($routeID);

        if ($routePattern === null) {
            return null;
        }

        return $this->getAdjustedRouteParams($routePattern, $params)['route'];
    }

    /**
     * @param array<mixed> $config the configuration, whose `routes` are taken
     *
     * @throws RuntimeException for routes that are missing or malformed
     */
    public function setConfig(array $config): void
    {
        $this->routes = $this->routesOf($config);
    }

    /**
     * The named captures among the matches, in their order.
     *
     * @param array<int|string, string> $matches
     * @param list<string> $allowedParams
     *
     * @return array<string, string>
     */
    protected function cleanMatches(array $matches, array $allowedParams): array
    {
        $result = [];

        foreach ($matches as $paramName => $paramValue) {
            // @phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
            if (in_array($paramName, $allowedParams, true)) {
                $result[$paramName] = $paramValue;
            }
        }

        return $result;
    }

    /**
     * The route's text: every named capture of the pattern replaced by its parameter, percent-encoded; and the
     * parameters the pattern does not name.
     *
     * @param array<string, string> $params
     *
     * @return array{route: string, notUsedParams: array<string, string>}
     *
     * @throws RuntimeException when a parameter the pattern names is missing
     */
    protected function getAdjustedRouteParams(string $regex, ?array $params = null): array
    {
        $params ??= [];
        $route = $regex;
        $matches = [];
        preg_match_all('/\(\?P\<(.+)\>[^\)]+\)/U', $regex, $matches, PREG_SET_ORDER);

        foreach ($matches as [$capture, $name]) {
            if (!isset($params[$name])) {
                throw new RuntimeException('Missing parameter ' . $name . ' for the route pattern ' . $regex . '.');
            }

            // A parameter is one path segment's text in the link: "/", "?", "#", "%", spaces and control
            // characters are encoded, an id or a word stays as it is
            $route = str_replace($capture, rawurlencode($params[$name]), $route);
            unset($params[$name]);
        }

        return ['route' => $route, 'notUsedParams' => $params];
    }

    /**
     * The routes of the bean 'Config', read once.
     *
     * @return array<string, array{pattern: string, controller: string}>
     */
    protected function getConfig(): array
    {
        return $this->routes ??= $this->routesOf($this->getBeanFactory()->getConfig());
    }

    /**
     * The first route whose pattern matches the whole text: its id, its controller bean, its named captures.
     *
     * @return ?array{string, string, array<string, string>}
     */
    protected function getControllerParamsByRoutePattern(string $routePattern): ?array
    {
        foreach ($this->getConfig() as $routeId => $route) {
            // D: "$" ends the route, it does not match before a final line feed
            $matches = [];

            if (preg_match('/^' . str_replace('/', '\/', $route['pattern']) . '$/D', $routePattern, $matches) === 1) {
                $params = $this->cleanMatches($matches, $this->getRouteParams($route['pattern']));

                return [$routeId, $route['controller'], $params];
            }
        }

        return null;
    }

    /**
     * The names of the pattern's captures, in their order.
     *
     * @return list<string>
     */
    protected function getRouteParams(string $regex): array
    {
        $matches = [];
        preg_match_all('/\(\?P\<([^\>]+)\>[^\)]+\)/U', $regex, $matches, PREG_PATTERN_ORDER);

        return $matches[1];
    }

    /**
     * @throws RuntimeException for a blank route id
     */
    protected function getRoutePattern(string $routeID): ?string
    {
        if (trim($routeID) === '') {
            throw new RuntimeException('A route id must not be blank.');
        }

        return $this->getConfig()[$routeID]['pattern'] ?? null;
    }

    /**
     * The configuration's routes, checked.
     *
     * @param array<mixed> $config
     *
     * @return array<string, array{pattern: string, controller: string}>
     *
     * @throws RuntimeException for routes that are missing or malformed
     */
    protected function routesOf(array $config): array
    {
        if (!array_key_exists('routes', $config)) {
            throw new RuntimeException('The configuration has no routes.');
        }

        return $this->validateRouteConfig($config['routes']);
    }

    /**
     * @return array<string, array{pattern: string, controller: string}>
     *
     * @throws RuntimeException for routes that are no non-empty array, and for a route that is malformed
     */
    protected function validateRouteConfig(mixed $config): array
    {
        if (!is_array($config)) {
            throw new RuntimeException(
                'The configuration\'s routes must be an array, not ' . get_debug_type($config) . '.',
            );
        }

        if ($config === []) {
            throw new RuntimeException('The configuration has no routes.');
        }

        $result = [];

        foreach ($config as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                throw new RuntimeException(
                    'A route\'s id must be a non-blank string, not ' . var_export($key, true) . '.',
                );
            }

            // A route names its pattern and its controller, and nothing else
            if (!is_array($value)) {
                throw new RuntimeException('The route ' . $key . ' must be defined by an array.');
            }

            $keys = array_keys($value);
            sort($keys);

            if ($keys !== ['controller', 'pattern']) {
                throw new RuntimeException(
                    'The route ' . $key . ' must name its pattern and its controller, and nothing else.',
                );
            }

            if (!is_string($value['pattern']) || !is_string($value['controller'])) {
                throw new RuntimeException(
                    'The route ' . $key . ' must name its pattern and its controller by strings.',
                );
            }

            /** @var array{pattern: string, controller: string} $value the options as configured, in their order */
            $result[$key] = $value;
        }

        return $result;
    }
}
