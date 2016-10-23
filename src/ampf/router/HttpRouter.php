<?php

namespace ampf\router;

use \ampf\requests\HttpRequest;
use \ampf\controller\Controller;

interface HttpRouter
{
	/**
	 * @param HttpRequest $request
	 * @return HttpRouter
	 * @throws \Exception
	 */
	public function route(HttpRequest $request);

	/**
	 * @param Controller $controller
	 * @param array $params
	 * @return HttpRouter
	 */
	public function routeBean(Controller $controller, array $params = null);
}
