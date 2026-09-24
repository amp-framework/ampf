<?php

declare(strict_types=1);

use ampf\Bean\BeanFactory;
use ampf\Bootstrap\ApplicationContext;
use ampf\Bootstrap\ErrorSettings;
use ampf\Request\CliRequestInterface;
use ampf\Router\CliRouterInterface;

// The command line entry point of the fixture application, as an application's bin/index.php
require __DIR__ . '/../../../../vendor/autoload.php';

ErrorSettings::applyDefaults();
date_default_timezone_set('UTC');

$config = ApplicationContext::boot([
    __DIR__ . '/../../../../config/default.php',
    __DIR__ . '/../../../../config/cli.php',
    __DIR__ . '/../config/default.php',
    __DIR__ . '/../config/cli.php',
]);
ErrorSettings::apply($config, dirname(__DIR__));

$beanFactory = new BeanFactory($config);
$router = $beanFactory->get('Router');
$request = $beanFactory->get('Request');
assert($router instanceof CliRouterInterface && $request instanceof CliRequestInterface);

$router->route($request);
$request->flush();

exit($request->getExitCode());
