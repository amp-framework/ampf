<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\Controller\Http;

use ampf\Controller\Http\AbstractController;

/** Keeps the theme of the route's capture in a cookie, and returns home. */
final class ThemeController extends AbstractController
{
    public function execute(string $theme = 'light'): void
    {
        $this->getRequest()->setCookieParam('theme', $theme);
        $this->getRequest()->setRedirect('home', null, 302);
    }
}
