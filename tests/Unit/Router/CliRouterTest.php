<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Router;

use ampf\Bean\BeanFactory;
use ampf\Request\CliRequest;
use ampf\Router\CliRouter;
use ampf\Router\RouteResolver;
use ampf\Tests\Support\Bean\PlainBean;
use ampf\Tests\Support\Controller\RecordingController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CliRouter::class)]
final class CliRouterTest extends TestCase
{
    private BeanFactory $beanFactory;

    private CliRouter $router;

    public function testTheRoutesControllerRunsWithTheArgumentsInTheirOrder(): void
    {
        self::assertSame(
            $this->router,
            $this->router->route($this->request(['bin/index.php', 'user/delete', '42', 'force'])),
        );

        $controller = $this->beanFactory->get('UserController');
        self::assertInstanceOf(RecordingController::class, $controller);
        self::assertSame(['beforeAction', "execute('42', 'force')", 'afterAction'], $controller->getCalls());
    }

    public function testNamedParametersArriveInTheirOrderAsWell(): void
    {
        $controller = new RecordingController();
        $this->router->routeBean($controller, ['slug' => 'first', 'id' => 'second']);

        self::assertSame(['beforeAction', "execute('first', 'second')", 'afterAction'], $controller->getCalls());
    }

    public function testWithoutArgumentsExecuteRunsWithItsDefaults(): void
    {
        $controller = new RecordingController();
        $this->router->routeBean($controller);

        self::assertSame(['beforeAction', 'execute(NULL, NULL)', 'afterAction'], $controller->getCalls());
    }

    public function testAnInterruptionEndsTheLifecycle(): void
    {
        $controller = new RecordingController()->interruptIn('beforeAction');
        $this->router->routeBean($controller, ['1']);
        self::assertSame(['beforeAction'], $controller->getCalls());

        $controller = new RecordingController()->interruptIn('execute');
        $this->router->routeBean($controller, ['1']);
        self::assertSame(['beforeAction', "execute('1', NULL)"], $controller->getCalls());
    }

    public function testARouteToAMissingBeanIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The controller bean MissingController has no configuration.');

        $this->router->route($this->request(['bin/index.php', 'missing']));
    }

    public function testARouteToABeanThatIsNoControllerIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The controller bean PlainController is no ampf\Controller\ControllerInterface, but ' . PlainBean::class . '.',
        );

        $this->router->route($this->request(['bin/index.php', 'plain']));
    }

    protected function setUp(): void
    {
        $this->beanFactory = new BeanFactory([
            'routes' => [
                'user/delete' => ['pattern' => 'user/delete', 'controller' => 'UserController'],
                'missing' => ['pattern' => 'missing', 'controller' => 'MissingController'],
                'plain' => ['pattern' => 'plain', 'controller' => 'PlainController'],
            ],
            'beans' => [
                'UserController' => ['class' => RecordingController::class],
                'PlainController' => ['class' => PlainBean::class],
            ],
        ]);
        $this->router = new CliRouter();
        $this->router->setBeanFactory($this->beanFactory);
    }

    /**
     * @param list<string> $argv
     */
    private function request(array $argv): CliRequest
    {
        $resolver = new RouteResolver();
        $resolver->setBeanFactory($this->beanFactory);
        $request = new CliRequest($argv);
        $request->setRouteResolver($resolver);

        return $request;
    }
}
