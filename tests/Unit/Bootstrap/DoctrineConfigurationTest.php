<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Bootstrap;

use ampf\Bootstrap\DoctrineConfiguration;
use ampf\Tests\Support\Doctrine\SampleEntity;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use FilesystemIterator;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The ORM configuration an application builds: with a cache directory the mapping is read once and kept there as PHP
 * files, which the cache creates itself, so the next request (another process) takes it from there; without one
 * nothing is kept; entities are PHP's native lazy objects either way, so no proxy class is generated. Over an
 * unopened SQLite connection: nothing here needs a database.
 */
#[CoversClass(DoctrineConfiguration::class)]
final class DoctrineConfigurationTest extends TestCase
{
    private string $directory;

    private static function entityDirectory(): string
    {
        return dirname(__DIR__, 2) . '/Support/Doctrine';
    }

    public function testACacheDirectoryKeepsTheMapping(): void
    {
        $this->entityManager(DoctrineConfiguration::create([self::entityDirectory()], $this->directory . '/doctrine'))
            ->getMetadataFactory()
            ->getAllMetadata()
        ;

        self::assertNotSame([], $this->files($this->directory . '/doctrine'));
    }

    public function testTheNextRequestTakesTheMappingFromTheCache(): void
    {
        $this->entityManager(DoctrineConfiguration::create([self::entityDirectory()], $this->directory))
            ->getMetadataFactory()
            ->getAllMetadata()
        ;

        $configuration = DoctrineConfiguration::create([self::entityDirectory()], $this->directory);
        $configuration->setMetadataDriverImpl(new class implements MappingDriver {
            // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- the interface's signature
            public function loadMetadataForClass(string $className, ClassMetadata $metadata): void
            {
                throw new LogicException('the mapping was read again: ' . $className);
            }

            /** @return list<class-string> */
            public function getAllClassNames(): array
            {
                throw new LogicException('the mapping was read again');
            }

            public function isTransient(string $className): bool
            {
                throw new LogicException('the mapping was read again: ' . $className);
            }
        });

        self::assertSame(
            'sample',
            $this->entityManager($configuration)->getClassMetadata(SampleEntity::class)->getTableName(),
        );
    }

    public function testWithoutACacheDirectoryNothingIsKept(): void
    {
        $this->entityManager(DoctrineConfiguration::create([self::entityDirectory()]))
            ->getMetadataFactory()
            ->getAllMetadata()
        ;

        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testEntitiesAreNativeLazyObjectsEitherWay(): void
    {
        self::assertTrue(DoctrineConfiguration::create([], $this->directory)->isNativeLazyObjectsEnabled());
        self::assertTrue(DoctrineConfiguration::create([])->isNativeLazyObjectsEnabled());
    }

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/ampf-doctrine-configuration-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            assert($entry instanceof SplFileInfo);
            $entry->isDir()
                ? rmdir($entry->getPathname())
                : unlink($entry->getPathname());
        }
        rmdir($this->directory);
    }

    private function entityManager(Configuration $configuration): EntityManager
    {
        return new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
            $configuration,
        );
    }

    /**
     * @return list<string> the files under the directory, none when it does not exist
     */
    private function files(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];

        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            ) as $entry
        ) {
            assert($entry instanceof SplFileInfo);
            $files[] = $entry->getPathname();
        }

        return $files;
    }
}
