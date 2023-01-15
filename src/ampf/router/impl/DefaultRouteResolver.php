<?php

declare(strict_types=1);

namespace ampf\router\impl;

use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\router\RouteResolver;
use RuntimeException;

class DefaultRouteResolver implements BeanFactoryAccess, RouteResolver
{
    use DefaultBeanFactoryAccess;

    /** @var ?array<string, array{pattern: string, controller: string}> */
    protected ?array $_config = null;

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

    /** @return ?array<string, string> */
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

    /** @param array<string, string> $params */
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

    /** @param array{routes: ?array<string, array{pattern: string, controller: string}>} $config */
    public function setConfig(array $config): void
    {
        if (!isset($config['routes'])) {
            throw new RuntimeException();
        }

        /** @var array<string, array{pattern: string, controller: string}> $config */
        $config = $config['routes'];
        if (!is_array($config) || count($config) < 1) {
            throw new RuntimeException();
        }

        $correctKeys = ['pattern', 'controller'];

        foreach ($config as $routeId => $routeOptions) {
            if (!is_string($routeId) || trim($routeId) === '' || !is_array($routeOptions)) {
                throw new RuntimeException();
            }

            $diff = array_diff(array_keys($routeOptions), $correctKeys);
            if (count($diff) !== 0) {
                throw new RuntimeException();
            }

            $diff2 = array_diff($correctKeys, array_keys($routeOptions));
            if (count($diff2) !== 0) {
                throw new RuntimeException();
            }
        }

        $this->_config = $config;
    }

    /**
     * @param array<string, string> $matches
     * @param string[]              $allowedParams
     *
     * @return array<string, string>
     */
    protected function cleanMatches(array $matches, array $allowedParams): array
    {
        $result = [];
        foreach ($matches as $paramName => $paramValue) {
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

            $replace = $params[$match[1]];
            unset($params[$match[1]]);
            $regex = str_replace($search, $replace, $regex);
        }

        return ['route' => $regex, 'notUsedParams' => $params];
    }

    /** @return array<string, array<string, mixed>> */
    protected function getConfig(): array
    {
        if ($this->_config === null) {
            $config = $this->getBeanFactory()->get('Config');
            if (!is_array($config) || !isset($config['routes'])) {
                throw new RuntimeException();
            }
            $this->setConfig($config);
        }

        if ($this->_config === null) {
            throw new RuntimeException();
        }

        return $this->_config;
    }

    /** @return ?array{string, string, array<string, string>} */
    protected function getControllerParamsByRoutePattern(string $routePattern): ?array
    {
        foreach ($this->getConfig() as $routeId => $routeOptions) {
            if (!isset($routeOptions['pattern']) || !is_string($routeOptions['pattern'])) {
                throw new RuntimeException();
            }

            $preg = ('/^' . str_replace('/', '\/', $routeOptions['pattern']) . '$/');

            /**
             * $matches will contain string,string elements because of named parameters in the regex
             *
             * @var array<string, string> $matches
             */
            $matches = [];
            if (preg_match($preg, $routePattern, $matches)) {
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

    /** @return string[] */
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
}
