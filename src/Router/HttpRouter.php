<?php

declare(strict_types=1);

namespace ampf\Router;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\Controller\ControllerInterface;
use ampf\Controller\ControllerInterruptedException;
use ampf\Request\HttpRequestInterface;
use RuntimeException;

/**
 * Asks the request for its controller bean and runs beforeAction(), execute() with the route's named parameters in
 * their order, and afterAction(); a ControllerInterruptedException ends the lifecycle where it is thrown.
 */
class HttpRouter implements BeanFactoryAccessInterface, HttpRouterInterface
{
    use BeanFactoryAccess;

    public function route(HttpRequestInterface $request): self
    {
        $controller = $request->getController();

        if ($controller === null) {
            throw new RuntimeException();
        }

        if (!$this->getBeanFactory()->has($controller)) {
            throw new RuntimeException("Controllerbean {$controller} is not known.");
        }

        $params = $request->getRouteParams();

        if (!is_array($params) || count($params) < 1) {
            $params = [];
        }

        $bean = $this->getBeanFactory()->get($controller);

        if (!($bean instanceof ControllerInterface)) {
            throw new RuntimeException();
        }

        $this->routeBean($bean, $params);

        return $this;
    }

    /**
     * @param array<string, string> $params
     */
    public function routeBean(ControllerInterface $controller, ?array $params = null): self
    {
        if ($params === null) {
            $params = [];
        }

        try {
            $controller->beforeAction();

            /**
             * @TODO This currently doesn't honor parameter names: We have the parameter names in $params
             * (in form of ['param1' => 'value1', ...], but call_user_func_array() just expects an array<int, mixed>
             * input for the parameters in the correct order by the method definition. Probably should be refactored
             * here fundamentally to support named parameters, but is a job for later.
             */
            call_user_func_array([$controller, 'execute'], array_values($params));

            $controller->afterAction();
        } catch (ControllerInterruptedException) {
            // do nothing.
        }

        return $this;
    }
}
