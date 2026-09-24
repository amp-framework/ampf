<?php

declare(strict_types=1);

namespace ampf\Router;

/** Maps a route's text to its route id, controller bean and parameters, and a route id back to a route's text. */
interface RouteResolverInterface
{
    public function getControllerByRoutePattern(string $routePattern): ?string;

    /**
     * @param array<string, string> $params
     *
     * @return ?array<string, string>
     */
    public function getNotDefinedParams(string $routeID, ?array $params = null): ?array;

    /**
     * @return ?array<string, string>
     */
    public function getParamsByRoutePattern(string $routePattern): ?array;

    public function getRouteIDByRoutePattern(string $routePattern): ?string;

    /**
     * @param array<string, string> $params
     */
    public function getRoutePatternByRouteID(string $routeID, ?array $params = null): ?string;
}
