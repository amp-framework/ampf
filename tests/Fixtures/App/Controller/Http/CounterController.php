<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\Controller\Http;

use ampf\BeanAccess\Service\SessionServiceAccess;
use ampf\Controller\Http\AbstractController;

/** Counts the session's visits. */
final class CounterController extends AbstractController
{
    use SessionServiceAccess;

    public function execute(): void
    {
        $visits = $this->getSessionService()->getAttribute('visits');
        $visits = (is_int($visits) ? $visits : 0) + 1;
        $this->getSessionService()->setAttribute('visits', $visits);

        $this->getRequest()->setResponse('Visits: ' . $visits);
    }
}
