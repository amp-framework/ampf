<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Controller\Http;

use ampf\Bean\BeanFactory;
use ampf\Controller\Http\AbstractController;
use ampf\Request\CliRequest;
use ampf\Router\HttpRouter;
use ampf\Tests\Support\Controller\GreetingHttpController;
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
        $request = new RecordingHttpRequest();
        $beanFactory = $this->beanFactory(['Request' => $request]);
        $controller = $this->controller($beanFactory);

        self::assertSame($request, $controller->getRequest());

        $beanFactory->set('Request', new RecordingHttpRequest());
        self::assertSame($request, $controller->getRequest(), 'the controller keeps its request');
    }

    public function testAGivenRequestIsTheControllers(): void
    {
        $controller = $this->controller($this->beanFactory([]));
        $request = new RecordingHttpRequest();

        $controller->setRequest($request);

        self::assertSame($request, $controller->getRequest());
    }

    public function testACommandLineRequestIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A web controller takes a web request, not ' . CliRequest::class . '.');

        $this->controller($this->beanFactory([]))->setRequest(new CliRequest(['bin/index.php']));
    }

    public function testABeanRequestOfTheCommandLineIsRefused(): void
    {
        $controller = $this->controller($this->beanFactory(['Request' => new CliRequest(['bin/index.php'])]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The bean Request is no web request, but ' . CliRequest::class . '.');

        $controller->getRequest();
    }

    public function testTheViewIsTheBeanViewFetchedAtItsFirstUse(): void
    {
        $view = new HttpView();
        $beanFactory = $this->beanFactory(['View' => $view]);
        $controller = $this->controller($beanFactory);

        self::assertSame($view, $controller->getView());

        $beanFactory->set('View', new HttpView());
        self::assertSame($view, $controller->getView(), 'the controller keeps its view');
    }

    public function testAGivenViewIsTheControllers(): void
    {
        $controller = $this->controller($this->beanFactory([]));
        $view = new HttpView();

        $controller->setView($view);

        self::assertSame($view, $controller->getView());
    }

    public function testABeanViewOfTheCommandLineIsRefused(): void
    {
        $controller = $this->controller($this->beanFactory(['View' => new CliView()]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The bean View is no web view, but ' . CliView::class . '.');

        $controller->getView();
    }

    public function testThroughTheRouterTheControllerAnswersOnItsRequest(): void
    {
        $request = new RecordingHttpRequest();
        $controller = $this->controller($this->beanFactory(['Request' => $request, 'View' => new HttpView()]));

        new HttpRouter()->routeBean($controller, ['name' => '<world>']);

        self::assertSame('<p>Hello &lt;world&gt;</p>', $request->getResponse());
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

    private function controller(BeanFactory $beanFactory): GreetingHttpController
    {
        $controller = new GreetingHttpController();
        $controller->setBeanFactory($beanFactory);

        return $controller;
    }
}
