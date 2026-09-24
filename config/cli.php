<?php

declare(strict_types=1);

use ampf\Controller\Cli\BeanAccessGeneratorController;
use ampf\Request\CliRequest;
use ampf\Router\CliRouter;
use ampf\View\CliView;

/*
 * The command line's beans, loaded after config/default.php by a CLI entry point. 'Router', 'Request',
 * 'RequestStub' and 'View' are roles the two transports fill with different classes, so they are keyed by name, not
 * by type.
 */
return [
    'routes' => [],

    'beans' => [
        'Router' => ['class' => CliRouter::class],
        'Request' => ['class' => CliRequest::class],
        // The request of a sub-request (CliView::subRoute()): configured like 'Request', a new one for each use
        'RequestStub' => ['class' => CliRequest::class, 'parent' => 'Request', 'scope' => 'prototype'],
        // A new view for each use, so one template's variables never reach the next (subRender())
        'View' => ['class' => CliView::class, 'scope' => 'prototype'],

        // Writes the application's bean access traits (its `beanAccessGenerator` block names the application); the
        // application routes a command to it, e.g. 'beanAccess/generate'
        'BeanAccessGeneratorController' => ['class' => BeanAccessGeneratorController::class],
    ],

    // The directory of the templates (ViewResolver); the application names its own
    'viewDirectory' => null,
];
