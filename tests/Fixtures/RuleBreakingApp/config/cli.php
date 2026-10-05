<?php

declare(strict_types=1);

use ampf\Tests\Fixtures\RuleBreakingApp\Constant\RouteConstants;
use ampf\Tests\Support\Controller\GreetingCliController;

/*
 * The catch-all of the command line first, a route after it; and the generator over the application, whose traits are
 * one missing and one stale.
 */
return [
    'routes' => [
        RouteConstants::CLI_ROUTE_ID_HELP => ['pattern' => '.*', 'controller' => 'HelpController'],
        'greet' => ['pattern' => 'greet', 'controller' => 'HelpController'],
    ],

    'beans' => [
        'HelpController' => ['class' => GreetingCliController::class],
    ],

    'beanAccessGenerator' => [
        'projectRoot' => dirname(__DIR__, 4),
        'namespace' => 'ampf\Tests\Fixtures\RuleBreakingApp',
        'sourceDirectory' => 'tests/Fixtures/RuleBreakingApp',
        'entityNamespace' => null,
    ],
];
