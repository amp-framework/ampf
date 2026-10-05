<?php

declare(strict_types=1);

use ampf\Tests\Fixtures\RuleAbidingApp\Constant\RouteConstants;
use ampf\Tests\Support\Controller\GreetingCliController;

return [
    'routes' => [
        RouteConstants::CLI_ROUTE_ID_GREET => ['pattern' => 'greet', 'controller' => 'GreetController'],
        RouteConstants::CLI_ROUTE_ID_BEAN_ACCESS => [
            'pattern' => 'beanAccess/generate',
            'controller' => 'BeanAccessGeneratorController',
        ],
        RouteConstants::CLI_ROUTE_ID_HELP => ['pattern' => '.*', 'controller' => 'GreetController'],
    ],

    'beans' => [
        'GreetController' => ['class' => GreetingCliController::class],
    ],

    // The application's classes are under tests/Fixtures/RuleAbidingApp of the framework's checkout
    'beanAccessGenerator' => [
        'projectRoot' => dirname(__DIR__, 4),
        'namespace' => 'ampf\Tests\Fixtures\RuleAbidingApp',
        'sourceDirectory' => 'tests/Fixtures/RuleAbidingApp',
        'entityNamespace' => null,
    ],
];
