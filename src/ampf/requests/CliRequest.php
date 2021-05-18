<?php

declare(strict_types=1);

namespace ampf\requests;

interface CliRequest
{
    public function getController(): string;

    /** @return string[] */
    public function getRouteParams(): array;

    public function getCmd(string $routeID): string;

    /** @param array<string, string> $params */
    public function getActionCmd(string $routeID, ?array $params = null): string;

    public function setResponse(string $response): self;

    public function flush(): self;
}
