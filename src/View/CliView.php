<?php

declare(strict_types=1);

namespace ampf\View;

use ampf\Controller\ControllerInterface;
use ampf\Request\CliRequestInterface;
use ampf\Router\CliRouterInterface;
use RuntimeException;

/** The terminal's view: text is printed as it is (escape() changes nothing). */
class CliView extends AbstractView implements CliViewInterface
{
    protected ?CliRouterInterface $router = null;

    public function escape(string $string): string
    {
        return $string;
    }

    /**
     * @param array<string, string> $params
     */
    public function subRoute(string $controllerBean, ?array $params = null): string
    {
        if ($params === null) {
            $params = [];
        }

        // get a stub request
        $request = $this->getBeanFactory()->get('RequestStub');
        assert($request instanceof CliRequestInterface);
        // get the controller bean and inject the request
        $controller = $this->getBeanFactory()->get($controllerBean);
        assert($controller instanceof ControllerInterface);
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

    public function getRouter(): CliRouterInterface
    {
        if ($this->router === null) {
            $router = $this->getBeanFactory()->get('Router');
            assert($router instanceof CliRouterInterface);
            $this->setRouter($router);
        }

        if ($this->router === null) {
            throw new RuntimeException();
        }

        return $this->router;
    }

    // Bean setters

    public function setRouter(CliRouterInterface $router): void
    {
        $this->router = $router;
    }
}
