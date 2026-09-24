<?php

declare(strict_types=1);

namespace ampf\Controller\Cli;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\Controller\ControllerInterface;
use ampf\Request\CliRequestInterface;
use ampf\Request\HttpRequestInterface;
use ampf\View\CliViewInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * The base of a command line controller: the command line and a view (the beans 'Request' and 'View', fetched at
 * their first use), and lifecycle hooks that do nothing. A controller implements execute(), which takes the route's
 * arguments in their order.
 */
abstract class AbstractController implements BeanFactoryAccessInterface, ControllerInterface
{
    use BeanFactoryAccess;

    protected ?CliRequestInterface $request = null;

    protected ?CliViewInterface $view = null;

    public function beforeAction(): void
    {
        // Nothing to prepare; a controller that has something overrides it
    }

    public function afterAction(): void
    {
        // Nothing to wrap around the response; a controller that has something overrides it
    }

    public function getRequest(): CliRequestInterface
    {
        if ($this->request === null) {
            $request = $this->getBeanFactory()->get('Request');

            if (!$request instanceof CliRequestInterface) {
                throw new RuntimeException(
                    'The bean Request is no command line request, but ' . get_debug_type($request) . '.',
                );
            }

            $this->request = $request;
        }

        return $this->request;
    }

    /**
     * @throws InvalidArgumentException for a request of the web
     */
    public function setRequest(CliRequestInterface|HttpRequestInterface $request): void
    {
        if (!$request instanceof CliRequestInterface) {
            throw new InvalidArgumentException(
                'A command line controller takes a command line request, not ' . $request::class . '.',
            );
        }

        $this->request = $request;
    }

    public function getView(): CliViewInterface
    {
        if ($this->view === null) {
            $view = $this->getBeanFactory()->get('View');

            if (!$view instanceof CliViewInterface) {
                throw new RuntimeException('The bean View is no command line view, but ' . get_debug_type($view) . '.');
            }

            $this->view = $view;
        }

        return $this->view;
    }

    public function setView(CliViewInterface $view): void
    {
        $this->view = $view;
    }
}
