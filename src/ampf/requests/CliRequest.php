<?php

namespace ampf\requests;

interface CliRequest
{
	public function getController();

	public function getRouteParams();

	public function getCmd($routeID);

	public function getActionCmd($routeID, $params = null);

	public function setResponse($response);

	public function flush();
}
