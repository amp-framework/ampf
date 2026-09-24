<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Controller\Cli;

use ampf\Bean\BeanFactory;
use ampf\Controller\Cli\AbstractController;
use ampf\Request\CliRequest;
use ampf\Router\CliRouter;
use ampf\Tests\Support\Controller\GreetingCliController;
use ampf\Tests\Support\RecordingHttpRequest;
use ampf\View\CliView;
use ampf\View\HttpView;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(AbstractController::class)]
final class AbstractControllerTest extends TestCase
{
    public function testTheRequestIsTheBeanRequestFetchedAtItsFirstUse(): void
    {
        $request = new CliRequest(['bin/index.php']);
        $beanFactory = $this->beanFactory(['Request' => $request]);
        $controller = $this->controller($beanFactory);

        self::assertSame($request, $controller->getRequest());

        $beanFactory->set('Request', new CliRequest(['bin/other.php']));
        self::assertSame($request, $controller->getRequest(), 'the controller keeps its request');
    }

    public function testAGivenRequestIsTheControllers(): void
    {
        $controller = $this->controller($this->beanFactory([]));
        $request = new CliRequest(['bin/index.php']);

        $controller->setRequest($request);

        self::assertSame($request, $controller->getRequest());
    }

    public function testAWebRequestIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'A command line controller takes a command line request, not ' . RecordingHttpRequest::class . '.',
        );

        $this->controller($this->beanFactory([]))->setRequest(new RecordingHttpRequest());
    }

    public function testABeanRequestOfTheWebIsRefused(): void
    {
        $controller = $this->controller($this->beanFactory(['Request' => new RecordingHttpRequest()]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The bean Request is no command line request, but ' . RecordingHttpRequest::class . '.',
        );

        $controller->getRequest();
    }

    public function testTheViewIsTheBeanViewFetchedAtItsFirstUse(): void
    {
        $view = new CliView();
        $beanFactory = $this->beanFactory(['View' => $view]);
        $controller = $this->controller($beanFactory);

        self::assertSame($view, $controller->getView());

        $beanFactory->set('View', new CliView());
        self::assertSame($view, $controller->getView(), 'the controller keeps its view');
    }

    public function testAGivenViewIsTheControllers(): void
    {
        $controller = $this->controller($this->beanFactory([]));
        $view = new CliView();

        $controller->setView($view);

        self::assertSame($view, $controller->getView());
    }

    public function testABeanViewOfTheWebIsRefused(): void
    {
        $controller = $this->controller($this->beanFactory(['View' => new HttpView()]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The bean View is no command line view, but ' . HttpView::class . '.');

        $controller->getView();
    }

    public function testThroughTheRouterTheControllerAnswersOnItsRequest(): void
    {
        $request = new CliRequest(['bin/index.php', 'greet', 'world']);
        $controller = $this->controller($this->beanFactory(['Request' => $request]));

        new CliRouter()->routeBean($controller, $request->getRouteParams());

        $this->expectOutputString('Hello world' . PHP_EOL);
        $request->flush();
    }

    /**
     * @param array<string, object> $beans
     */
    private function beanFactory(array $beans): BeanFactory
    {
        $beanFactory = new BeanFactory([]);

        foreach ($beans as $beanID => $bean) {
            $beanFactory->set($beanID, $bean);
        }

        return $beanFactory;
    }

    private function controller(BeanFactory $beanFactory): GreetingCliController
    {
        $controller = new GreetingCliController();
        $controller->setBeanFactory($beanFactory);

        return $controller;
    }
}
