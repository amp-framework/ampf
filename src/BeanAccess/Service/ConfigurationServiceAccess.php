<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Service;

use ampf\BeanAccess\AbstractAccess;
use ampf\Service\Configuration\ConfigurationServiceInterface;

trait ConfigurationServiceAccess
{
    use AbstractAccess;

    protected ?ConfigurationServiceInterface $__configurationService = null;

    public function getConfigurationService(): ConfigurationServiceInterface
    {
        if ($this->__configurationService === null) {
            $object = $this->getBeanFactory()->get(ConfigurationServiceInterface::class);
            assert($object instanceof ConfigurationServiceInterface);
            $this->setConfigurationService($object);
        }

        assert($this->__configurationService instanceof ConfigurationServiceInterface);

        return $this->__configurationService;
    }

    public function setConfigurationService(ConfigurationServiceInterface $object): void
    {
        $this->__configurationService = $object;
    }
}
