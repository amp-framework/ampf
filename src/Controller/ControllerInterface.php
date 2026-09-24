<?php

declare(strict_types=1);

namespace ampf\Controller;

use ampf\Request\CliRequestInterface;
use ampf\Request\HttpRequestInterface;

/**
 * A controller bean: the router runs beforeAction(), execute() with the route's parameters, then afterAction(). On the
 * web, execute() takes a route's named captures by their names (an optional parameter each); on the command line, the
 * arguments after the route in their order. The controller prepares the response on its request; the entry point
 * flushes it.
 */
interface ControllerInterface
{
    /**
     * Will be executed before the given action.
     * Can be used to e.g. do some access-checks.
     *
     * Should throw \ampf\Controller\ControllerInterruptedException In case the action should not be executed
     */
    public function beforeAction(): void;

    /**
     * Will be executed after the given action.
     * Can be used to e.g. set a surrounding layout around the response.
     */
    public function afterAction(): void;

    /** Execute the given action. The main logic of a controller goes here. */
    public function execute(): void;

    public function setRequest(CliRequestInterface|HttpRequestInterface $request): void;
}
