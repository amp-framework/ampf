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
use Pdo\Mysql;

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
            null, // Cache, instance of \Psr\Cache\CacheItemPoolInterface
        ),
        'connectionParams' => [
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'user' => 'user',
            'password' => '',
            'dbname' => 'ampf',
            'charset' => 'utf8mb4',
            'driverOptions' => [
                Mysql::ATTR_INIT_COMMAND => "SET time_zone = 'UTC';",
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

    // The attributes DefaultHttp::setCookieParam() gives a cookie unless the call names its own. secure: true,
    // false, or null for "exactly when the request came over https" (the web server's HTTPS variable).
    'cookies' => [
        'path' => '/',
        'domain' => '',
        'secure' => null,
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    // The session (DefaultSessionService), applied before the session starts: its cookie's attributes (secure as
    // above), strict mode (an id the server never issued is replaced, not adopted) and cookies only.
    'session' => [
        'cookie' => [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => null,
            'httponly' => true,
            'samesite' => 'Lax',
        ],
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ],

    'stringfilecache' => [
        'cachedir' => null,
        'defaultttl' => null,
    ],

    'configuration.service' => [
        '.ampf' => [],
    ],
];
