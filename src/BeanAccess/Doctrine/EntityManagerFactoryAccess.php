<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Doctrine;

use ampf\BeanAccess\AbstractAccess;
use ampf\Doctrine\EntityManagerFactoryInterface;

trait EntityManagerFactoryAccess
{
    use AbstractAccess;

    protected ?EntityManagerFactoryInterface $__entityManagerFactory = null;

    public function getEntityManagerFactory(): EntityManagerFactoryInterface
    {
        if ($this->__entityManagerFactory === null) {
            $object = $this->getBeanFactory()->get(EntityManagerFactoryInterface::class);
            assert($object instanceof EntityManagerFactoryInterface);
            $this->setEntityManagerFactory($object);
        }

        assert($this->__entityManagerFactory instanceof EntityManagerFactoryInterface);

        return $this->__entityManagerFactory;
    }

    public function setEntityManagerFactory(EntityManagerFactoryInterface $object): void
    {
        $this->__entityManagerFactory = $object;
    }
}
