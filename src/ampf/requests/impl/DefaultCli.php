<?php

declare(strict_types=1);

namespace ampf\requests\impl;

use ampf\beans\access\RouteResolverAccess;
use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\requests\CliRequest;
use RuntimeException;

/** @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable */
class DefaultCli implements BeanFactoryAccess, CliRequest
{
    use DefaultBeanFactoryAccess;
    use RouteResolverAccess;

    /** @var string[] */
    protected ?array $argv = null;

    protected ?string $responseBody = null;

    public function __construct()
    {
        $this->argv = $GLOBALS['argv'];
    }

    public function getController(): string
    {
        if (!isset($this->argv[1]) || trim($this->argv[1]) === '') {
            $arg = '*';
        } else {
            $arg = $this->argv[1];
        }

        $controller = $this->getRouteResolver()->getControllerByRoutePattern($arg);
        if ($controller === null) {
            throw new RuntimeException();
        }

        return $controller;
    }

    /** @return string[] */
    public function getRouteParams(): array
    {
        $arg = $this->argv;
        if ($arg === null || count($arg) < 2) {
            $arg = [];
        } else {
            array_shift($arg);
            array_shift($arg);
        }

        return $arg;
    }

    /** @param array<string, string> $params */
    public function getActionCmd(string $routeID, ?array $params = null): string
    {
        if ($params === null) {
            $params = [];
        }

        $routeID = $this->getRouteResolver()->getRoutePatternByRouteID($routeID, $params);
        if ($routeID === null) {
            throw new RuntimeException();
        }

        return $this->getCmd($routeID);
    }

    public function getCmd(string $routeID): string
    {
        if (!isset($this->argv[0])) {
            throw new RuntimeException();
        }

        return $this->argv[0] . ' ' . $routeID;
    }

    public function setResponse(string $response): self
    {
        $this->responseBody = $response;

        return $this;
    }

    public function flush(): self
    {
        echo $this->responseBody;
        $this->responseBody = null;

        return $this;
    }
}
