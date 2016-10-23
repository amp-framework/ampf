<?php

namespace ampf\router;

use \ampf\requests\CliRequest;
use \ampf\controller\Controller;

interface CliRouter
{
	/**
	 * @param CliRequest $request
	 * @return CliRouter
	 * @throws \Exception
	 */
	public function route(CliRequest $request);

	/**
	 * @param Controller $controller
	 * @param array $params
	 * @return CliRouter
	 */
	public function routeBean(Controller $controller, array $params = null);
}
