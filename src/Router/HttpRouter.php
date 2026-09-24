<?php

declare(strict_types=1);

namespace ampf\Router;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\Controller\ControllerInterface;
use ampf\Controller\ControllerInterruptedException;
use ampf\Request\HttpRequestInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * Asks the request for its controller bean and runs beforeAction(), execute() with the route's named captures as
 * named arguments, and afterAction(); a ControllerInterruptedException ends the lifecycle where it is thrown.
 */
class HttpRouter implements BeanFactoryAccessInterface, HttpRouterInterface
{
    use BeanFactoryAccess;

    public function route(HttpRequestInterface $request): self
    {
        $controller = $request->getController()
            ?? throw new RuntimeException('No route matches the request.');

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
     * @param ?array<string, string> $params the route's parameters, handed to execute() by their names
     */
    public function routeBean(ControllerInterface $controller, ?array $params = null): self
    {
        $params ??= [];
        $this->checkParameters($controller, $params);

        try {
            $controller->beforeAction();
            call_user_func_array([$controller, 'execute'], $params);
            $controller->afterAction();
        } catch (ControllerInterruptedException) {
            // The controller ended its lifecycle: nothing after the throw runs
        }

        return $this;
    }

    /**
     * Whether execute() takes the parameters by their names: each of them is a parameter of execute(), unless
     * execute() collects named arguments (`...$params`). ControllerInterface lets execute() require none.
     *
     * @param array<string, string> $params
     *
     * @throws RuntimeException naming the parameter execute() does not have
     */
    protected function checkParameters(ControllerInterface $controller, array $params): void
    {
        $names = [];

        foreach (new ReflectionMethod($controller, 'execute')->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                return;
            }

            $names[] = $parameter->getName();
        }

        foreach (array_keys($params) as $name) {
            if (!in_array($name, $names, true)) {
                throw new RuntimeException(
                    'The route\'s parameter ' . $name . ' names no parameter of ' . $controller::class . '::execute().',
                );
            }
        }
    }
}
