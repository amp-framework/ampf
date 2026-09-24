<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Controller;

use ampf\Controller\Http\AbstractController;
use ampf\Tests\Support\RecordingHttpRequest;

/**
 * A web controller whose response has a body and a header line that flush() refuses.
 */
final class BrokenHeaderHttpController extends AbstractController
{
    public function execute(): void
    {
        $request = $this->getRequest();
        assert($request instanceof RecordingHttpRequest);

        $request->setResponse('<p>a body</p>');
        $request->addRawHeader("X-Test: a\r\nSet-Cookie: b=c");
    }
}
