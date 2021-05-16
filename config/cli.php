<?php

declare(strict_types=1);

use ampf\requests\impl\DefaultCli;
use ampf\router\impl\DefaultCliRouter;
use ampf\router\impl\DefaultRouteResolver;
use ampf\views\impl\DefaultCliView;

return [
    'routes' => [],

    'beans' => [
        /**
         * Routing stuff
         */
        'RouteResolver' => [
            'class' => DefaultRouteResolver::class,
        ],
        'Router' => [
            'class' => DefaultCliRouter::class,
        ],

        /**
         * Request stuff
         */
        'Request' => [
            'class' => DefaultCli::class,
        ],
        'RequestStub' => [
            'class' => DefaultCli::class,
            'parent' => 'Request',
            'scope' => 'prototype',
        ],

        /**
         * View stuff
         */
        'View' => [
            'class' => DefaultCliView::class,
            'scope' => 'prototype',
        ],
    ],

    'viewDirectory' => null,
];
