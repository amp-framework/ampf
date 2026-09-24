<?php

declare(strict_types=1);

namespace ampf\Router;

use ampf\Controller\ControllerInterface;
use ampf\Request\HttpRequestInterface;
use RuntimeException;

/** Runs the controller bean an HTTP request's route names, through its whole lifecycle. */
interface HttpRouterInterface
{
    /**
     * @throws RuntimeException when no route matches, or its controller bean is missing or no controller
     */
    public function route(HttpRequestInterface $request): self;

    /**
     * Runs the controller's lifecycle with the parameters handed to execute() by their names.
     *
     * @param ?array<string, string> $params
     *
     * @throws RuntimeException when a parameter names no parameter of execute()
     */
    public function routeBean(ControllerInterface $controller, ?array $params = null): self;
}
