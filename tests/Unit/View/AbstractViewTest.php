<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\View;

use ampf\Bean\BeanFactory;
use ampf\Service\Translator\TranslatorServiceInterface;
use ampf\Tests\Support\ArrayTranslatorService;
use ampf\View\AbstractView;
use ampf\View\CliView;
use ampf\View\HttpView;
use ampf\View\ViewResolver;
use ampf\View\ViewResolverInterface;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(AbstractView::class)]
final class AbstractViewTest extends TestCase
{
    private string $timezone;

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideNoTimes(): iterable
    {
        yield 'nothing' => [null, 'null'];
        yield 'a word' => ['soon', '\'soon\''];
        yield 'a fraction of a second' => [1.5, '1.5'];
        yield 'a boolean' => [true, 'true'];
        yield 'an array' => [[1_751_328_000], 'array'];
        yield 'an object' => [new stdClass(), 'stdClass'];
    }

    public function testAVariableIsKeptUnderItsName(): void
    {
        $view = new CliView();
        $view->set('title', 'Home');
        $view->set('none', null);

        self::assertTrue($view->has('title'));
        self::assertSame('Home', $view->get('title'));
        self::assertFalse($view->has('none'), 'null is no value');
        self::assertSame('default', $view->get('none', 'default'));
        self::assertFalse($view->has('missing'));
        self::assertNull($view->get('missing'));
        self::assertSame('default', $view->get('missing', 'default'));
    }

    public function testResetForgetsEveryVariable(): void
    {
        $view = new CliView();
        $view->set('title', 'Home');

        $view->reset();

        self::assertFalse($view->has('title'));
    }

    public function testATemplateSeesItsVariablesAndTheViewOnly(): void
    {
        $view = $this->view();
        $view->set('title', 'Home');
        $view->set('items', [1, 2]);

        self::assertSame('title,items|' . CliView::class, $view->render('scope.txt.php'));
    }

    public function testAVariableThatCannotBeAVariableStaysOutOfTheTemplate(): void
    {
        $view = $this->view();
        $view->set('this', 'not the view');
        $view->set('my-title', 'a key, not a name');
        $view->set('1st', 'neither');
        $view->set('title', 'Home');

        self::assertSame('title|' . CliView::class, $view->render('scope.txt.php'));
    }

    public function testKeyAndValueAreVariablesLikeAnyOther(): void
    {
        $view = $this->view();
        $view->set('key', 'colour');
        $view->set('value', 'blue');
        $view->set('last', 'the last variable');

        self::assertSame('colour=blue', $view->render('key-value.txt.php'));
    }

    public function testTheTemplateUsesTheViewsHelpers(): void
    {
        $view = $this->view();
        $view->set('name', 'Ada');

        self::assertSame('Hello Ada!' . PHP_EOL, $view->render('greeting.txt.php'));
    }

    public function testAFailingTemplatePrintsNothing(): void
    {
        $view = $this->view();
        $level = ob_get_level();

        try {
            $view->render('failing.txt.php');
            self::fail('the template did not fail');
        } catch (RuntimeException $e) {
            self::assertSame('The template failed.', $e->getMessage());
        }

        self::assertSame($level, ob_get_level());
        $this->expectOutputString('');
    }

    public function testAFailingTemplateLeavesNoBufferOfItsOwnOpen(): void
    {
        $view = $this->view();
        $level = ob_get_level();

        try {
            $view->render('failing-nested.txt.php');
            self::fail('the template did not fail');
        } catch (RuntimeException $e) {
            self::assertSame('The template failed inside its own buffer.', $e->getMessage());
        }

        self::assertSame($level, ob_get_level());
        $this->expectOutputString('');
    }

    public function testATemplateThatDoesNotExistIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There is no template missing.txt.php.');

        $this->view()->render('missing.txt.php');
    }

    public function testASubRenderRendersInANewViewWithTheParametersOnly(): void
    {
        $beanFactory = new BeanFactory(['beans' => ['View' => ['class' => CliView::class, 'scope' => 'prototype']]]);
        $beanFactory->set(ViewResolverInterface::class, $this->resolver());
        $view = $this->view();
        $view->setBeanFactory($beanFactory);
        $view->set('other', 'the outer view\'s');

        self::assertSame('Sub|other: false', $view->subRender('sub.txt.php', ['title' => 'Sub']));
        self::assertSame('|other: false', $view->subRender('sub.txt.php', ['title' => '']));
    }

    public function testASubRenderWithoutParametersRendersAnEmptyView(): void
    {
        $beanFactory = new BeanFactory(['beans' => ['View' => ['class' => CliView::class, 'scope' => 'prototype']]]);
        $beanFactory->set(ViewResolverInterface::class, $this->resolver());
        $view = $this->view();
        $view->setBeanFactory($beanFactory);
        $view->set('title', 'the outer view\'s');

        self::assertSame(CliView::class, substr($view->subRender('scope.txt.php'), 1));
    }

    public function testASubRenderNeedsAViewBean(): void
    {
        $view = $this->view();
        $view->setBeanFactory(new BeanFactory(['beans' => ['View' => ['class' => stdClass::class]]]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The bean View is no view, but stdClass.');

        $view->subRender('sub.txt.php');
    }

    public function testANumberIsFormattedWithoutDecimalsAndWithSpacesBetweenThousands(): void
    {
        $view = new CliView();

        self::assertSame('1 234 568', $view->formatNumber(1_234_567.5));
        self::assertSame('1 234 567', $view->formatNumber('1234567'));
        self::assertSame('1.234.567,50', $view->formatNumber(1_234_567.5, 2, ',', '.'));
        self::assertSame('-12', $view->formatNumber(-12));
        self::assertSame('12.3', $view->formatNumber(12.34, 1));
        self::assertSame('1234', $view->formatNumber(1234, thousandsSep: ''));
        self::assertSame('1 234;50', $view->formatNumber(1234.5, 2, ';'));
    }

    public function testATimeIsShownInTheLocalTimeZone(): void
    {
        date_default_timezone_set('Europe/Berlin');
        $view = new CliView();

        self::assertSame(
            '01.01.2025 01:00',
            $view->formatTime(new DateTime('2025-01-01 00:00:00', new DateTimeZone('UTC'))),
        );
        self::assertSame('01.07.2025 02:00', $view->formatTime(new DateTimeImmutable('2025-07-01 00:00:00+00:00')));
        self::assertSame('2025-07-01T02:00:00+02:00', $view->formatTime(1_751_328_000, DATE_ATOM));
        self::assertSame('01.07.2025 02:00', $view->formatTime('1751328000'));
        self::assertSame('01.07.2025 02:00', $view->formatTime(1_751_328_000.0));
        self::assertSame('01.01.1970 00:59', $view->formatTime(-60));
    }

    public function testTheTimeGivenIsNotChanged(): void
    {
        date_default_timezone_set('Europe/Berlin');
        $time = new DateTime('2025-01-01 00:00:00', new DateTimeZone('UTC'));

        new CliView()->formatTime($time);

        self::assertSame('UTC', $time->getTimezone()->getName());
    }

    #[DataProvider('provideNoTimes')]
    public function testWhatIsNoTimeIsRefused(mixed $time, string $shown): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A time is a DateTimeInterface or a Unix timestamp, not ' . $shown . '.');

        // @phpstan-ignore argument.type (what is no time, on purpose)
        new CliView()->formatTime($time);
    }

    public function testATranslationWithoutTextIsEmpty(): void
    {
        $translator = self::createStub(TranslatorServiceInterface::class);
        $translator->method('translate')->willReturn(null);
        $view = new CliView();
        $view->setTranslatorService($translator);

        self::assertSame('', $view->t('MISSING'));
    }

    public function testTeWithoutArgumentsIsT(): void
    {
        $view = new HttpView();
        $view->setTranslatorService(new ArrayTranslatorService(['TEXT' => '<b>%s</b>']));

        self::assertSame('<b>%s</b>', $view->te('TEXT'));
        self::assertSame('<b>&lt;i&gt;</b>', $view->te('TEXT', ['<i>']));
        self::assertSame('<b><i></b>', $view->t('TEXT', ['<i>']));
    }

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    private function view(): CliView
    {
        $view = new CliView();
        $view->setViewResolver($this->resolver());

        return $view;
    }

    private function resolver(): ViewResolver
    {
        $resolver = new ViewResolver();
        $resolver->setConfig(['viewDirectory' => __DIR__ . '/../../Fixtures/views']);

        return $resolver;
    }
}
