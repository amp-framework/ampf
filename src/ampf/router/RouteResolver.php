<?php

declare(strict_types=1);

namespace ampf\router;

interface RouteResolver
{
    public function getControllerByRoutePattern(string $routePattern): ?string;

    /** @return ?string[] */
    public function getNotDefinedParams(string $routeID, ?array $params = null): ?array;

    /** @return ?string[] */
    public function getParamsByRoutePattern(string $routePattern): ?array;

    public function getRouteIDByRoutePattern(string $routePattern): ?string;

    public function getRoutePatternByRouteID(string $routeID, ?array $params = null): ?string;
}
