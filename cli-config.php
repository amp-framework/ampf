<?php

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
mb_internal_encoding("UTF-8");

// require composer autoloader
require (__DIR__ . '/vendor/autoload.php');
// boot the applicationcontext and get merged config
$config = \ampf\ApplicationContext::boot(
	array(
		(realpath(__DIR__) . '/config/default.php'),
		(realpath(__DIR__) . '/config/cli.php'),
		(realpath(__DIR__) . '/config/local.php'),
	)
);

// set up our beanfactory
$beanFactory = new \ampf\beans\DefaultBeanFactory($config);

// Get the doctrine entity manager
$em = $beanFactory->get('EntityManagerFactory')->get();
/* @var $em \ampf\doctrine\EntityManager */

return \Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet($em);
