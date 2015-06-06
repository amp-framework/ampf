<?php

namespace ampf\router;

use ampf\requests\CliRequest;
use ampf\controller\Controller;

interface CliRouter
{
	public function route(CliRequest $request);

	public function routeBean(Controller $controller, $params);
}
