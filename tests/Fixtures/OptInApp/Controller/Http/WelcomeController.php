<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\OptInApp\Controller\Http;

use ampf\BeanAccess\Service\SessionServiceAccess;
use ampf\Controller\Http\AbstractController;

/** A page that reads the session: it greets the user the session holds, or a visitor. */
final class WelcomeController extends AbstractController
{
    use SessionServiceAccess;

    public function execute(): void
    {
        $user = $this->getSessionService()->getAttribute('user');

        $this->getRequest()->setResponse(
            '<p>Welcome, ' . (is_string($user) ? $this->getView()->escape($user) : 'visitor') . "</p>\n",
        );
    }
}
