<?php

namespace ampf\views\impl;

use ampf\router\CliRouter;
use ampf\views\CliView;
use ampf\views\AbstractView;

class DefaultCliView extends AbstractView implements CliView
{
	protected $_router = null;

	public function escape($string)
	{
		return $string;
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

	// Bean getters

	public function getRouter()
	{
		if (is_null($this->_router))
		{
			$this->setRouter($this->getBeanFactory()->get('Router'));
		}
		return $this->_router;
	}

	// Bean setters

	public function setRouter(CliRouter $router)
	{
		$this->_router = $router;
	}
}
