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
            throw new RuntimeException();
        }

        $params = $request->getRouteParams();

        $bean = $this->getBeanFactory()->get($controller);

        if (!($bean instanceof ControllerInterface)) {
            throw new RuntimeException();
        }

        $this->routeBean($bean, $params);

        return $this;
    }

    /**
     * @param array<int, string> $params
     */
    public function routeBean(ControllerInterface $controller, ?array $params = null): self
    {
        if ($params === null) {
            $params = [];
        }

        try {
            $controller->beforeAction();
            call_user_func_array([$controller, 'execute'], $params);
            $controller->afterAction();
        } catch (ControllerInterruptedException) {
            // do nothing.
        }

        return $this;
    }
}
