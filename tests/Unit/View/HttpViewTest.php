<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\View;

use ampf\Bean\BeanFactory;
use ampf\Request\CliRequest;
use ampf\Router\CliRouter;
use ampf\Router\HttpRouter;
use ampf\Router\RouteResolver;
use ampf\Tests\Support\ArrayTranslatorService;
use ampf\Tests\Support\Bean\PlainBean;
use ampf\Tests\Support\Controller\BrokenHeaderHttpController;
use ampf\Tests\Support\Controller\GreetingHttpController;
use ampf\Tests\Support\RecordingHttpRequest;
use ampf\View\HttpView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(HttpView::class)]
final class HttpViewTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideEscapes(): iterable
    {
        yield 'markup' => ['<a href="x">Tom & \'Jerry\'</a>', '&lt;a href=&quot;x&quot;&gt;Tom &amp; &apos;Jerry&apos;&lt;/a&gt;'];
        yield 'an integer' => [42, '42'];
        yield 'a float' => [1.5, '1.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'invalid UTF-8' => ["a\xC3", ''];
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideNoScalars(): iterable
    {
        yield 'null' => [null, 'null'];
        yield 'an array' => [['a'], 'array'];
        yield 'an object' => [new stdClass(), 'stdClass'];
    }

    public function testTeEscapesEveryArgumentBeforeItGoesIntoTheText(): void
    {
        $view = $this->newView(['GREETING' => '<strong>Hello %s</strong>, you have %s messages']);

        self::assertSame(
            '<strong>Hello &lt;img src=x onerror=alert(1)&gt; &amp; &quot;you&quot;</strong>, you have 3 messages',
            $view->te('GREETING', ['<img src=x onerror=alert(1)> & "you"', '3']),
        );
    }

    public function testTTakesArgumentsThatAreMarkupAlready(): void
    {
        $view = $this->newView(['GREETING' => 'Hello %s']);

        self::assertSame('Hello <em>you</em>', $view->t('GREETING', ['<em>you</em>']));
        self::assertSame('Hello %s', $view->te('GREETING'), 'no arguments, nothing put in');
    }

    public function testASubmittedValueIsReadFromTheFormFirst(): void
    {
        $view = $this->newView([]);
        $view->setRequest(new RecordingHttpRequest(get: ['name' => 'query', 'page' => '2'], post: ['name' => 'form']));

        self::assertSame('form', $view->getParamString('name'));
        self::assertSame('2', $view->getParamString('page'));
        self::assertSame('', $view->getParamString('absent'));
    }

    #[DataProvider('provideEscapes')]
    public function testAScalarIsEscapedForHtml(mixed $value, string $escaped): void
    {
        self::assertSame($escaped, new HttpView()->escape($value));
    }

    #[DataProvider('provideNoScalars')]
    public function testWhatIsNoScalarIsNotEscaped(mixed $value, string $type): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only a scalar can be escaped for HTML, not ' . $type . '.');

        new HttpView()->escape($value);
    }

    public function testAnAssetLinkIsTheLinkOfItsResolvedPath(): void
    {
        $view = new HttpView();
        $view->setRequest(new RecordingHttpRequest(['SCRIPT_NAME' => '/app/index.php']));

        self::assertSame('/app/css/app.css', $view->getAssetLink('css/app.css'));
        self::assertSame('/app/css/app.css', $view->getAssetLink('/css//app.css/'));
        self::assertSame('/app/img/logo.png', $view->getAssetLink('css/../img/./logo.png'));
        self::assertSame('/app/img/..logo.png', $view->getAssetLink('img/..logo.png'), '".." starts a name');
        self::assertSame('/app/', $view->getAssetLink('css/..'));
    }

    public function testAViewMayResolveTheAssetPathsItsOwnWay(): void
    {
        $view = new class extends HttpView {
            protected function solveSymbolicPath(string $path): string
            {
                return 'v2/' . parent::solveSymbolicPath($path);
            }
        };
        $view->setRequest(new RecordingHttpRequest(['SCRIPT_NAME' => '/app/index.php']));

        self::assertSame('/app/v2/img/logo.png', $view->getAssetLink('css/../img/logo.png'));
    }

    public function testAnAssetLinkNeedsAPath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An asset link needs the path of the asset.');

        new HttpView()->getAssetLink(' ');
    }

    public function testAnAssetLinkStaysUnderTheWebRoot(): void
    {
        $view = new HttpView();
        $view->setRequest(new RecordingHttpRequest());

        foreach (['../secret.txt', 'css/../../secret.txt', './..'] as $path) {
            try {
                $view->getAssetLink($path);
                self::fail('linked ' . $path);
            } catch (RuntimeException $e) {
                self::assertSame('The asset path ' . $path . ' leaves the web root.', $e->getMessage());
            }
        }
    }

    public function testAnActionLinkIsTheRequests(): void
    {
        $resolver = new RouteResolver();
        $resolver->setConfig(
            ['routes' => ['show' => ['pattern' => 'show/(?P<id>[0-9]+)', 'controller' => 'ShowController']]],
        );
        $request = new RecordingHttpRequest(['SCRIPT_NAME' => '/app/index.php']);
        $request->setRouteResolver($resolver);
        $view = new HttpView();
        $view->setRequest($request);

        self::assertSame('/app/show/7?page=2', $view->getActionLink('show', ['id' => 7, 'page' => 2]));
        self::assertSame('/app/show/7', $view->getActionLink('show', ['id' => '7']));
    }

    public function testTheRequestAndTheRouterAreTheBeansFetchedAtTheirFirstUse(): void
    {
        $request = new RecordingHttpRequest();
        $router = new HttpRouter();
        $beanFactory = new BeanFactory([]);
        $beanFactory->set('Request', $request);
        $beanFactory->set('Router', $router);
        $view = new HttpView();
        $view->setBeanFactory($beanFactory);

        self::assertSame($request, $view->getRequest());
        self::assertSame($router, $view->getRouter());

        $beanFactory->set('Request', new RecordingHttpRequest());
        $beanFactory->set('Router', new HttpRouter());
        self::assertSame($request, $view->getRequest(), 'the view keeps its request');
        self::assertSame($router, $view->getRouter(), 'the view keeps its router');
    }

    public function testAGivenRouterIsTheViews(): void
    {
        $router = new HttpRouter();
        $view = new HttpView();

        $view->setRouter($router);

        self::assertSame($router, $view->getRouter());
    }

    public function testABeanRequestOfTheCommandLineIsRefused(): void
    {
        $beanFactory = new BeanFactory([]);
        $beanFactory->set('Request', new CliRequest(['bin/index.php']));
        $view = new HttpView();
        $view->setBeanFactory($beanFactory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The bean Request is no web request, but ' . CliRequest::class . '.');

        $view->getRequest();
    }

    public function testABeanRouterOfTheCommandLineIsRefused(): void
    {
        $beanFactory = new BeanFactory([]);
        $beanFactory->set('Router', new CliRouter());
        $view = new HttpView();
        $view->setBeanFactory($beanFactory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The bean Router is no web router, but ' . CliRouter::class . '.');

        $view->getRouter();
    }

    public function testASubRouteIsWhatTheControllerRespondsOnARequestOfItsOwn(): void
    {
        $request = new RecordingHttpRequest();
        $view = $this->routingView(['RequestStub' => ['class' => RecordingHttpRequest::class, 'scope' => 'prototype']]);
        $view->getBeanFactory()->set('Request', $request);

        self::assertSame('<p>Hello &lt;Ada&gt;</p>', $view->subRoute('GreetingController', ['name' => '<Ada>']));
        self::assertSame('<p>Hello nobody</p>', $view->subRoute('GreetingController'));
        self::assertSame('', $request->getResponse(), 'the request of the page is not the sub-request\'s');
    }

    public function testASubRouteTakesTheParametersByTheirNames(): void
    {
        $view = $this->routingView(['RequestStub' => ['class' => RecordingHttpRequest::class, 'scope' => 'prototype']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The route\'s parameter id names no parameter of ' . GreetingHttpController::class . '::execute().',
        );

        $view->subRoute('GreetingController', ['id' => '7']);
    }

    public function testASubRouteNeedsAWebRequestStub(): void
    {
        $view = $this->routingView(['RequestStub' => ['class' => CliRequest::class]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The bean RequestStub is no web request, but ' . CliRequest::class . '.');

        $view->subRoute('GreetingController');
    }

    public function testASubRouteNeedsAController(): void
    {
        $view = $this->routingView(['RequestStub' => ['class' => RecordingHttpRequest::class]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The controller bean PlainBean is no ampf\Controller\ControllerInterface, but ' . PlainBean::class . '.',
        );

        $view->subRoute('PlainBean');
    }

    public function testASubRouteThatFailsToFlushPrintsNothing(): void
    {
        $view = $this->routingView(['RequestStub' => ['class' => RecordingHttpRequest::class, 'scope' => 'prototype']]);
        $level = ob_get_level();

        try {
            $view->subRoute('BrokenHeaderController');
            self::fail('flushed a header with a line break');
        } catch (RuntimeException $e) {
            self::assertSame('A header must not contain a control character.', $e->getMessage());
        }

        self::assertSame($level, ob_get_level());
        $this->expectOutputString('');
    }

    /**
     * @param array<string, string> $texts
     */
    private function newView(array $texts): HttpView
    {
        $view = new HttpView();
        $view->setTranslatorService(new ArrayTranslatorService($texts));

        return $view;
    }

    /**
     * @param array<string, array<string, string>> $beans
     */
    private function routingView(array $beans): HttpView
    {
        $beanFactory = new BeanFactory(['beans' => [
            ...$beans,
            'Router' => ['class' => HttpRouter::class],
            'View' => ['class' => HttpView::class, 'scope' => 'prototype'],
            'GreetingController' => ['class' => GreetingHttpController::class, 'scope' => 'prototype'],
            'BrokenHeaderController' => ['class' => BrokenHeaderHttpController::class, 'scope' => 'prototype'],
            'PlainBean' => ['class' => PlainBean::class],
        ]]);
        $view = new HttpView();
        $view->setBeanFactory($beanFactory);

        return $view;
    }
}
