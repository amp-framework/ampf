<?php

declare(strict_types=1);

namespace ampf\router;

use ampf\controller\Controller;
use ampf\requests\HttpRequest;

interface HttpRouter
{
    public function route(HttpRequest $request): self;

    /** @param ?array<string, string> $params */
    public function routeBean(Controller $controller, ?array $params = null): self;
}
