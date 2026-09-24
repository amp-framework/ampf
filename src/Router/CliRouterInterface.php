<?php

declare(strict_types=1);

namespace ampf\Router;

use ampf\Controller\ControllerInterface;
use ampf\Request\CliRequestInterface;
use RuntimeException;

/** Runs the controller bean a command line's route names, through its whole lifecycle. */
interface CliRouterInterface
{
    /**
     * @throws RuntimeException when no route matches, or its controller bean is missing or no controller
     */
    public function route(CliRequestInterface $request): self;

    /**
     * Runs the controller's lifecycle with the arguments handed to execute() in their order.
     *
     * @param ?array<int|string, string> $params
     */
    public function routeBean(ControllerInterface $controller, ?array $params = null): self;
}
