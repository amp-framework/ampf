<?php

return array(

	'doctrine' => array(
		'cacheDir' => (__DIR__ . '/../cache/doctrine/'),
		'connectionParams' => array(
			'driver' => 'pdo_mysql',
			'host' => 'localhost',
			'user' => 'continga',
			'password' => '',
			'dbname' => 'test',
			'charset' => 'utf8mb4',
			'driverOptions' => array(),
		),
		'entities' => array(
			__DIR__ . '/../src/ampf/doctrine/entities/'
		),
		'isDevMode' => true,
		'proxyDir' => null,
		'useSimpleAnnotationReader' => false,
	),
);
