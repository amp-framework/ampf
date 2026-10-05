<?php

declare(strict_types=1);

use ampf\Bean\BeanFactory;
use ampf\Bootstrap\ApplicationContext;
use ampf\Bootstrap\ErrorSettings;
use ampf\Bootstrap\TraceSettings;
use ampf\Request\HttpRequestInterface;
use ampf\Router\HttpRouterInterface;

// The web entry point of the fixture application that opts into the lazy session
require __DIR__ . '/../../../../vendor/autoload.php';

ErrorSettings::applyDefaults();
TraceSettings::apply();
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

$router->route($request);
$request->flush();
