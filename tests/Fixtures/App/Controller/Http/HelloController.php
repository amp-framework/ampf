<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\Controller\Http;

use ampf\Controller\Http\AbstractController;

/** Greets the route's capture `name`. */
final class HelloController extends AbstractController
{
    public function execute(?string $name = null): void
    {
        $this->getRequest()->setResponse('<p>Hello ' . $this->getView()->escape($name ?? 'nobody') . '</p>');
    }
}
