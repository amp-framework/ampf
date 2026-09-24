<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Doctrine;

use ampf\BeanAccess\AbstractAccess;
use ampf\Doctrine\EntityManagerFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/** The entity manager of the EntityManagerFactoryInterface bean: one per bean factory, created at its first use. */
trait DoctrineEntityManagerAccess
{
    use AbstractAccess;

    protected ?EntityManagerInterface $__doctrineEntityManager = null;

    public function getDoctrineEntityManager(): EntityManagerInterface
    {
        if ($this->__doctrineEntityManager === null) {
            $object = $this->getBeanFactory()->get(EntityManagerFactoryInterface::class);
            assert($object instanceof EntityManagerFactoryInterface);
            $this->setDoctrineEntityManager($object->get());
        }

        assert($this->__doctrineEntityManager instanceof EntityManagerInterface);

        return $this->__doctrineEntityManager;
    }

    public function setDoctrineEntityManager(EntityManagerInterface $object): void
    {
        $this->__doctrineEntityManager = $object;
    }
}
