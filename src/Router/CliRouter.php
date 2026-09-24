<?php

declare(strict_types=1);

namespace ampf\Router;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\Controller\ControllerInterface;
use ampf\Controller\ControllerInterruptedException;
use ampf\Request\CliRequestInterface;
use RuntimeException;

/**
 * Asks the command line for its controller bean and runs beforeAction(), execute() with the remaining arguments in
 * their order, and afterAction(); a ControllerInterruptedException ends the lifecycle where it is thrown.
 */
class CliRouter implements BeanFactoryAccessInterface, CliRouterInterface
{
    use BeanFactoryAccess;

    public function route(CliRequestInterface $request): self
    {
        $controller = $request->getController();

        if (!$this->getBeanFactory()->has($controller)) {
            throw new RuntimeException('The controller bean ' . $controller . ' has no configuration.');
        }

        $bean = $this->getBeanFactory()->get($controller);

        if (!$bean instanceof ControllerInterface) {
            throw new RuntimeException(
                'The controller bean ' . $controller . ' is no ' . ControllerInterface::class . ', but '
                . get_debug_type($bean) . '.',
            );
        }

        return $this->routeBean($bean, $request->getRouteParams());
    }

    /**
     * @param ?array<int|string, string> $params the command line's arguments, handed to execute() in their order
     */
    public function routeBean(ControllerInterface $controller, ?array $params = null): self
    {
        try {
            $controller->beforeAction();
            call_user_func_array([$controller, 'execute'], array_values($params ?? []));
            $controller->afterAction();
        } catch (ControllerInterruptedException) {
            // The controller ended its lifecycle: nothing after the throw runs
        }

        return $this;
    }
}
