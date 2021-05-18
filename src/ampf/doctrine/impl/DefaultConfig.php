<?php

declare(strict_types=1);

namespace ampf\doctrine\impl;

use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\doctrine\Config;
use Doctrine\ORM\Configuration;
use RuntimeException;

class DefaultConfig implements BeanFactoryAccess, Config
{
    use DefaultBeanFactoryAccess;

    /** @var ?array<string, mixed> */
    protected ?array $_config = null;

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        if ($this->_config === null) {
            $this->setConfig($this->getBeanFactory()->get('Config'));
        }
        assert($this->_config !== null);

        return $this->_config;
    }

    public function getConfiguration(): Configuration
    {
        return $this->getConfigValue('configuration');
    }

    /** @return array<string, mixed> */
    public function getConnectionParams(): array
    {
        return $this->getConfigValue('connectionParams');
    }

    /** @return array<string, mixed> */
    public function getMappingOverrides(): array
    {
        return $this->getConfigValue('mappingOverrides');
    }

    /** @return array<string, mixed> */
    public function getTypeOverrides(): array
    {
        return $this->getConfigValue('typeOverrides');
    }

    /** @param ?array{doctrine: array<string, mixed>} $config */
    public function setConfig(?array $config = null): void
    {
        if ($config === null) {
            throw new RuntimeException();
        }

        if (count($config['doctrine'] ?? []) < 1) {
            throw new RuntimeException();
        }

        $this->_config = $config['doctrine'];
    }

    protected function getConfigValue(string $value): mixed
    {
        $config = $this->getConfig();
        if (!isset($config[$value])) {
            return null;
        }

        return $config[$value];
    }
}
