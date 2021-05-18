<?php

declare(strict_types=1);

namespace ampf\views\impl;

use ampf\router\CliRouter;
use ampf\views\AbstractView;
use ampf\views\CliView;
use RuntimeException;

class DefaultCliView extends AbstractView implements CliView
{
    protected ?CliRouter $_router = null;

    public function escape(mixed $string): string
    {
        return $string;
    }

    /** @param array<string, mixed> $params */
    public function subRoute(string $controllerBean, ?array $params = null): string
    {
        if ($params === null) {
            $params = [];
        }

        // get a stub request
        $request = $this->getBeanFactory()->get('RequestStub');
        // get the controller bean and inject the request
        $controller = $this->getBeanFactory()->get($controllerBean);
        $controller->setRequest($request);

        // route it
        $this->getRouter()->routeBean($controller, $params);

        // get the response
        ob_start();
        $request->flush();
        $result = ob_get_clean();

        // and, finally, return it
        if ($result === false) {
            throw new RuntimeException();
        }

        return $result;
    }

    // Bean getters

    public function getRouter(): CliRouter
    {
        if ($this->_router === null) {
            $this->setRouter($this->getBeanFactory()->get('Router'));
        }

        if ($this->_router === null) {
            throw new RuntimeException();
        }

        return $this->_router;
    }

    // Bean setters

    public function setRouter(CliRouter $router): void
    {
        $this->_router = $router;
    }
}
