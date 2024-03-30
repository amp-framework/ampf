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

    /**
     * @var ?array<string, mixed>
     */
    protected ?array $_config = null;

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        if ($this->_config === null) {
            $config = $this->getBeanFactory()->get('Config');

            if (!is_array($config) || !isset($config['doctrine']) || !is_array($config['doctrine'])) {
                throw new RuntimeException();
            }
            $this->setConfig($config);
        }
        assert($this->_config !== null);

        return $this->_config;
    }

    public function getConfiguration(): Configuration
    {
        $config = $this->getConfigValue('configuration');
        assert($config instanceof Configuration);

        return $config;
    }

    /**
     * @return array{
     *     'driver': 'pdo_mysql',
     *     'host': string,
     *     'user': string,
     *     'password': string,
     *     'dbname': string,
     *     'charset': string,
     *     'driverOptions': array<string, string>,
     * }
     */
    public function getConnectionParams(): array
    {
        /** @phpstan-ignore-next-line */
        return $this->getConfigValue('connectionParams');
    }

    /**
     * @return array<string, mixed>
     */
    public function getMappingOverrides(): array
    {
        /** @phpstan-ignore-next-line */
        return $this->getConfigValue('mappingOverrides');
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeOverrides(): array
    {
        /** @phpstan-ignore-next-line */
        return $this->getConfigValue('typeOverrides');
    }

    /**
     * @param array{doctrine: ?array<string, mixed>} $config
     */
    public function setConfig(array $config): void
    {
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
