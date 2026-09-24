<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\View;

use ampf\Tests\Support\ArrayTranslatorService;
use ampf\Tests\Support\RecordingHttpRequest;
use ampf\View\AbstractView;
use ampf\View\HttpView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractView::class)]
#[CoversClass(HttpView::class)]
final class HttpViewTest extends TestCase
{
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

    /**
     * @param array<string, string> $texts
     */
    private function newView(array $texts): HttpView
    {
        $view = new HttpView();
        $view->setTranslatorService(new ArrayTranslatorService($texts));

        return $view;
    }
}
