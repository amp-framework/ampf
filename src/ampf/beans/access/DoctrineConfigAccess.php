<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\doctrine\Config;

trait DoctrineConfigAccess
{
    protected ?Config $__doctrineConfig = null;

    public function getDoctrineConfig(): Config
    {
        if ($this->__doctrineConfig === null) {
            $this->setDoctrineConfig($this->getBeanFactory()->get('DoctrineConfig'));
        }
        assert($this->__doctrineConfig !== null);

        return $this->__doctrineConfig;
    }

    public function setDoctrineConfig(Config $config): void
    {
        $this->__doctrineConfig = $config;
    }

    abstract public function getBeanFactory(): BeanFactory;
}
