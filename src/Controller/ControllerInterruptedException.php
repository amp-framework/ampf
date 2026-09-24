<?php

declare(strict_types=1);

namespace ampf\Controller;

use Exception;

/**
 * Ends a controller's lifecycle where it is thrown (a login guard in beforeAction(), say): the router catches it, and
 * neither execute() nor afterAction() runs after it. The request keeps what was set before (a redirect, a body).
 */
class ControllerInterruptedException extends Exception
{
}
