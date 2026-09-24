<?php

declare(strict_types=1);

namespace ampf\Request;

/**
 * A command line call: the first argument names the route, the others are the route's parameters in their order;
 * the response is text that flush() prints.
 */
interface CliRequestInterface
{
    public function getController(): string;

    /**
     * @return list<string>
     */
    public function getRouteParams(): array;

    public function getCmd(string $routeID): string;

    /**
     * @param array<string, string> $params
     */
    public function getActionCmd(string $routeID, ?array $params = null): string;

    public function setResponse(string $response): self;

    public function flush(): self;
}
