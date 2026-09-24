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
     * What the controller bean prints for the arguments, run on a request of its own (the bean 'RequestStub').
     *
     * @param ?array<int|string, string> $params
     *
     * @throws RuntimeException when the bean 'RequestStub' is no command line request, or the controller bean no
     *     controller
     */
    public function subRoute(string $controllerBean, ?array $params = null): string
    {
        $request = $this->getBeanFactory()->get('RequestStub');

        if (!$request instanceof CliRequestInterface) {
            throw new RuntimeException(
                'The bean RequestStub is no command line request, but ' . get_debug_type($request) . '.',
            );
        }

        $controller = $this->getBeanFactory()->get($controllerBean);

        if (!$controller instanceof ControllerInterface) {
            throw new RuntimeException(
                'The controller bean ' . $controllerBean . ' is no ' . ControllerInterface::class . ', but '
                . get_debug_type($controller) . '.',
            );
        }

        $controller->setRequest($request);
        $this->getRouter()->routeBean($controller, $params);

        return $this->capture($request->flush(...));
    }

    /**
     * @throws RuntimeException when the bean 'Router' is no command line router
     */
    public function getRouter(): CliRouterInterface
    {
        if ($this->router === null) {
            $router = $this->getBeanFactory()->get('Router');

            if (!$router instanceof CliRouterInterface) {
                throw new RuntimeException(
                    'The bean Router is no command line router, but ' . get_debug_type($router) . '.',
                );
            }

            $this->router = $router;
        }

        return $this->router;
    }

    public function setRouter(CliRouterInterface $router): void
    {
        $this->router = $router;
    }
}
