<?php

declare(strict_types=1);

namespace ampf\router\impl;

use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\controller\Controller;
use ampf\exceptions\ControllerInterruptedException;
use ampf\requests\CliRequest;
use ampf\router\CliRouter;
use RuntimeException;

class DefaultCliRouter implements BeanFactoryAccess, CliRouter
{
    use DefaultBeanFactoryAccess;

    public function route(CliRequest $request): self
    {
        $controller = $request->getController();
        if (!$this->getBeanFactory()->has($controller)) {
            throw new RuntimeException();
        }

        $params = $request->getRouteParams();
        if ($params === null) {
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
