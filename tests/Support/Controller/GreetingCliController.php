<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Controller;

use ampf\Controller\Cli\AbstractController;

/**
 * A command line controller on the framework's base: greets its first argument.
 */
final class GreetingCliController extends AbstractController
{
    public function execute(?string $name = null): void
    {
        $this->getRequest()->setResponse('Hello ' . ($name ?? 'nobody') . PHP_EOL);
    }
}
