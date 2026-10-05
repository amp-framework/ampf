<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\RuleAbidingApp\Controller;

use ampf\Controller\Http\AbstractController;
use ampf\Tests\Fixtures\RuleAbidingApp\BeanAccess\Service\GreeterAccess;

/** The home page: a greeting of the application's service. */
final class HomeController extends AbstractController
{
    use GreeterAccess;

    public function execute(): void
    {
        $this->getRequest()->setResponse($this->getGreeter()->greet('world'));
    }
}
