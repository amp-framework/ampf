<?php

namespace ampf\router;

interface RouteResolver
{
	/**
	 * @param string $routePattern
	 * @return string
	 */
	public function getControllerByRoutePattern(string $routePattern);

	/**
	 * @param string $routeID
	 * @param array $params
	 * @return array
	 */
	public function getNotDefinedParams(string $routeID, array $params);

	/**
	 * @param string $routePattern
	 * @return array
	 */
	public function getParamsByRoutePattern(string $routePattern);

	/**
	 * @param string $routePattern
	 * @return string
	 */
	public function getRouteIDByRoutePattern(string $routePattern);

	/**
	 * @param string $routeID
	 * @param array $params
	 * @return string
	 */
	public function getRoutePatternByRouteID(string $routeID, array $params = null);
}
