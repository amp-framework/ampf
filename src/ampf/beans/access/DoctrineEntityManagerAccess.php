<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use Doctrine\ORM\EntityManagerInterface;

trait DoctrineEntityManagerAccess
{
    protected ?EntityManagerInterface $__doctrineEntityManager = null;

    abstract public function getBeanFactory(): BeanFactory;

    public function getDoctrineEntityManager(): EntityManagerInterface
    {
        if ($this->__doctrineEntityManager === null) {
            $this->setDoctrineEntityManager(
                $this->getBeanFactory()->get('EntityManagerFactory')->get(),
            );
        }

        return $this->__doctrineEntityManager;
    }

    public function setDoctrineEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->__doctrineEntityManager = $entityManager;
    }
}
