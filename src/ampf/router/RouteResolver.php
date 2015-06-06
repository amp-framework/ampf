<?php

namespace ampf\router;

interface RouteResolver
{
	public function getRoutePatternByRouteID($routeID, $params = null);

	public function getRouteIDByRoutePattern($routePattern);

	public function getControllerByRoutePattern($routePattern);

	public function getParamsByRoutePattern($routePattern);

	public function getNotDefinedParams($routeID, $params);
}
