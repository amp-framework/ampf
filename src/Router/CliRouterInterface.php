<?php

declare(strict_types=1);

namespace ampf\Router;

use ampf\Controller\ControllerInterface;
use ampf\Request\CliRequestInterface;

/** Runs the controller bean a command line's route names, through its whole lifecycle. */
interface CliRouterInterface
{
    public function route(CliRequestInterface $request): self;

    /**
     * @param ?array<string, string> $params
     */
    public function routeBean(ControllerInterface $controller, ?array $params = null): self;
}
