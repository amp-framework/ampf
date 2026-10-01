<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\Controller\Http;

use ampf\BeanAccess\Service\SessionServiceAccess;
use ampf\Controller\Http\AbstractController;

/** A response that may be kept: the session started, which makes PHP add its cache limiter's headers, and then every header against caching removed. */
final class KeptController extends AbstractController
{
    use SessionServiceAccess;

    public function execute(): void
    {
        $this->getSessionService()->getAttribute('visits');

        $this->getRequest()
            ->addHeader('Cache-Control', 'public, max-age=3600')
            ->removeHeader('Pragma')
            ->removeHeader('Expires')
            ->setResponse('Kept')
        ;
    }
}
