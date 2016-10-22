<?php

return array(

	'beans' => array(
		/**
		 * Database stuff
		 */
		'DoctrineConfig' => array(
			'class' => '\ampf\doctrine\impl\DefaultConfig',
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),
		'EntityManagerFactory' => array(
			'class' => '\ampf\doctrine\impl\DefaultEntityManagerFactory',
			'initMethod' => 'init',
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
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
		'ConfigurationService' => array(
			'class' => '\ampf\services\configuration\impl\DefaultConfigurationService',
			'properties' => array(
				'Config' => 'config',
			),
		),
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
	'doctrine' => array(
		'cacheDir' => (__DIR__ . '/../cache/doctrine/'),
		'connectionParams' => array(
			'driver' => 'pdo_mysql',
			'host' => 'host',
			'user' => 'user',
			'password' => 'password',
			'dbname' => 'dbname',
			'charset' => 'utf8mb4',
			'driverOptions' => array(),
		),
		'entities' => array(
			__DIR__ . '/../src/ampf/doctrine/entities/'
		),
		'isDevMode' => false,
		'proxyDir' => null,
		'useSimpleAnnotationReader' => false,
	),

	'translation.dir' => realpath(__DIR__ . '/translations/'),

	'stringfilecache' => array(
		'cachedir' => realpath(__DIR__ . '/../cache/'),
		'defaultttl' => 3600,
	),

	'configuration.service' => array(
		'.ampf' => array(),
	),
);
