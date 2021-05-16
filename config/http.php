<?php

declare(strict_types=1);

use ampf\requests\impl\DefaultHttp;
use ampf\router\impl\DefaultHttpRouter;
use ampf\router\impl\DefaultRouteResolver;
use ampf\views\impl\DefaultHttpView;

return [
    'routes' => [],

    'beans' => [
        /**
         * Routing stuff
         */
        'RouteResolver' => [
            'class' => DefaultRouteResolver::class,
            'properties' => [
                'Config' => 'config',
            ],
        ],
        'Router' => [
            'class' => DefaultHttpRouter::class,
        ],

        /**
         * Request stuff
         */
        'Request' => [
            'class' => DefaultHttp::class,
        ],
        'RequestStub' => [
            'class' => DefaultHttp::class,
            'parent' => 'Request',
            'scope' => 'prototype',
        ],

        /**
         * View stuff
         */
        'View' => [
            'class' => DefaultHttpView::class,
            'scope' => 'prototype',
        ],
    ],

    'viewDirectory' => null,
];
