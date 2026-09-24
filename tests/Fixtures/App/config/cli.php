<?php

declare(strict_types=1);

use ampf\Tests\Fixtures\App\Controller\Cli\FailController;
use ampf\Tests\Fixtures\App\Controller\Cli\GreetController;

return [
    'routes' => [
        'greet' => ['pattern' => 'greet', 'controller' => 'GreetController'],
        'fail' => ['pattern' => 'fail', 'controller' => 'FailController'],
        'beanAccess/generate' => ['pattern' => 'beanAccess/generate', 'controller' => 'BeanAccessGeneratorController'],
    ],

    'beans' => [
        'GreetController' => ['class' => GreetController::class],
        'FailController' => ['class' => FailController::class],
    ],

    // The application's classes are under tests/Fixtures/App of the framework's checkout
    'beanAccessGenerator' => [
        'projectRoot' => dirname(__DIR__, 4),
        'namespace' => 'ampf\Tests\Fixtures\App',
        'sourceDirectory' => 'tests/Fixtures/App',
    ],
];
