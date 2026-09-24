<?php

declare(strict_types=1);

namespace ampf\Router;

use ampf\Controller\ControllerInterface;
use ampf\Request\HttpRequestInterface;

/** Runs the controller bean an HTTP request's route names, through its whole lifecycle. */
interface HttpRouterInterface
{
    public function route(HttpRequestInterface $request): self;

    /**
     * @param ?array<string, string> $params
     */
    public function routeBean(ControllerInterface $controller, ?array $params = null): self;
}
