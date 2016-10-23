<?php

namespace ampf\router\impl;

use \ampf\beans\BeanFactoryAccess;
use \ampf\router\CliRouter;
use \ampf\requests\CliRequest;
use \ampf\controller\Controller;
use \ampf\exceptions\ControllerInterruptedException;

class DefaultCliRouter implements BeanFactoryAccess, CliRouter
{
	use \ampf\beans\impl\DefaultBeanFactoryAccess;

	/**
	 * @param CliRequest $request
	 * @return CliRouter
	 * @throws \Exception
	 */
	public function route(CliRequest $request)
	{
		$controller = $request->getController();
		if (!$this->getBeanFactory()->has($controller)) throw new \Exception();

		$params = $request->getRouteParams();
		if (!is_array($params) || count($params) < 1) $params = array();

		$bean = $this->getBeanFactory()->get($controller);
		$this->routeBean($bean, $params);

		return $this;
	}

	/**
	 * @param Controller $controller
	 * @param array $params
	 * @return CliRouter
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
