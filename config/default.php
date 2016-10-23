<?php

return array(

	'beans' => array(
		/**
		 * Database stuff
		 */
		'DoctrineConfig' => array(
			'class' => '\ampf\doctrine\impl\DefaultConfig',
		),
		'EntityManagerFactory' => array(
			'class' => '\ampf\doctrine\impl\DefaultEntityManagerFactory',
			'initMethod' => 'init',
		),

		/**
		 * View stuff
		 */
		'ViewResolver' => array(
			'class' => '\ampf\views\impl\DefaultViewResolver',
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
		),
		'XsrfTokenService' => array(
			'class' => '\ampf\services\xsrfToken\impl\DefaultXsrfTokenService',
		),
	),

	'routes' => array(
	),

	// This should be overriden by the unversioned local.php config file
	'doctrine' => array(
		'cacheDir' => null,
		'connectionParams' => array(
			'driver' => null,
			'host' => null,
			'user' => null,
			'password' => null,
			'dbname' => null,
			'charset' => null,
			'driverOptions' => null,
		),
		'entities' => array(
			null,
		),
		'isDevMode' => null,
		'proxyDir' => null,
		'useSimpleAnnotationReader' => null,
	),

	'translation.dir' => null,

	'stringfilecache' => array(
		'cachedir' => null,
		'defaultttl' => null,
	),

	'configuration.service' => array(
		'.ampf' => array(),
	),
);
