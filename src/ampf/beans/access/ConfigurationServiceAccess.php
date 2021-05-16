<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\services\configuration\ConfigurationService;

trait ConfigurationServiceAccess
{
    protected ?ConfigurationService $__configurationService = null;

    public function getConfigurationService(): ConfigurationService
    {
        if ($this->__configurationService === null) {
            $this->setConfigurationService($this->getBeanFactory()->get('ConfigurationService'));
        }

        return $this->__configurationService;
    }

    public function setConfigurationService(ConfigurationService $configurationService): void
    {
        $this->__configurationService = $configurationService;
    }

    abstract public function getBeanFactory(): BeanFactory;
}
