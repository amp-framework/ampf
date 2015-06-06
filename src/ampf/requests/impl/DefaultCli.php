<?php

namespace ampf\requests\impl;

use ampf\requests\CliRequest;

class DefaultCli implements CliRequest
{
	use \ampf\beans\access\BeanFactoryAccess;
	use \ampf\beans\access\RouteResolverAccess;

	protected $argv = null;
	protected $responseBody = null;

	public function __construct()
	{
		$this->argv = $GLOBALS['argv'];
	}

	public function getController()
	{
		if (!isset($this->argv[1]) || empty($this->argv[1])) $arg = '*';
		else $arg = $this->argv[1];

		return $this->getRouteResolver()->getControllerByRoutePattern($arg);
	}

	public function getRouteParams()
	{
		$arg = $this->argv;
		if (!is_array($arg) || count($arg) < 2) $arg = array();
		else
		{
			array_shift($arg);
			array_shift($arg);
		}
		return $arg;
	}

	public function getCmd($routeID)
	{
		$route = ($this->argv[0] . ' ' . $routeID);

		return $route;
	}

	public function getActionCmd($routeID, $params = null)
	{
		if (is_null($params)) $params = array();

		$routeID = $this->getRouteResolver()->getRoutePatternByRouteID($routeID, $params);
		$route = $this->getCmd($routeID);
		return $route;
	}

	public function setResponse($response)
	{
		$this->responseBody = $response;
	}

	public function flush()
	{
		echo $this->responseBody;
		$this->responseBody = null;
	}
}
