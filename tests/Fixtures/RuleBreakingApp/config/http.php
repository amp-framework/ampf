<?php

declare(strict_types=1);

use ampf\Service\TimeL10n\TimeL10nServiceInterface;
use ampf\Tests\Fixtures\RuleBreakingApp\Constant\RouteConstants;
use ampf\Tests\Support\Controller\GreetingHttpController;

/*
 * Routes that name a bean without a definition and beans that are no controller (a role of the framework's
 * config/http.php, a service of its config/default.php), and one after the catch-all.
 */
return [
    'routes' => [
        RouteConstants::ROUTE_ID_HOME => ['pattern' => '', 'controller' => 'HomeController'],
        'missing' => ['pattern' => 'missing', 'controller' => 'MissingController'],
        'view' => ['pattern' => 'view', 'controller' => 'View'],
        'time' => ['pattern' => 'time', 'controller' => TimeL10nServiceInterface::class],
        'not-found' => ['pattern' => '(?P<path>.*)', 'controller' => 'HomeController'],
        'late' => ['pattern' => 'late', 'controller' => 'HomeController'],
    ],

    'beans' => [
        'HomeController' => ['class' => GreetingHttpController::class],
    ],
];
