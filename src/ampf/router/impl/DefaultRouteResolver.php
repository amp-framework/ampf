<?php

declare(strict_types=1);

namespace ampf\router\impl;

use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\router\RouteResolver;
use JetBrains\PhpStorm\ArrayShape;
use RuntimeException;

class DefaultRouteResolver implements BeanFactoryAccess, RouteResolver
{
    use DefaultBeanFactoryAccess;

    /** @var ?array<string, array<string, mixed>> */
    protected ?array $_config = null;

    public function getControllerByRoutePattern(string $routePattern): ?string
    {
        $array = $this->getControllerParamsByRoutePattern($routePattern);
        if ($array === null) {
            return null;
        }

        return $array[1];
    }

    /** @return ?string[] */
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

    /** @return ?string[] */
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

    /** @param array<string, mixed> $config */
    public function setConfig(array $config): void
    {
        if (!isset($config['routes'])) {
            throw new RuntimeException();
        }

        $config = $config['routes'];
        if (!is_array($config) || count($config) < 1) {
            throw new RuntimeException();
        }

        $correctKeys = ['pattern', 'controller'];

        foreach ($config as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                throw new RuntimeException();
            }

            $diff = array_diff(array_keys($value), $correctKeys);
            if (count($diff) !== 0) {
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
        foreach ($matches as $key => $value) {
            if (in_array($key, $allowedParams, true)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    #[ArrayShape(['route' => 'string', 'notUsedParams' => 'array'])]
    protected function getAdjustedRouteParams(string $regex, ?array $params = null): array
    {
        if ($params === null) {
            $params = [];
        }

        $matches = [];
        $catch = '/\(\?P\<(.+)\>[^\)]+\)/';
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
            $this->setConfig($this->getBeanFactory()->get('Config'));
        }

        return $this->_config;
    }

    /** @return ?mixed[] */
    protected function getControllerParamsByRoutePattern(string $routePattern): ?array
    {
        foreach ($this->getConfig() as $routeID => $value) {
            $preg = ('/^' . str_replace('/', '\/', $value['pattern']) . '$/');
            $matches = [];
            if (preg_match($preg, $routePattern, $matches)) {
                $matches = $this->cleanMatches($matches, $this->getRouteParams($value['pattern']));

                return [$routeID, $value['controller'], $matches];
            }
        }

        return null;
    }

    /** @return string[] */
    protected function getRouteParams(string $regex): array
    {
        $matches = [];
        $catch = '/\(\?P\<(.+)\>[^\)]+\)/';
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
                return $value['pattern'];
            }
        }

        return null;
    }
}
