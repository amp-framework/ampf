<?php

declare(strict_types=1);

namespace ampf\Doctrine;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\BeanAccess\Doctrine\DoctrineConfigAccess;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates the entity manager from the DoctrineConfigInterface bean: the DBAL type overrides (the framework's UTC
 * datetimes), the connection, the entity manager over the ORM configuration, and the platform's type mappings (a
 * database type read as a DBAL type). Nothing connects to the database before the entity manager is asked for.
 */
class EntityManagerFactory implements BeanFactoryAccessInterface, EntityManagerFactoryInterface
{
    use BeanFactoryAccess;
    use DoctrineConfigAccess;

    protected ?EntityManagerInterface $entityManager = null;

    /** Creates the entity manager once (the bean's initMethod); later calls keep it. */
    public function init(): void
    {
        $this->entityManager ??= $this->createEntityManager();
    }

    public function get(): EntityManagerInterface
    {
        return $this->entityManager ??= $this->createEntityManager();
    }

    protected function createEntityManager(): EntityManagerInterface
    {
        $doctrine = $this->getDoctrineConfig();

        foreach ($doctrine->getTypeOverrides() as $type => $override) {
            /** @var class-string<\Doctrine\DBAL\Types\Type> $override */
            Type::overrideType($type, $override);
        }

        $connection = DriverManager::getConnection(
            $doctrine->getConnectionParams(),
            $doctrine->getConfiguration(),
        );

        $entityManager = new EntityManager(
            $connection,
            $doctrine->getConfiguration(),
        );

        // @phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
        if (count($doctrine->getMappingOverrides()) > 0) {
            $platform = $entityManager->getConnection()->getDatabasePlatform();

            foreach ($doctrine->getMappingOverrides() as $mapping => $override) {
                /** @var string $override */
                $platform->registerDoctrineTypeMapping($mapping, $override);
            }
        }

        return $entityManager;
    }
}
