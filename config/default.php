<?php

declare(strict_types=1);

use ampf\doctrine\impl\DefaultConfig;
use ampf\doctrine\impl\DefaultEntityManagerFactory;
use ampf\doctrine\types\UTCDateTimeType;
use ampf\services\cache\string\impl\FileBased;
use ampf\services\configuration\impl\DefaultConfigurationService;
use ampf\services\hasher\impl\DefaultHasherService;
use ampf\services\session\impl\DefaultSessionService;
use ampf\services\timel10n\impl\DefaultTimeL10nService;
use ampf\services\translator\impl\DefaultTranslatorService;
use ampf\services\xsrfToken\impl\DefaultXsrfTokenService;
use ampf\views\impl\DefaultViewResolver;
use Doctrine\ORM\ORMSetup;

return [
    'beans' => [
        /**
         * Database stuff
         */
        'DoctrineConfig' => [
            'class' => DefaultConfig::class,
        ],
        'EntityManagerFactory' => [
            'class' => DefaultEntityManagerFactory::class,
            'initMethod' => 'init',
        ],

        /**
         * View stuff
         */
        'ViewResolver' => [
            'class' => DefaultViewResolver::class,
        ],

        /**
         * Services
         */
        'ConfigurationService' => [
            'class' => DefaultConfigurationService::class,
            'properties' => [
                'Config' => 'config',
            ],
        ],
        'HasherService' => [
            'class' => DefaultHasherService::class,
        ],
        'StringCacheService' => [
            'class' => FileBased::class,
            'properties' => [
                'Config' => 'config',
            ],
        ],
        'SessionService' => [
            'class' => DefaultSessionService::class,
        ],
        'TimeL10nService' => [
            'class' => DefaultTimeL10nService::class,
        ],
        'TranslatorService' => [
            'class' => DefaultTranslatorService::class,
        ],
        'XsrfTokenService' => [
            'class' => DefaultXsrfTokenService::class,
        ],
    ],

    'routes' => [],

    // This should be overriden by the unversioned local.php config file
    'doctrine' => [
        'configuration' => ORMSetup::createAttributeMetadataConfiguration(
            [], // Entity paths
            true, // Is dev mode?
            null, // Proxy directory
            null, // Cache, instance of \Doctrine\Common\Cache\Cache
        ),
        'connectionParams' => [
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'user' => 'user',
            'password' => '',
            'dbname' => 'ampf',
            'charset' => 'utf8mb4',
            'driverOptions' => [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = 'UTC';",
            ],
        ],
        'typeOverrides' => [
            'datetime' => UTCDateTimeType::class,
            'datetimetz' => UTCDateTimeType::class,
        ],
        'mappingOverrides' => [
            'enum' => 'string',
        ],
    ],

    'translation.dir' => null,

    'stringfilecache' => [
        'cachedir' => null,
        'defaultttl' => null,
    ],

    'configuration.service' => [
        '.ampf' => [],
    ],
];
