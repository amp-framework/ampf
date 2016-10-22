<?php

namespace ampf\views\impl;

use ampf\requests\HttpRequest;
use ampf\router\HttpRouter;
use ampf\views\HttpView;
use ampf\views\AbstractView;

class DefaultHttpView extends AbstractView implements HttpView
{
	protected $_request = null;
	protected $_router = null;

	public function escape($string)
	{
		return htmlspecialchars(
			$string,
			(ENT_QUOTES | ENT_HTML5),
			'UTF-8'
		);
	}

	public function getAssetLink($relativeLink)
	{
		if (!is_string($relativeLink) || trim($relativeLink) == '') throw new \Exception();

		$relativeLink = $this->solveSymbolicPath($relativeLink);
		$route = $this->getRequest()->getLink($relativeLink);

		return $route;
	}

	public function getActionLink($routeID, $params = null, $addToken = false)
	{
		if (is_null($params)) $params = array();

		$route = $this->getRequest()->getActionLink($routeID, $params, $addToken);
		return $route;
	}

	public function subRoute($controllerBean, $params = null)
	{
		if (is_null($params)) $params = array();
		// get a stub request
		$request = $this->getBeanFactory()->get('RequestStub');
		// get the controller bean and inject the request
		$controller = $this->getBeanFactory()->get($controllerBean);
		$controller->setRequest($request);
		// route it
		$this->getRouter()->routeBean($controller, $params);
		// get the response
		ob_start();
		$request->flush();
		$response = ob_get_clean();
		// and, finally, return it
		return $response;
	}

	/**
	 * Protected methods
	 */

	protected function getParam($param)
	{
		if ($this->getRequest()->hasPostParam($param))
		{
			return $this->getRequest()->getPostParam($param);
		}
		elseif ($this->getRequest()->hasGetParam($param))
		{
			return $this->getRequest()->getGetParam($param);
		}
		return '';
	}

	protected function solveSymbolicPath($path)
	{
		// strip of trailing slashes
		$path = trim($path, '/');
		// explode for slashes
		$array = explode('/', $path);
		// this will hold the path result
		$result = array();
		foreach ($array as $value)
		{
			if ($value == '') continue;
			elseif ($value == '.') continue;
			elseif ($value == '..' && count($result) == 0) throw new \Exception();
			elseif (strpos($value, '..') === 0) array_pop($result);
			else $result[] = $value;
		}
		// return it
		return implode('/', $result);
	}

	// Bean getters

	/**
	 * @return \ampf\requests\HttpRequest;
	 */
	public function getRequest()
	{
		if (is_null($this->_request))
		{
			$this->setRequest($this->getBeanFactory()->get('Request'));
		}
		return $this->_request;
	}

	public function getRouter()
	{
		if (is_null($this->_router))
		{
			$this->setRouter($this->getBeanFactory()->get('Router'));
		}
		return $this->_router;
	}

	// Bean setters

	public function setRequest(HttpRequest $request)
	{
		$this->_request = $request;
	}

	public function setRouter(HttpRouter $router)
	{
		$this->_router = $router;
	}
}
