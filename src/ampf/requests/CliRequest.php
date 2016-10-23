<?php

namespace ampf\requests;

interface CliRequest
{
	/**
	 * @return string
	 */
	public function getController();

	/**
	 * @return array
	 */
	public function getRouteParams();

	/**
	 * @param string $routeID
	 * @return string
	 */
	public function getCmd(string $routeID);

	/**
	 * @param string $routeID
	 * @param array $params
	 * @return string
	 */
	public function getActionCmd(string $routeID, array $params = null);

	/**
	 * @param string $response
	 * @return CliRequest
	 */
	public function setResponse(string $response);

	/**
	 * @return CliRequest
	 */
	public function flush();
}
