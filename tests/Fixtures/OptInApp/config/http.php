<?php

declare(strict_types=1);

use ampf\Tests\Fixtures\OptInApp\Controller\Http\WelcomeController;

return [
    'routes' => [
        'welcome' => ['pattern' => 'welcome', 'controller' => 'WelcomeController'],
    ],

    'beans' => [
        'WelcomeController' => ['class' => WelcomeController::class],
    ],
];
