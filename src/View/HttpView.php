<?php

declare(strict_types=1);

namespace ampf\View;

use ampf\Controller\ControllerInterface;
use ampf\Request\HttpRequestInterface;
use ampf\Router\HttpRouterInterface;
use Exception;
use RuntimeException;

/** The HTML view: escaping for HTML, links through the request, sub-requests through the router. */
class HttpView extends AbstractView implements HttpViewInterface
{
    protected ?HttpRequestInterface $request = null;

    protected ?HttpRouterInterface $router = null;

    public function escape(mixed $string): string
    {
        if (!is_scalar($string)) {
            throw new RuntimeException();
        }

        // @phpcs:ignore SlevomatCodingStandard.Functions.RequireSingleLineCall.RequiredSingleLineCall
        return htmlspecialchars(
            (string)$string,
            (ENT_QUOTES | ENT_HTML5),
            'UTF-8',
        );
    }

    public function getAssetLink(string $relativeLink): string
    {
        if (trim($relativeLink) === '') {
            throw new RuntimeException();
        }

        $relativeLink = $this->solveSymbolicPath($relativeLink);

        return $this->getRequest()->getLink($relativeLink);
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

    public function getRequest(): HttpRequestInterface
    {
        if ($this->request === null) {
            $request = $this->getBeanFactory()->get('Request');
            assert($request instanceof HttpRequestInterface);
            $this->setRequest($request);
        }

        if ($this->request === null) {
            throw new RuntimeException();
        }

        return $this->request;
    }

    public function setRequest(HttpRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function getRouter(): HttpRouterInterface
    {
        if ($this->router === null) {
            $router = $this->getBeanFactory()->get('Router');
            assert($router instanceof HttpRouterInterface);
            $this->setRouter($router);
        }

        if ($this->router === null) {
            throw new RuntimeException();
        }

        return $this->router;
    }

    public function setRouter(HttpRouterInterface $router): void
    {
        $this->router = $router;
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
        assert($request instanceof HttpRequestInterface);
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

    protected function solveSymbolicPath(string $path): string
    {
        // strip of trailing slashes
        $path = trim($path, '/');

        // explode for slashes
        $array = explode('/', $path);

        // this will hold the path result
        $result = [];

        /** @phpcs:disable SlevomatCodingStandard.ControlStructures.EarlyExit.UselessElseIf */
        foreach ($array as $value) {
            if ($value === '') {
                continue;
            } elseif ($value === '.') {
                continue;
            } elseif ($value === '..' && count($result) === 0) {
                throw new Exception();
            } elseif (str_starts_with($value, '..')) {
                array_pop($result);
            } else {
                $result[] = $value;
            }
        }
        /** @phpcs:enable SlevomatCodingStandard.ControlStructures.EarlyExit.UselessElseIf */

        // return it
        return implode('/', $result);
    }
}
