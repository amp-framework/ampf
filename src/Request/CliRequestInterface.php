<?php

declare(strict_types=1);

namespace ampf\Request;

use InvalidArgumentException;
use RuntimeException;

/**
 * A command line call: the first argument names the route, the others are the route's parameters in their order;
 * the response is text that flush() prints, and the exit code the status the entry point ends with.
 */
interface CliRequestInterface
{
    /**
     * The controller bean of the route the command line names.
     *
     * @throws RuntimeException when no route matches
     */
    public function getController(): string;

    /**
     * The arguments after the route, in their order.
     *
     * @return list<string>
     */
    public function getRouteParams(): array;

    /** The command line that runs the route: the script, then the route. */
    public function getCmd(string $routeID): string;

    /**
     * The command line that runs the route with the route's own parameters put in.
     *
     * @param array<string, string> $params
     *
     * @throws RuntimeException for an unknown route id
     */
    public function getActionCmd(string $routeID, ?array $params = null): string;

    public function setResponse(string $response): self;

    /**
     * The status the process ends with, 0 unless a controller said otherwise: the entry point passes it to exit().
     */
    public function getExitCode(): int;

    /**
     * @throws InvalidArgumentException for a status outside 0 to 254 (255 is PHP's own)
     */
    public function setExitCode(int $exitCode): self;

    /** Prints the response. */
    public function flush(): self;
}
