<?php

namespace ampf\router;

use ampf\requests\HttpRequest;
use ampf\controller\Controller;

interface HttpRouter
{
	public function route(HttpRequest $request);

	public function routeBean(Controller $controller, $params);
}
