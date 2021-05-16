<?php

declare(strict_types=1);

namespace ampf\router\impl;

use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\controller\Controller;
use ampf\exceptions\ControllerInterruptedException;
use ampf\requests\HttpRequest;
use ampf\router\HttpRouter;
use RuntimeException;

class DefaultHttpRouter implements BeanFactoryAccess, HttpRouter
{
    use DefaultBeanFactoryAccess;

    public function route(HttpRequest $request): self
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
        if (!($bean instanceof Controller)) {
            throw new RuntimeException();
        }

        $this->routeBean($bean, $params);

        return $this;
    }

    public function routeBean(Controller $controller, ?array $params = null): self
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
