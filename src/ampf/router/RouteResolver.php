<?php

declare(strict_types=1);

namespace ampf\router;

interface RouteResolver
{
    public function getControllerByRoutePattern(string $routePattern): ?string;

    /**
     * @param array<string, string> $params
     *
     * @return ?array<string, string>
     */
    public function getNotDefinedParams(string $routeID, ?array $params = null): ?array;

    /** @return ?array<string, string> */
    public function getParamsByRoutePattern(string $routePattern): ?array;

    public function getRouteIDByRoutePattern(string $routePattern): ?string;

    /** @param array<string, string> $params */
    public function getRoutePatternByRouteID(string $routeID, ?array $params = null): ?string;
}
