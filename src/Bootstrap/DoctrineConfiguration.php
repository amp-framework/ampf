<?php

declare(strict_types=1);

namespace ampf\Bootstrap;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

/**
 * The ORM configuration an application puts into `doctrine.configuration`: the attribute mapping of its entity
 * directories, and entities as PHP's native lazy objects — Doctrine generates no proxy classes, so there is no proxy
 * directory in any environment. With a cache directory (production) the mapping, the parsed queries and the cached
 * results are kept there as PHP files: created on first use, never expiring — empty the directory on every deploy.
 * Without one (development) nothing outlives the process, so an entity change shows at once.
 */
final class DoctrineConfiguration
{
    /**
     * @param list<string> $entityPaths the directories of the attribute-mapped entities
     */
    public static function create(array $entityPaths, ?string $cacheDirectory = null): Configuration
    {
        $configuration = ORMSetup::createAttributeMetadataConfig(
            paths: $entityPaths,
            isDevMode: $cacheDirectory === null,
            cache: $cacheDirectory === null
                ? null
                : new PhpFilesAdapter('orm', 0, $cacheDirectory),
        );
        $configuration->enableNativeLazyObjects(true);

        return $configuration;
    }
}
