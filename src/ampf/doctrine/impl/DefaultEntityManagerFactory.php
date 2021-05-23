<?php

declare(strict_types=1);

namespace ampf\doctrine\impl;

use ampf\beans\access\DoctrineConfigAccess;
use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\doctrine\EntityManagerFactory;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;

class DefaultEntityManagerFactory implements BeanFactoryAccess, EntityManagerFactory
{
    use DefaultBeanFactoryAccess;
    use DoctrineConfigAccess;

    protected ?EntityManager $_em = null;

    public function init(): void
    {
        $doctrine = $this->getDoctrineConfig();

        foreach ($doctrine->getTypeOverrides() as $type => $override) {
            /** @var class-string<\Doctrine\DBAL\Types\Type> $type */
            /** @var class-string<\Doctrine\DBAL\Types\Type> $override */
            Type::overrideType($type, $override);
        }

        $this->_em = EntityManager::create(
            $doctrine->getConnectionParams(),
            $doctrine->getConfiguration(),
        );

        if (count($doctrine->getMappingOverrides()) > 0) {
            $platform = $this->_em->getConnection()->getDatabasePlatform();
            foreach ($doctrine->getMappingOverrides() as $mapping => $override) {
                /** @var string $override */
                $platform->registerDoctrineTypeMapping($mapping, $override);
            }
        }
    }

    public function get(): ?EntityManager
    {
        return $this->_em;
    }
}
