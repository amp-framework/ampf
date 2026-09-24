<?php

declare(strict_types=1);

namespace ampf\Controller\Http;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\Controller\ControllerInterface;
use ampf\Request\CliRequestInterface;
use ampf\Request\HttpRequestInterface;
use ampf\View\HttpViewInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * The base of a web controller: the request and a view (the beans 'Request' and 'View', fetched at their first use),
 * and lifecycle hooks that do nothing. A controller implements execute(), which takes the route's named captures by
 * their names.
 */
abstract class AbstractController implements BeanFactoryAccessInterface, ControllerInterface
{
    use BeanFactoryAccess;

    protected ?HttpRequestInterface $request = null;

    protected ?HttpViewInterface $view = null;

    public function beforeAction(): void
    {
        // Nothing to prepare; a controller that has something overrides it
    }

    public function afterAction(): void
    {
        // Nothing to wrap around the response; a controller that has something overrides it
    }

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

    /**
     * @throws InvalidArgumentException for a command line request
     */
    public function setRequest(CliRequestInterface|HttpRequestInterface $request): void
    {
        if (!$request instanceof HttpRequestInterface) {
            throw new InvalidArgumentException('A web controller takes a web request, not ' . $request::class . '.');
        }

        $this->request = $request;
    }

    public function getView(): HttpViewInterface
    {
        if ($this->view === null) {
            $view = $this->getBeanFactory()->get('View');

            if (!$view instanceof HttpViewInterface) {
                throw new RuntimeException('The bean View is no web view, but ' . get_debug_type($view) . '.');
            }

            $this->view = $view;
        }

        return $this->view;
    }

    public function setView(HttpViewInterface $view): void
    {
        $this->view = $view;
    }
}
