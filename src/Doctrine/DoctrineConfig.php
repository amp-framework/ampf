<?php

declare(strict_types=1);

namespace ampf\Doctrine;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use Doctrine\ORM\Configuration;
use RuntimeException;

/**
 * The merged configuration's `doctrine` block: the ORM configuration object, the connection parameters, the DBAL
 * type overrides and the platform's type mappings.
 *
 * @phpstan-import-type Params from \Doctrine\DBAL\DriverManager
 */
class DoctrineConfig implements BeanFactoryAccessInterface, DoctrineConfigInterface
{
    use BeanFactoryAccess;

    /**
     * @var ?array<string, mixed>
     */
    protected ?array $config = null;

    /**
     * The `doctrine` block of the bean 'Config', unless one was set.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the configuration has no `doctrine` block
     */
    public function getConfig(): array
    {
        return $this->config ??= $this->blockOf($this->getBeanFactory()->getConfig());
    }

    /**
     * @throws RuntimeException when the block holds no ORM configuration
     */
    public function getConfiguration(): Configuration
    {
        $configuration = $this->getConfigValue('configuration');

        if (!$configuration instanceof Configuration) {
            throw new RuntimeException(
                'The configuration\'s doctrine.configuration must be a ' . Configuration::class
                . ' (DoctrineConfiguration::create()), not ' . get_debug_type($configuration) . '.',
            );
        }

        return $configuration;
    }

    /**
     * @return Params
     *
     * @throws RuntimeException when the block's connectionParams are no array
     */
    public function getConnectionParams(): array
    {
        // @phpstan-ignore return.type (the configuration's parameters: DBAL checks them when it connects)
        return $this->getArrayValue('connectionParams');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the block's mappingOverrides are no array
     */
    public function getMappingOverrides(): array
    {
        return $this->getArrayValue('mappingOverrides');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the block's typeOverrides are no array
     */
    public function getTypeOverrides(): array
    {
        return $this->getArrayValue('typeOverrides');
    }

    /**
     * @param array{doctrine: array<mixed, mixed>} $config
     *
     * @throws RuntimeException for a block that is empty, or not keyed by strings
     */
    public function setConfig(array $config): void
    {
        $this->config = $this->blockOf($config);
    }

    /**
     * The configuration's `doctrine` block, checked.
     *
     * @param array<mixed> $config
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException for a block that is missing, empty, or not keyed by strings
     */
    protected function blockOf(array $config): array
    {
        $block = $config['doctrine'] ?? null;

        if (!is_array($block) || $block === []) {
            throw new RuntimeException('The configuration has no doctrine block.');
        }

        foreach (array_keys($block) as $key) {
            if (!is_string($key)) {
                throw new RuntimeException(
                    'The configuration\'s doctrine block must be keyed by names, not ' . $key . '.',
                );
            }
        }

        /** @var array<string, mixed> $block */
        return $block;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException for a value that is no array keyed by strings
     */
    protected function getArrayValue(string $key): array
    {
        $value = $this->getConfigValue($key);

        if (!is_array($value)) {
            throw new RuntimeException(
                'The configuration\'s doctrine.' . $key . ' must be an array, not ' . get_debug_type($value) . '.',
            );
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    protected function getConfigValue(string $value): mixed
    {
        return $this->getConfig()[$value] ?? null;
    }
}
