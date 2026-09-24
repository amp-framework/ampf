<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\Controller\Cli;

use ampf\Controller\Cli\AbstractController;

/** Fails as a command fails: a message and an exit code other than 0. */
final class FailController extends AbstractController
{
    public function execute(): void
    {
        $this->getRequest()->setResponse('Something failed.' . PHP_EOL)->setExitCode(3);
    }
}
