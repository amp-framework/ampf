<?php

declare(strict_types=1);

namespace ampf\View;

use ampf\Controller\ControllerInterface;
use ampf\Request\HttpRequestInterface;
use ampf\Router\HttpRouterInterface;
use RuntimeException;

/** The HTML view: escaping for HTML, links through the request, sub-requests through the router. */
class HttpView extends AbstractView implements HttpViewInterface
{
    protected ?HttpRequestInterface $request = null;

    protected ?HttpRouterInterface $router = null;

    /**
     * @throws RuntimeException for a value that is no scalar
     */
    public function escape(mixed $string): string
    {
        if (!is_scalar($string)) {
            throw new RuntimeException('Only a scalar can be escaped for HTML, not ' . get_debug_type($string) . '.');
        }

        return htmlspecialchars((string)$string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * The link of a file under the web root: "." and ".." are resolved first, and no link leaves the web root.
     *
     * @throws RuntimeException for a blank path, and for a path that leaves the web root
     */
    public function getAssetLink(string $relativeLink): string
    {
        if (trim($relativeLink) === '') {
            throw new RuntimeException('An asset link needs the path of the asset.');
        }

        return $this->getRequest()->getLink($this->solveSymbolicPath($relativeLink));
    }

    /**
     * @param ?array<string, scalar|null> $params
     */
    public function getActionLink(string $routeID, ?array $params = null, bool $addToken = false): string
    {
        return $this->getRequest()->getActionLink($routeID, $params ?? [], $addToken);
    }

    public function getParamString(string $name): string
    {
        return $this->getRequest()->getParamString($name);
    }

    /**
     * @throws RuntimeException when the bean 'Request' is no web request
     */
    public function getRequest(): HttpRequestInterface
    {
        if ($this->request === null) {
            $request = $this->getBeanFactory()->get('Request');

            if (!$request instanceof HttpRequestInterface) {
                throw new RuntimeException('The bean Request is no web request, but ' . get_debug_type($request) . '.');
            }

            $this->request = $request;
        }

        return $this->request;
    }

    public function setRequest(HttpRequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * @throws RuntimeException when the bean 'Router' is no web router
     */
    public function getRouter(): HttpRouterInterface
    {
        if ($this->router === null) {
            $router = $this->getBeanFactory()->get('Router');

            if (!$router instanceof HttpRouterInterface) {
                throw new RuntimeException('The bean Router is no web router, but ' . get_debug_type($router) . '.');
            }

            $this->router = $router;
        }

        return $this->router;
    }

    public function setRouter(HttpRouterInterface $router): void
    {
        $this->router = $router;
    }

    /**
     * What the controller bean responds to the parameters, run on a request of its own (the bean 'RequestStub'):
     * execute() takes them by their names.
     *
     * @param ?array<string, string> $params
     *
     * @throws RuntimeException when the bean 'RequestStub' is no web request, or the controller bean no controller
     */
    public function subRoute(string $controllerBean, ?array $params = null): string
    {
        $request = $this->getBeanFactory()->get('RequestStub');

        if (!$request instanceof HttpRequestInterface) {
            throw new RuntimeException('The bean RequestStub is no web request, but ' . get_debug_type($request) . '.');
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
     * The path with its "." and ".." segments resolved, and without empty ones.
     *
     * @throws RuntimeException for a path whose ".." leaves the web root
     */
    protected function solveSymbolicPath(string $path): string
    {
        $result = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                if ($result === []) {
                    throw new RuntimeException('The asset path ' . $path . ' leaves the web root.');
                }

                array_pop($result);
            } elseif ($segment !== '' && $segment !== '.') {
                $result[] = $segment;
            }
        }

        return implode('/', $result);
    }
}
