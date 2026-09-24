<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Router;

use ampf\Bean\BeanFactory;
use ampf\Router\HttpRouter;
use ampf\Router\RouteResolver;
use ampf\Tests\Support\Bean\PlainBean;
use ampf\Tests\Support\Controller\RecordingController;
use ampf\Tests\Support\Controller\VariadicController;
use ampf\Tests\Support\RecordingHttpRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(HttpRouter::class)]
final class HttpRouterTest extends TestCase
{
    private BeanFactory $beanFactory;

    private HttpRouter $router;

    public function testTheRoutesControllerRunsItsLifecycle(): void
    {
        $this->router->route($this->request('/article/42/intro'));

        self::assertSame(
            ['beforeAction', "execute('42', 'intro')", 'afterAction'],
            $this->controller('ArticleController')->getCalls(),
        );
    }

    public function testTheCapturesArriveByTheirNamesWhateverTheirOrder(): void
    {
        $this->router->route($this->request('/by-slug/intro/42'));

        self::assertSame(
            ['beforeAction', "execute('42', 'intro')", 'afterAction'],
            $this->controller('SlugFirstController')->getCalls(),
        );
    }

    public function testARouteWithoutCapturesRunsExecuteWithItsDefaults(): void
    {
        $this->router->route($this->request('/'));

        self::assertSame(
            ['beforeAction', 'execute(NULL, NULL)', 'afterAction'],
            $this->controller('HomeController')->getCalls(),
        );
    }

    public function testACaptureExecuteDoesNotTakeIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The route\'s parameter page names no parameter of ' . RecordingController::class . '::execute().',
        );

        $this->router->route($this->request('/list/2'));
    }

    public function testNothingRunsWhenACaptureIsRefused(): void
    {
        try {
            $this->router->route($this->request('/list/2'));
            self::fail('ran a controller without the parameter');
        } catch (RuntimeException) {
            self::assertSame([], $this->controller('ListController')->getCalls());
        }
    }

    public function testAnExecuteThatCollectsNamedArgumentsTakesEveryCapture(): void
    {
        $this->router->route($this->request('/any/a/b'));

        $controller = $this->beanFactory->get('AnyController');
        self::assertInstanceOf(VariadicController::class, $controller);
        self::assertSame(['first' => 'a', 'second' => 'b'], $controller->getArguments());
    }

    public function testAnInterruptionEndsTheLifecycle(): void
    {
        $controller = new RecordingController()->interruptIn('beforeAction');
        self::assertSame($this->router, $this->router->routeBean($controller, ['id' => '1']));
        self::assertSame(['beforeAction'], $controller->getCalls());

        $controller = new RecordingController()->interruptIn('execute');
        $this->router->routeBean($controller, ['id' => '1']);
        self::assertSame(['beforeAction', "execute('1', NULL)"], $controller->getCalls());

        $controller = new RecordingController()->interruptIn('afterAction');
        $this->router->routeBean($controller);
        self::assertSame(['beforeAction', 'execute(NULL, NULL)', 'afterAction'], $controller->getCalls());
    }

    public function testARequestWithoutARouteIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No route matches the request.');

        $this->router->route($this->request('/unknown/path'));
    }

    public function testARouteToAMissingBeanIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The controller bean MissingController has no configuration.');

        $this->router->route($this->request('/missing'));
    }

    public function testARouteToABeanThatIsNoControllerIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The controller bean PlainController is no ampf\Controller\ControllerInterface, but ' . PlainBean::class . '.',
        );

        $this->router->route($this->request('/plain'));
    }

    protected function setUp(): void
    {
        $this->beanFactory = new BeanFactory([
            'routes' => [
                'home' => ['pattern' => '', 'controller' => 'HomeController'],
                'article' => ['pattern' => 'article/(?P<id>[0-9]+)/(?P<slug>[a-z]+)', 'controller' => 'ArticleController'],
                'slug' => ['pattern' => 'by-slug/(?P<slug>[a-z]+)/(?P<id>[0-9]+)', 'controller' => 'SlugFirstController'],
                'list' => ['pattern' => 'list/(?P<page>[0-9]+)', 'controller' => 'ListController'],
                'any' => ['pattern' => 'any/(?P<first>[a-z]+)/(?P<second>[a-z]+)', 'controller' => 'AnyController'],
                'missing' => ['pattern' => 'missing', 'controller' => 'MissingController'],
                'plain' => ['pattern' => 'plain', 'controller' => 'PlainController'],
            ],
            'beans' => [
                'HomeController' => ['class' => RecordingController::class],
                'ArticleController' => ['class' => RecordingController::class],
                'SlugFirstController' => ['class' => RecordingController::class],
                'ListController' => ['class' => RecordingController::class],
                'AnyController' => ['class' => VariadicController::class],
                'PlainController' => ['class' => PlainBean::class],
            ],
        ]);
        $this->router = new HttpRouter();
        $this->router->setBeanFactory($this->beanFactory);
    }

    private function request(string $uri): RecordingHttpRequest
    {
        $resolver = new RouteResolver();
        $resolver->setBeanFactory($this->beanFactory);
        $request = new RecordingHttpRequest(['REQUEST_URI' => $uri]);
        $request->setRouteResolver($resolver);

        return $request;
    }

    private function controller(string $beanID): RecordingController
    {
        $controller = $this->beanFactory->get($beanID);
        self::assertInstanceOf(RecordingController::class, $controller);

        return $controller;
    }
}
