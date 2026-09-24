<?php

declare(strict_types=1);

use ampf\Request\HttpRequest;
use ampf\Router\HttpRouter;
use ampf\View\HttpView;

/*
 * The web's beans, loaded after config/default.php by an HTTP entry point. 'Router', 'Request', 'RequestStub' and
 * 'View' are roles the two transports fill with different classes, so they are keyed by name, not by type.
 */
return [
    'routes' => [],

    'beans' => [
        'Router' => ['class' => HttpRouter::class],
        'Request' => ['class' => HttpRequest::class],
        // The request of a sub-request (HttpView::subRoute()): configured like 'Request', a new one for each use
        'RequestStub' => ['class' => HttpRequest::class, 'parent' => 'Request', 'scope' => 'prototype'],
        // A new view for each use, so one template's variables never reach the next (subRender())
        'View' => ['class' => HttpView::class, 'scope' => 'prototype'],
    ],

    // The directory of the templates (ViewResolver); the application names its own
    'viewDirectory' => null,
];
