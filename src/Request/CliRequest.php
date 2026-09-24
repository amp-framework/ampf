<?php

declare(strict_types=1);

namespace ampf\Request;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\BeanAccess\RouteResolverAccess;
use InvalidArgumentException;
use RuntimeException;

/**
 * The command line of PHP's `$argv`: `php bin/index.php <route> [arguments…]` — the route `*` when none is given.
 *
 * @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 */
class CliRequest implements BeanFactoryAccessInterface, CliRequestInterface
{
    use BeanFactoryAccess;
    use RouteResolverAccess;

    /**
     * @var list<string>
     */
    protected array $argv = [];

    protected ?string $responseBody = null;

    protected int $exitCode = 0;

    /**
     * @param ?list<string> $argv the command line; PHP's `$argv` when none is given (the bean factory gives none)
     */
    public function __construct(?array $argv = null)
    {
        if ($argv === null) {
            $serverArgv = $_SERVER['argv'] ?? [];
            $argv = [];

            foreach (is_array($serverArgv) ? $serverArgv : [] as $argument) {
                if (is_string($argument)) {
                    $argv[] = $argument;
                }
            }
        }

        $this->argv = $argv;
    }

    public function getController(): string
    {
        $route = trim($this->argv[1] ?? '') === ''
            ? '*'
            : $this->argv[1];

        return $this->getRouteResolver()->getControllerByRoutePattern($route)
            ?? throw new RuntimeException('No route matches the command line\'s route ' . $route . '.');
    }

    /**
     * @return list<string>
     */
    public function getRouteParams(): array
    {
        return array_slice($this->argv, 2);
    }

    /**
     * @param array<string, string> $params
     */
    public function getActionCmd(string $routeID, ?array $params = null): string
    {
        $route = $this->getRouteResolver()->getRoutePatternByRouteID($routeID, $params ?? [])
            ?? throw new RuntimeException('There is no route ' . $routeID . '.');

        return $this->getCmd($route);
    }

    public function getCmd(string $routeID): string
    {
        return ($this->argv[0] ?? throw new RuntimeException('The command line names no script.')) . ' ' . $routeID;
    }

    public function setResponse(string $response): self
    {
        $this->responseBody = $response;

        return $this;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function setExitCode(int $exitCode): self
    {
        if ($exitCode < 0 || $exitCode > 254) {
            throw new InvalidArgumentException('An exit code is a number from 0 to 254, not ' . $exitCode . '.');
        }

        $this->exitCode = $exitCode;

        return $this;
    }

    public function flush(): self
    {
        echo $this->responseBody;
        $this->responseBody = null;

        return $this;
    }
}
