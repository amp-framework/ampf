<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\Controller\Cli;

use ampf\Controller\Cli\AbstractController;

/** Greets its first argument. */
final class GreetController extends AbstractController
{
    public function execute(string $name = 'nobody'): void
    {
        $view = $this->getView();
        $view->set('name', $name);

        $this->getRequest()->setResponse($view->render('greet.txt.php'));
    }
}
