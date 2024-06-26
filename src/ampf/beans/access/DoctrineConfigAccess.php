<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\doctrine\Config;

trait DoctrineConfigAccess
{
    protected ?Config $__doctrineConfig = null;

    abstract public function getBeanFactory(): BeanFactory;

    public function getDoctrineConfig(): Config
    {
        if ($this->__doctrineConfig === null) {
            $doctrineConfig = $this->getBeanFactory()->get('DoctrineConfig');
            assert($doctrineConfig instanceof Config);
            $this->setDoctrineConfig($doctrineConfig);
        }
        assert($this->__doctrineConfig !== null);

        return $this->__doctrineConfig;
    }

    public function setDoctrineConfig(Config $config): void
    {
        $this->__doctrineConfig = $config;
    }
}
