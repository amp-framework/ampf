<?php

declare(strict_types=1);

namespace ampfTest\Views;

use ampf\views\impl\DefaultHttpView;
use ampfTest\Support\ArrayTranslatorService;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \ampf\views\AbstractView
 * @covers \ampf\views\impl\DefaultHttpView
 */
final class DefaultHttpViewTest extends TestCase
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

    /**
     * @param array<string, string> $texts
     */
    private function newView(array $texts): DefaultHttpView
    {
        $view = new DefaultHttpView();
        $view->setTranslatorService(new ArrayTranslatorService($texts));

        return $view;
    }
}
