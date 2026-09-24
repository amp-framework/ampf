<?php

declare(strict_types=1);

use ampf\Bootstrap\DoctrineConfiguration;

/*
 * The fixture application's configuration for both transports: its templates, texts and database. The database is
 * the SQLite file the environment's AMPF_FIXTURE_DATABASE names, or one in memory.
 */
$database = getenv('AMPF_FIXTURE_DATABASE');

return [
    'viewDirectory' => __DIR__ . '/../views',
    'translation.dir' => __DIR__ . '/../translations',
    'stringfilecache' => ['cachedir' => sys_get_temp_dir()],

    'doctrine' => [
        'configuration' => DoctrineConfiguration::create([__DIR__ . '/../Doctrine/Entity']),
        'connectionParams' => is_string($database) && $database !== ''
            ? ['driver' => 'pdo_sqlite', 'path' => $database]
            : ['driver' => 'pdo_sqlite', 'memory' => true],
    ],
];
