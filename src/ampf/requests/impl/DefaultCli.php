<?php

namespace ampf\requests\impl;

use \ampf\beans\BeanFactoryAccess;
use \ampf\requests\CliRequest;

class DefaultCli implements BeanFactoryAccess, CliRequest
{
	use \ampf\beans\impl\DefaultBeanFactoryAccess;
	use \ampf\beans\access\RouteResolverAccess;

	/**
	 * @var array
	 */
	protected $argv = null;

	/**
	 * @var string
	 */
	protected $responseBody = null;

	public function __construct()
	{
		$this->argv = $GLOBALS['argv'];
	}

	/**
	 * @return string
	 */
	public function getController()
	{
		if (!isset($this->argv[1]) || empty($this->argv[1])) $arg = '*';
		else $arg = $this->argv[1];

		return $this->getRouteResolver()->getControllerByRoutePattern($arg);
	}

	/**
	 * @return array
	 */
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

	/**
	 * @param string $routeID
	 * @return string
	 */
	public function getCmd(string $routeID)
	{
		return ($this->argv[0] . ' ' . $routeID);
	}

	/**
	 * @param string $routeID
	 * @param array $params
	 * @return string
	 */
	public function getActionCmd(string $routeID, array $params = null)
	{
		if (is_null($params)) $params = array();

		$routeID = $this->getRouteResolver()->getRoutePatternByRouteID($routeID, $params);
		return $this->getCmd($routeID);
	}

	/**
	 * @param string $response
	 * @return CliRequest
	 */
	public function setResponse(string $response)
	{
		$this->responseBody = $response;
		return $this;
	}

	/**
	 * @return CliRequest
	 */
	public function flush()
	{
		echo $this->responseBody;
		$this->responseBody = null;
		return $this;
	}
}
