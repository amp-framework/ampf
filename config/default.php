<?php

return array(

	'beans' => array(
		/**
		 * Database stuff
		 */
		'DatabaseFactory' => array(
			'class' => '\ampf\database\factories\PDOMySQL',
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),
		'DatabaseConfig' => array(
			'class' => '\ampf\database\DefaultConfig',
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),
		'AbstractMapper' => array(
			'class' => '\ampf\database\mapper\AbstractMapper',
			'abstract' => true,
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),
		'TestMapper' => array(
			'class' => '\ampf\database\mapper\TestMapper',
			'parent' => 'AbstractMapper',
		),
		'TestModel' => array(
			'class' => '\ampf\database\models\TestModel',
			'scope' => 'prototype',
		),

		/**
		 * View stuff
		 */
		'ViewResolver' => array(
			'class' => '\ampf\views\impl\DefaultViewResolver',
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),
		'AbstractView' => array(
			'abstract' => true,
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),

		/**
		 * Services
		 */
		'HasherService' => array(
			'class' => '\ampf\services\hasher\impl\DefaultHasherService',
		),
		'StringCacheService' => array(
			'class' => '\ampf\services\cache\string\impl\FileBased',
			'properties' => array(
				'Config' => 'config',
			),
		),
		'SessionService' => array(
			'class' => '\ampf\services\session\impl\DefaultSessionService',
		),
		'TimeL10nService' => array(
			'class' => '\ampf\services\timel10n\impl\DefaultTimeL10nService',
		),
		'TranslatorService' => array(
			'class' => '\ampf\services\translator\impl\DefaultTranslatorService',
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),
		'XsrfTokenService' => array(
			'class' => '\ampf\services\xsrfToken\impl\DefaultXsrfTokenService',
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),
	),

	'routes' => array(
	),

	// This should be overriden by the unversioned local.php config file
	'database' => array(
		'host' => 'host',
		'username' => 'username',
		'password' => 'password',
		'dbname' => 'dbname',
		'port' => ini_get("mysqli.default_port"),
		'socket' => ini_get("mysqli.default_socket"),
	),

	'translation.dir' => realpath(__DIR__ . '/translations/'),

	'stringfilecache' => array(
		'cachedir' => realpath(__DIR__ . '/../cache/'),
		'defaultttl' => 3600,
	),
);
