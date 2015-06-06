<?php

namespace ampf\router\impl;

use ampf\router\CliRouter;
use ampf\requests\CliRequest;
use ampf\controller\Controller;

class DefaultCliRouter implements CliRouter
{
	use \ampf\beans\access\BeanFactoryAccess;

	public function route(CliRequest $request)
	{
		$controller = $request->getController();
		if (!$this->getBeanFactory()->has($controller)) throw new \Exception();

		$params = $request->getRouteParams();
		if (!is_array($params) || count($params) < 1) $params = array();

		$bean = $this->getBeanFactory()->get($controller);
		$this->routeBean($bean, $params);
	}

	public function routeBean(Controller $controller, $params = null)
	{
		if (is_null($params)) $params = array();

		$controller->beforeAction();
		call_user_func_array(array($controller, 'execute'), $params);
		$controller->afterAction();
	}
}
