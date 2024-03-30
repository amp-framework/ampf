<?php

declare(strict_types=1);

namespace ampf\views\impl;

use ampf\controller\Controller;
use ampf\requests\HttpRequest;
use ampf\router\HttpRouter;
use ampf\views\AbstractView;
use ampf\views\HttpView;
use Exception;
use RuntimeException;

class DefaultHttpView extends AbstractView implements HttpView
{
    protected ?HttpRequest $_request = null;

    protected ?HttpRouter $_router = null;

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
        if (!is_string($relativeLink) || trim($relativeLink) === '') {
            throw new RuntimeException();
        }

        $relativeLink = $this->solveSymbolicPath($relativeLink);

        return $this->getRequest()->getLink($relativeLink);
    }

    /**
     * @param array<string, string> $params
     */
    public function getActionLink(string $routeID, ?array $params = null, bool $addToken = false): string
    {
        if ($params === null) {
            $params = [];
        }

        return $this->getRequest()->getActionLink($routeID, $params, $addToken);
    }

    public function getRequest(): HttpRequest
    {
        if ($this->_request === null) {
            $request = $this->getBeanFactory()->get('Request');
            assert($request instanceof HttpRequest);
            $this->setRequest($request);
        }

        if ($this->_request === null) {
            throw new RuntimeException();
        }

        return $this->_request;
    }

    public function setRequest(HttpRequest $request): void
    {
        $this->_request = $request;
    }

    public function getRouter(): HttpRouter
    {
        if ($this->_router === null) {
            $router = $this->getBeanFactory()->get('Router');
            assert($router instanceof HttpRouter);
            $this->setRouter($router);
        }

        if ($this->_router === null) {
            throw new RuntimeException();
        }

        return $this->_router;
    }

    public function setRouter(HttpRouter $router): void
    {
        $this->_router = $router;
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
        assert($request instanceof HttpRequest);
        // get the controller bean and inject the request
        $controller = $this->getBeanFactory()->get($controllerBean);
        assert($controller instanceof Controller);
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

        // return it
        return implode('/', $result);
    }

    /**
     * Protected methods
     */

    protected function getParam(string $param): mixed
    {
        if ($this->getRequest()->hasPostParam($param)) {
            return $this->getRequest()->getPostParam($param);
        } elseif ($this->getRequest()->hasGetParam($param)) {
            return $this->getRequest()->getGetParam($param);
        }

        return '';
    }
}
