<?php

declare(strict_types=1);

use ampf\Tests\Fixtures\App\Controller\Http\CounterController;
use ampf\Tests\Fixtures\App\Controller\Http\HelloController;
use ampf\Tests\Fixtures\App\Controller\Http\HomeController;
use ampf\Tests\Fixtures\App\Controller\Http\NotesController;
use ampf\Tests\Fixtures\App\Controller\Http\ThemeController;

return [
    'routes' => [
        'home' => ['pattern' => '', 'controller' => 'HomeController'],
        'hello' => ['pattern' => 'hello/(?P<name>[a-z]+)', 'controller' => 'HelloController'],
        'notes' => ['pattern' => 'notes', 'controller' => 'NotesController'],
        'counter' => ['pattern' => 'counter', 'controller' => 'CounterController'],
        'theme' => ['pattern' => 'theme/(?P<theme>light|dark)', 'controller' => 'ThemeController'],
    ],

    'beans' => [
        'HomeController' => ['class' => HomeController::class],
        'HelloController' => ['class' => HelloController::class],
        'NotesController' => ['class' => NotesController::class],
        'CounterController' => ['class' => CounterController::class],
        'ThemeController' => ['class' => ThemeController::class],
    ],
];
