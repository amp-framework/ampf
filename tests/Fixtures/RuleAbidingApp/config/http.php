<?php

declare(strict_types=1);

use ampf\Tests\Fixtures\RuleAbidingApp\Constant\RouteConstants;
use ampf\Tests\Fixtures\RuleAbidingApp\Controller\HomeController;
use ampf\Tests\Support\Controller\GreetingHttpController;

return [
    'routes' => [
        RouteConstants::ROUTE_ID_HOME => ['pattern' => '', 'controller' => 'HomeController'],
        RouteConstants::ROUTE_ID_HELLO => ['pattern' => 'hello/(?P<name>[a-z]+)', 'controller' => 'HelloController'],
        RouteConstants::ROUTE_ID_NOT_FOUND => ['pattern' => '.*', 'controller' => 'HelloController'],
    ],

    'beans' => [
        'HomeController' => ['class' => HomeController::class],
        'HelloController' => ['class' => GreetingHttpController::class],
    ],
];
