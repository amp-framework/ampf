<?php

namespace ampf\router\impl;

use \ampf\beans\BeanFactoryAccess;
use \ampf\router\HttpRouter;
use \ampf\requests\HttpRequest;
use \ampf\controller\Controller;
use \ampf\exceptions\ControllerInterruptedException;

class DefaultHttpRouter implements BeanFactoryAccess, HttpRouter
{
	use \ampf\beans\impl\DefaultBeanFactoryAccess;

	/**
	 * @param HttpRequest $request
	 * @return HttpRouter
	 * @throws \Exception
	 */
	public function route(HttpRequest $request)
	{
		$controller = $request->getController();
		if (is_null($controller)) throw new \Exception();
		if (!$this->getBeanFactory()->has($controller))
		{
			throw new \Exception("Controllerbean {$controller} is not known.");
		}

		$params = $request->getRouteParams();
		if (!is_array($params) || count($params) < 1) $params = array();

		$bean = $this->getBeanFactory()->get($controller);
		$this->routeBean($bean, $params);

		return $this;
	}

	/**
	 * @param Controller $controller
	 * @param array $params
	 * @return HttpRouter
	 */
	public function routeBean(Controller $controller, array $params = null)
	{
		if (is_null($params)) $params = array();

		try
		{
			$controller->beforeAction();
			call_user_func_array(array($controller, 'execute'), $params);
			$controller->afterAction();
		}
		catch (ControllerInterruptedException $e)
		{
			// do nothing.
		}
		return $this;
	}
}
