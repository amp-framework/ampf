<?php

namespace ampf\router\impl;

use ampf\router\RouteResolver;

class DefaultRouteResolver implements RouteResolver
{
	use \ampf\beans\access\BeanFactoryAccess;

	protected $_config = null;

	/**
	 * Implementing the interface
	 */

	public function getRoutePatternByRouteID($routeID, $params = null)
	{
		$routePattern = $this->getRoutePattern($routeID);
		if ($routePattern === null)
		{
			return null;
		}

		if ($params == null || !is_array($params))
		{
			$params = array();
		}
		return $this->getAdjustedRouteParams($routePattern, $params)['route'];
	}

	public function getNotDefinedParams($routeID, $params)
	{
		$routePattern = $this->getRoutePattern($routeID);
		if ($routePattern === null)
		{
			return null;
		}

		if ($params === null || !is_array($params))
		{
			$params = array();
		}
		return $this->getAdjustedRouteParams($routePattern, $params)['notUsedParams'];
	}

	public function getRouteIDByRoutePattern($routePattern)
	{
		$array = $this->getControllerParamsByRoutePattern($routePattern);
		if (is_null($array)) return null;
		return $array[0];
	}

	public function getControllerByRoutePattern($routePattern)
	{
		$array = $this->getControllerParamsByRoutePattern($routePattern);
		if (is_null($array)) return null;
		return $array[1];
	}

	public function getParamsByRoutePattern($routePattern)
	{
		$array = $this->getControllerParamsByRoutePattern($routePattern);
		if (is_null($array)) return null;
		return $array[2];
	}

	/**
	 * Protected methods
	 */

	protected function getControllerParamsByRoutePattern($routePattern)
	{
		foreach ($this->getConfig() as $routeID => $value)
		{
			$preg = ('/^' . str_replace('/', '\/', $value['pattern']) . '$/');
			$matches = array();
			if (preg_match($preg, $routePattern, $matches))
			{
				$matches = $this->cleanMatches($matches, $this->getRouteParams($value['pattern']));

				return array($routeID, $value['controller'], $matches);
			}
		}

		return null;
	}

	protected function cleanMatches($matches, $allowedParams)
	{
		$result = array();
		foreach ($matches as $key => $value)
		{
			if (in_array($key, $allowedParams, true))
			{
				$result[$key] = $value;
			}
		}
		return $result;
	}

	protected function getAdjustedRouteParams($regex, $params = null)
	{
		if ($params === null || !is_array($params))
		{
			$params = array();
		}
		$matches = array();
		$catch = '/\(\?P\<(.+)\>[^\)]+\)/';
		preg_match_all($catch, $regex, $matches, PREG_SET_ORDER);
		foreach ($matches as $match)
		{
			$search = $match[0];
			if (!isset($params[$match[1]])) throw new \Exception("Missing parameter " . $match[1]);
			$replace = $params[$match[1]];
			unset ($params[$match[1]]);
			$regex = str_replace($search, $replace, $regex);
		}

		return array('route' => $regex, 'notUsedParams' => $params);
	}

	protected function getRouteParams($regex)
	{
		$matches = array();
		$catch = '/\(\?P\<(.+)\>[^\)]+\)/';
		preg_match_all($catch, $regex, $matches, PREG_PATTERN_ORDER);
		return $matches[1];
	}

	protected function getRoutePattern($routeID)
	{
		if (!is_scalar($routeID)) throw new \Exception();
		$routeID = ((string)$routeID);
		if (trim($routeID) == '') throw new \Exception();

		$routePattern = null;
		foreach ($this->getConfig() as $_routeID => $value)
		{
			if ($_routeID === $routeID)
			{
				$routePattern = $value['pattern'];
				break 1;
			}
		}

		return $routePattern;
	}

	// Bean getters

	public function getConfig()
	{
		if (is_null($this->_config))
		{
			$this->setConfig($this->getBeanFactory()->get('Config'));
		}
		return $this->_config;
	}

	// Bean setters

	public function setConfig($config)
	{
		if (!is_array($config) || !isset($config['routes'])) throw new \Exception();
		$config = $config['routes'];
		if (!is_array($config) || count($config) < 1) throw new \Exception();

		$correctKeys = array('pattern', 'controller');

		foreach ($config as $key => $value)
		{
			if (!is_string($key) || trim($key) == '') throw new \Exception();
			$diff = array_diff(array_keys($value), $correctKeys);
			if (count($diff) != 0) throw new \Exception();
		}

		$this->_config = $config;
	}
}
