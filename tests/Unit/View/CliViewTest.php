<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\View;

use ampf\Bean\BeanFactory;
use ampf\Request\CliRequest;
use ampf\Router\CliRouter;
use ampf\Router\HttpRouter;
use ampf\Tests\Support\Bean\PlainBean;
use ampf\Tests\Support\Controller\GreetingCliController;
use ampf\Tests\Support\RecordingHttpRequest;
use ampf\View\CliView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CliView::class)]
final class CliViewTest extends TestCase
{
    public function testTextIsPrintedAsItIs(): void
    {
        self::assertSame('<b>"Ada" & \'Bob\'</b>', new CliView()->escape('<b>"Ada" & \'Bob\'</b>'));
    }

    public function testTheRouterIsTheBeanRouterFetchedAtItsFirstUse(): void
    {
        $router = new CliRouter();
        $beanFactory = new BeanFactory([]);
        $beanFactory->set('Router', $router);
        $view = new CliView();
        $view->setBeanFactory($beanFactory);

        self::assertSame($router, $view->getRouter());

        $beanFactory->set('Router', new CliRouter());
        self::assertSame($router, $view->getRouter(), 'the view keeps its router');
    }

    public function testAGivenRouterIsTheViews(): void
    {
        $router = new CliRouter();
        $view = new CliView();

        $view->setRouter($router);

        self::assertSame($router, $view->getRouter());
    }

    public function testABeanRouterOfTheWebIsRefused(): void
    {
        $beanFactory = new BeanFactory([]);
        $beanFactory->set('Router', new HttpRouter());
        $view = new CliView();
        $view->setBeanFactory($beanFactory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The bean Router is no command line router, but ' . HttpRouter::class . '.');

        $view->getRouter();
    }

    public function testASubRouteIsWhatTheControllerPrintsOnARequestOfItsOwn(): void
    {
        $request = new CliRequest(['bin/index.php', 'main']);
        $view = $this->view(['RequestStub' => ['class' => CliRequest::class, 'scope' => 'prototype']]);
        $view->getBeanFactory()->set('Request', $request);

        self::assertSame('Hello Ada' . PHP_EOL, $view->subRoute('GreetingController', ['Ada']));
        self::assertSame('Hello nobody' . PHP_EOL, $view->subRoute('GreetingController'));

        $this->expectOutputString('');
        $request->flush();
    }

    public function testASubRouteNeedsACommandLineRequestStub(): void
    {
        $view = $this->view(['RequestStub' => ['class' => RecordingHttpRequest::class]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The bean RequestStub is no command line request, but ' . RecordingHttpRequest::class . '.',
        );

        $view->subRoute('GreetingController');
    }

    public function testASubRouteNeedsAController(): void
    {
        $view = $this->view(['RequestStub' => ['class' => CliRequest::class]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The controller bean PlainBean is no ampf\Controller\ControllerInterface, but ' . PlainBean::class . '.',
        );

        $view->subRoute('PlainBean');
    }

    /**
     * @param array<string, array<string, string>> $beans
     */
    private function view(array $beans): CliView
    {
        $beanFactory = new BeanFactory(['beans' => [
            ...$beans,
            'Router' => ['class' => CliRouter::class],
            'GreetingController' => ['class' => GreetingCliController::class, 'scope' => 'prototype'],
            'PlainBean' => ['class' => PlainBean::class],
        ]]);
        $view = new CliView();
        $view->setBeanFactory($beanFactory);

        return $view;
    }
}
