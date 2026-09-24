<?php

declare(strict_types=1);

use ampf\Bootstrap\DoctrineConfiguration;
use ampf\Doctrine\DoctrineConfig;
use ampf\Doctrine\DoctrineConfigInterface;
use ampf\Doctrine\EntityManagerFactory;
use ampf\Doctrine\EntityManagerFactoryInterface;
use ampf\Doctrine\Type\UTCDateTimeType;
use ampf\Router\RouteResolver;
use ampf\Router\RouteResolverInterface;
use ampf\Service\Configuration\ConfigurationService;
use ampf\Service\Configuration\ConfigurationServiceInterface;
use ampf\Service\Hasher\HasherService;
use ampf\Service\Hasher\HasherServiceInterface;
use ampf\Service\Session\SessionService;
use ampf\Service\Session\SessionServiceInterface;
use ampf\Service\StringCache\FileStringCacheService;
use ampf\Service\StringCache\StringCacheServiceInterface;
use ampf\Service\TimeL10n\TimeL10nService;
use ampf\Service\TimeL10n\TimeL10nServiceInterface;
use ampf\Service\Translator\TranslatorService;
use ampf\Service\Translator\TranslatorServiceInterface;
use ampf\Service\XsrfToken\XsrfTokenService;
use ampf\Service\XsrfToken\XsrfTokenServiceInterface;
use ampf\View\ViewResolver;
use ampf\View\ViewResolverInterface;
use Pdo\Mysql;

/*
 * The framework's defaults, the first file every entry point loads (then config/http.php or config/cli.php, then the
 * application's own files). The files are merged one level deep (ApplicationContext::boot()): a later file replaces
 * one bean or one route as a whole, and inside the other blocks one key as a whole.
 *
 * A service is keyed by its interface; an application replaces it by configuring another class under the same key.
 */
return [
    'beans' => [
        /**
         * Doctrine
         */
        DoctrineConfigInterface::class => ['class' => DoctrineConfig::class],
        EntityManagerFactoryInterface::class => ['class' => EntityManagerFactory::class, 'initMethod' => 'init'],

        /**
         * Routes and templates
         */
        RouteResolverInterface::class => ['class' => RouteResolver::class, 'properties' => ['Config' => 'config']],
        ViewResolverInterface::class => ['class' => ViewResolver::class],

        /**
         * Services
         */
        ConfigurationServiceInterface::class => [
            'class' => ConfigurationService::class,
            'properties' => ['Config' => 'config'],
        ],
        HasherServiceInterface::class => ['class' => HasherService::class],
        SessionServiceInterface::class => ['class' => SessionService::class],
        StringCacheServiceInterface::class => [
            'class' => FileStringCacheService::class,
            'properties' => ['Config' => 'config'],
        ],
        TimeL10nServiceInterface::class => ['class' => TimeL10nService::class],
        TranslatorServiceInterface::class => ['class' => TranslatorService::class],
        XsrfTokenServiceInterface::class => ['class' => XsrfTokenService::class],
    ],

    // The routes (route id => pattern and controller bean): the application's http.php and cli.php name them
    'routes' => [],

    // The database: an application names its configuration (DoctrineConfiguration::create() with its entity
    // directories) and its connection in an unversioned local file
    'doctrine' => [
        'configuration' => DoctrineConfiguration::create([]),
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
        // Every datetime is stored in UTC
        'typeOverrides' => [
            'datetime' => UTCDateTimeType::class,
            'datetimetz' => UTCDateTimeType::class,
        ],
        // Database types read as DBAL types (the platform's type mappings); an application may set []
        'mappingOverrides' => [
            'enum' => 'string',
        ],
    ],

    // The directory of the translation files (TranslatorService: <language>.php)
    'translation.dir' => null,

    // PHP's error handling, which an entry point applies once the configuration is merged (ErrorSettings::apply()):
    // errors shown in the output (a development machine only), written to the log, and the log's file — relative to
    // the project root, or absolute; null leaves PHP's own
    'errors' => [
        'display' => false,
        'log' => true,
        'log-file' => null,
    ],

    // The attributes HttpRequest::setCookieParam() gives a cookie unless the call names its own. secure: true,
    // false, or null for "exactly when the request came over https" (the web server's HTTPS variable).
    'cookies' => [
        'path' => '/',
        'domain' => '',
        'secure' => null,
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    // The session (SessionService), applied before the session starts: its cookie's attributes (secure as above) and
    // strict mode (an id the server never issued is replaced, not adopted). The id travels in the cookie only.
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
    ],

    // The string cache (FileStringCacheService): its directory, the default time to live in seconds (an hour when
    // null), and whether it is on — a development machine switches it off, so a cached page hides no change.
    'stringfilecache' => [
        'cachedir' => null,
        'defaultttl' => null,
        'enabled' => true,
    ],

    // The application's settings by domain (ConfigurationService): '.app' => [...], '.app.de' => [...]. A later file
    // replaces a whole domain, so an override repeats every key the domain needs.
    'configuration.service' => [
        '.ampf' => [],
    ],
];
