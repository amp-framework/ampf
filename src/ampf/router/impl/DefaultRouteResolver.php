<?php

namespace ampf\router\impl;

use \ampf\beans\BeanFactoryAccess;
use \ampf\router\RouteResolver;

class DefaultRouteResolver implements BeanFactoryAccess, RouteResolver
{
	use \ampf\beans\impl\DefaultBeanFactoryAccess;

	/**
	 * @var array
	 */
	protected $_config = null;

	/**
	 * @param string $routePattern
	 * @return string
	 */
	public function getControllerByRoutePattern(string $routePattern)
	{
		$array = $this->getControllerParamsByRoutePattern($routePattern);
		if (is_null($array)) return null;
		return $array[1];
	}

	/**
	 * @param string $routeID
	 * @param array $params
	 * @return array
	 */
	public function getNotDefinedParams(string $routeID, array $params)
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

	/**
	 * @param string $routePattern
	 * @return array
	 */
	public function getParamsByRoutePattern(string $routePattern)
	{
		$array = $this->getControllerParamsByRoutePattern($routePattern);
		if (is_null($array)) return null;
		return $array[2];
	}

	/**
	 * @param string $routePattern
	 * @return string
	 */
	public function getRouteIDByRoutePattern(string $routePattern)
	{
		$array = $this->getControllerParamsByRoutePattern($routePattern);
		if (is_null($array)) return null;
		return $array[0];
	}

	/**
	 * @param string $routeID
	 * @param array $params
	 * @return string
	 */
	public function getRoutePatternByRouteID(string $routeID, array $params = null)
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

	/**
	 * Protected methods
	 */

	/**
	 * @param string $routePattern
	 * @return array
	 */
	protected function getControllerParamsByRoutePattern(string $routePattern)
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

	/**
	 * @param array $matches
	 * @param array $allowedParams
	 * @return array
	 */
	protected function cleanMatches(array $matches, array $allowedParams)
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

	/**
	 * @param string $regex
	 * @param array $params
	 * @return array
	 * @throws \Exception
	 */
	protected function getAdjustedRouteParams(string $regex, array $params = null)
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

	/**
	 * @param string $regex
	 * @return array
	 */
	protected function getRouteParams(string $regex)
	{
		$matches = array();
		$catch = '/\(\?P\<(.+)\>[^\)]+\)/';
		preg_match_all($catch, $regex, $matches, PREG_PATTERN_ORDER);
		return $matches[1];
	}

	/**
	 * @param string $routeID
	 * @return string
	 * @throws \Exception
	 */
	protected function getRoutePattern(string $routeID)
	{
		if (trim($routeID) == '') throw new \Exception();

		foreach ($this->getConfig() as $_routeID => $value)
		{
			if ($_routeID === $routeID)
			{
				return $value['pattern'];
			}
		}

		return null;
	}

	// Bean getters

	/**
	 * @return array
	 */
	public function getConfig()
	{
		if (is_null($this->_config))
		{
			$this->setConfig($this->getBeanFactory()->get('Config'));
		}
		return $this->_config;
	}

	// Bean setters

	/**
	 * @param array $config
	 * @return RouteResolver
	 * @throws \Exception
	 */
	public function setConfig(array $config)
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
		return $this;
	}
}
