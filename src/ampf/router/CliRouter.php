<?php

declare(strict_types=1);

namespace ampf\router;

use ampf\controller\Controller;
use ampf\requests\CliRequest;

interface CliRouter
{
    public function route(CliRequest $request): self;

    /** @param ?array<string, string> $params */
    public function routeBean(Controller $controller, ?array $params = null): self;
}
