<?php

declare(strict_types=1);

namespace ampf\beans\impl;

use ampf\beans\BeanFactory;
use ampf\beans\BeanFactoryAccess;
use RuntimeException;

/** @phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter */
class DefaultBeanFactory implements BeanFactory
{
    /** @var array<string, mixed> */
    protected array $memory = [];

    /** @var array<string, int> */
    protected array $statistics = [
        'beansCreated' => 0,
    ];

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->memory['BeanFactory'] = $this;
        $this->memory['Config'] = $config;
    }

    public function set(string $beanID, mixed $object): self
    {
        $this->memory[$beanID] = $object;

        return $this;
    }

    public function has(string $beanID): bool
    {
        if (isset($this->memory[$beanID])) {
            return true;
        }

        $config = $this->get('Config');

        return isset($config['beans'][$beanID]);
    }

    public function get(string $beanID, ?callable $creatorFunc = null): mixed
    {
        if (isset($this->memory[$beanID])) {
            $bean = $this->memory[$beanID];
        } else {
            $config = $this->get('Config');

            $beanConfig = null;
            $bean = null;
            if (isset($config['beans'][$beanID])) {
                $beanConfig = $config['beans'][$beanID];
                $_tclass = $beanConfig['class'];
                $bean = new $_tclass();
            } elseif (is_callable($creatorFunc)) {
                $beanConfig = [];
                $bean = $creatorFunc($this, $beanConfig);
            } else {
                throw new RuntimeException("No configuration for bean {$beanID} found");
            }

            $this->evalConfig($beanID, $bean, $beanConfig);
            $this->statistics['beansCreated']++;
        }

        return $bean;
    }

    public function is(mixed $object, string $beanID): bool
    {
        if (!is_object($object)) {
            return false;
        }

        $config = $this->get('Config');
        if (!isset($config['beans'][$beanID])) {
            return false;
        }

        $class = $config['beans'][$beanID]['class'];

        if (!class_exists($class)) {
            return false;
        }

        return $object instanceof $class;
    }

    /** @return array<string, int> */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /** @param array<string, mixed> $beanConfig */
    protected function evalConfig(string $beanID, mixed $bean, array $beanConfig): self
    {
        return $this
            ->evalConfigParent($beanID, $bean, $beanConfig)
            ->evalConfigProperties($beanID, $bean, $beanConfig)
            ->evalConfigInitMethod($beanID, $bean, $beanConfig)
            ->evalConfigScope($beanID, $bean, $beanConfig)
        ;
    }

    /** @param array<string, mixed> $beanConfig */
    protected function evalConfigScope(string $beanID, mixed $bean, array $beanConfig): self
    {
        if (trim($beanID) === '') {
            return $this;
        }

        $scope = 'singleton';
        if (isset($beanConfig['scope'])) {
            $scope = $beanConfig['scope'];
        }

        if ($scope === 'prototype') {
            // do nothing
        } else {
            // handle as if scope == 'singleton'
            $this->memory[$beanID] = $bean;
        }

        return $this;
    }

    /** @param array<string, mixed> $beanConfig */
    protected function evalConfigInitMethod(string $beanID, mixed $bean, array $beanConfig): self
    {
        if (isset($beanConfig['initMethod'])) {
            $initMethod = $beanConfig['initMethod'];
            $bean->{$initMethod}();
        }

        return $this;
    }

    /** @param array<string, mixed> $beanConfig */
    protected function evalConfigProperties(string $beanID, mixed $bean, array $beanConfig): self
    {
        if (isset($beanConfig['properties'])) {
            foreach ($beanConfig['properties'] as $bean2ID => $field) {
                $setter = ('set' . ucfirst($field));
                if (method_exists($bean, $setter)) {
                    $bean->{$setter}($this->get($bean2ID));
                } else {
                    throw new RuntimeException("Property {$field} can not be set due to missing setter");
                }
            }
        }

        // Inject us, if wanted. This is done through an interface and not through
        // the normal properties configuration, as this is needed way too much.
        if ($bean instanceof BeanFactoryAccess) {
            $bean->setBeanFactory($this);
        }

        return $this;
    }

    /** @param array<string, mixed> $beanConfig */
    protected function evalConfigParent(string $beanID, mixed $bean, array $beanConfig): self
    {
        if (isset($beanConfig['parent'])) {
            $parent = $beanConfig['parent'];
            $config = $this->get('Config');
            if (!isset($config['beans'][$parent])) {
                throw new RuntimeException();
            }
            $parentConfig = $config['beans'][$parent];
            $this->evalConfig('', $bean, $parentConfig);
        }

        return $this;
    }
}
