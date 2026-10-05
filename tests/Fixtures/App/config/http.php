<?php

declare(strict_types=1);

use ampf\Tests\Fixtures\App\Controller\Http\CounterController;
use ampf\Tests\Fixtures\App\Controller\Http\HelloController;
use ampf\Tests\Fixtures\App\Controller\Http\HomeController;
use ampf\Tests\Fixtures\App\Controller\Http\KeptController;
use ampf\Tests\Fixtures\App\Controller\Http\NotesController;
use ampf\Tests\Fixtures\App\Controller\Http\StylesheetController;
use ampf\Tests\Fixtures\App\Controller\Http\ThemeController;
use ampf\Tests\Fixtures\App\Controller\Http\UploadController;

return [
    'routes' => [
        'home' => ['pattern' => '', 'controller' => 'HomeController'],
        'hello' => ['pattern' => 'hello/(?P<name>[a-z]+)', 'controller' => 'HelloController'],
        'notes' => ['pattern' => 'notes', 'controller' => 'NotesController'],
        'counter' => ['pattern' => 'counter', 'controller' => 'CounterController'],
        'kept' => ['pattern' => 'kept', 'controller' => 'KeptController'],
        'theme' => ['pattern' => 'theme/(?P<theme>light|dark)', 'controller' => 'ThemeController'],
        // A form's uploaded files
        'upload' => ['pattern' => 'upload', 'controller' => 'UploadController'],
        // The address of an asset with its version, which the web server serves from public/assets (AssetService::link())
        'stylesheet' => ['pattern' => 'stylesheet', 'controller' => 'StylesheetController'],
    ],

    'beans' => [
        'HomeController' => ['class' => HomeController::class],
        'HelloController' => ['class' => HelloController::class],
        'NotesController' => ['class' => NotesController::class],
        'CounterController' => ['class' => CounterController::class],
        'KeptController' => ['class' => KeptController::class],
        'ThemeController' => ['class' => ThemeController::class],
        'UploadController' => ['class' => UploadController::class],
        'StylesheetController' => ['class' => StylesheetController::class],
    ],
];
