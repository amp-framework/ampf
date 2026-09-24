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
        $array = $this->getControllerParamsByRoutePattern($routePattern);

        if ($array === null) {
            return null;
        }

        return $array[1];
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

        if ($params === null) {
            $params = [];
        }

        return $this->getAdjustedRouteParams($routePattern, $params)['notUsedParams'];
    }

    /**
     * @return ?array<string, string>
     */
    public function getParamsByRoutePattern(string $routePattern): ?array
    {
        $array = $this->getControllerParamsByRoutePattern($routePattern);

        if ($array === null) {
            return null;
        }

        return $array[2];
    }

    public function getRouteIDByRoutePattern(string $routePattern): ?string
    {
        $array = $this->getControllerParamsByRoutePattern($routePattern);

        if ($array === null) {
            return null;
        }

        return $array[0];
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

        if ($params === null) {
            $params = [];
        }

        return $this->getAdjustedRouteParams($routePattern, $params)['route'];
    }

    /**
     * @param array<mixed> $config the configuration, whose `routes` are taken
     */
    public function setConfig(array $config): void
    {
        if (!array_key_exists('routes', $config)) {
            throw new RuntimeException('The configuration has no routes.');
        }

        $config = $this->validateRouteConfig($config['routes']);

        $this->routes = $config;
    }

    /**
     * @param array<string, string> $matches
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
     * @param array<string, string> $params
     *
     * @return array{route: string, notUsedParams: array<string, string>}
     */
    protected function getAdjustedRouteParams(string $regex, ?array $params = null): array
    {
        if ($params === null) {
            $params = [];
        }

        $matches = [];
        $catch = '/\(\?P\<(.+)\>[^\)]+\)/U';
        preg_match_all($catch, $regex, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $search = $match[0];

            if (!isset($params[$match[1]])) {
                throw new RuntimeException('Missing parameter ' . $match[1]);
            }

            // A parameter is one path segment's text in the link: "/", "?", "#", "%", spaces and control
            // characters are encoded, an id or a word stays as it is
            $replace = rawurlencode($params[$match[1]]);
            unset($params[$match[1]]);
            $regex = str_replace($search, $replace, $regex);
        }

        return ['route' => $regex, 'notUsedParams' => $params];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function getConfig(): array
    {
        if ($this->routes === null) {
            $config = $this->getBeanFactory()->get('Config');

            if (!is_array($config) || !isset($config['routes'])) {
                throw new RuntimeException();
            }

            $this->setConfig($config);
        }

        if ($this->routes === null) {
            throw new RuntimeException();
        }

        return $this->routes;
    }

    /**
     * @return ?array{string, string, array<string, string>}
     */
    protected function getControllerParamsByRoutePattern(string $routePattern): ?array
    {
        foreach ($this->getConfig() as $routeId => $routeOptions) {
            if (!isset($routeOptions['pattern']) || !is_string($routeOptions['pattern'])) {
                throw new RuntimeException();
            }

            // D: "$" ends the route, it does not match before a final line feed
            $preg = ('/^' . str_replace('/', '\/', $routeOptions['pattern']) . '$/D');

            /**
             * $matches will contain string,string elements because of named parameters in the regex
             *
             * @var array<string, string> $matches
             */
            $matches = [];

            if (preg_match($preg, $routePattern, $matches)) {
                /** @phpstan-ignore argument.type */
                $matches = $this->cleanMatches($matches, $this->getRouteParams($routeOptions['pattern']));

                $controller = $routeOptions['controller'];

                if (!is_string($controller)) {
                    throw new RuntimeException();
                }

                return [$routeId, (string)$controller, $matches];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function getRouteParams(string $regex): array
    {
        $matches = [];
        $catch = '/\(\?P\<([^\>]+)\>[^\)]+\)/U';
        preg_match_all($catch, $regex, $matches, PREG_PATTERN_ORDER);

        return $matches[1];
    }

    protected function getRoutePattern(string $routeID): ?string
    {
        if (trim($routeID) === '') {
            throw new RuntimeException();
        }

        foreach ($this->getConfig() as $_routeID => $value) {
            if ($_routeID === $routeID) {
                if (!isset($value['pattern']) || !is_string($value['pattern'])) {
                    throw new RuntimeException();
                }

                return $value['pattern'];
            }
        }

        return null;
    }

    /**
     * @return array<string, array{pattern: string, controller: string}>
     */
    protected function validateRouteConfig(mixed $config): array
    {
        if (!is_array($config) || count($config) < 1) {
            throw new RuntimeException();
        }

        $result = [];

        foreach ($config as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                throw new RuntimeException();
            }

            if (!is_array($value)) {
                throw new RuntimeException();
            }

            // A route names its pattern and its controller, and nothing else
            $keys = array_keys($value);
            sort($keys);

            if ($keys !== ['controller', 'pattern']) {
                throw new RuntimeException();
            }

            if (!is_string($value['pattern']) || !is_string($value['controller'])) {
                throw new RuntimeException();
            }

            /** @var array{pattern: string, controller: string} $value the options as configured, in their order */
            $result[$key] = $value;
        }

        return $result;
    }
}
