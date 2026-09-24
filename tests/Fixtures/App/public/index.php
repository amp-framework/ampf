<?php

declare(strict_types=1);

use ampf\Bean\BeanFactory;
use ampf\Bootstrap\ApplicationContext;
use ampf\Bootstrap\ErrorSettings;
use ampf\Request\HttpRequestInterface;
use ampf\Router\HttpRouterInterface;

// The web entry point of the fixture application, as an application's public/index.php
require __DIR__ . '/../../../../vendor/autoload.php';

ErrorSettings::applyDefaults();
date_default_timezone_set('UTC');

$config = ApplicationContext::boot([
    __DIR__ . '/../../../../config/default.php',
    __DIR__ . '/../../../../config/http.php',
    __DIR__ . '/../config/default.php',
    __DIR__ . '/../config/http.php',
]);
ErrorSettings::apply($config, dirname(__DIR__));

$beanFactory = new BeanFactory($config);
$router = $beanFactory->get('Router');
$request = $beanFactory->get('Request');
assert($router instanceof HttpRouterInterface && $request instanceof HttpRequestInterface);

// A route no pattern matches is the application's to answer
if ($request->getController() === null) {
    $request->setStatusCode(404)->setResponse('Not found');
} else {
    $router->route($request);
}

$request->flush();
