<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Doctrine;

use ampf\BeanAccess\AbstractAccess;
use ampf\Doctrine\DoctrineConfigInterface;

trait DoctrineConfigAccess
{
    use AbstractAccess;

    protected ?DoctrineConfigInterface $__doctrineConfig = null;

    public function getDoctrineConfig(): DoctrineConfigInterface
    {
        if ($this->__doctrineConfig === null) {
            $object = $this->getBeanFactory()->get(DoctrineConfigInterface::class);
            assert($object instanceof DoctrineConfigInterface);
            $this->setDoctrineConfig($object);
        }

        assert($this->__doctrineConfig instanceof DoctrineConfigInterface);

        return $this->__doctrineConfig;
    }

    public function setDoctrineConfig(DoctrineConfigInterface $object): void
    {
        $this->__doctrineConfig = $object;
    }
}
