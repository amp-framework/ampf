<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Controller;

use ampf\Controller\Http\AbstractController;

/**
 * A web controller on the framework's base: greets the route's capture name.
 */
final class GreetingHttpController extends AbstractController
{
    public function execute(?string $name = null): void
    {
        $this->getRequest()->setResponse('<p>Hello ' . $this->getView()->escape($name ?? 'nobody') . '</p>');
    }
}
