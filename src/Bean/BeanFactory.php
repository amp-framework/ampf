<?php

declare(strict_types=1);

namespace ampf\Bean;

use ampf\Helper\Functions;
use ReflectionClass;
use RuntimeException;

/**
 * The bean factory over the merged configuration's `beans`: a bean is created with `new` (no constructor arguments),
 * then configured — its parent's configuration, the `properties` (bean id => setter suffix), the factory itself for
 * a BeanFactoryAccessInterface, the `initMethod` — and kept as a singleton unless its `scope` is `prototype`. The
 * factory is its own bean 'BeanFactory', the merged configuration the bean 'Config'.
 *
 * A definition is checked when its bean is first created: an unknown option, a class that does not exist or cannot
 * be instantiated, an unknown scope, a missing setter or init method and a cycle of parents are refused with a
 * message that names the bean.
 */
class BeanFactory implements BeanFactoryInterface
{
    /**
     * The options a bean definition may name.
     */
    protected const array OPTIONS = ['class', 'scope', 'properties', 'initMethod', 'parent'];

    protected const string SINGLETON = 'singleton';
    protected const string PROTOTYPE = 'prototype';

    /**
     * @var array<string, mixed>
     */
    protected array $memory = [];

    /**
     * @var array<string, int>
     */
    protected array $statistics = [
        'beansCreated' => 0,
    ];

    /**
     * @param array<string, mixed> $config
     */
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
        return isset($this->memory[$beanID]) || isset($this->getDefinitions()[$beanID]);
    }

    public function get(string $beanID, ?callable $creatorFunc = null): mixed
    {
        if (isset($this->memory[$beanID])) {
            return $this->memory[$beanID];
        }

        $definition = $this->getDefinition($beanID);

        if ($definition !== null) {
            $class = $definition['class'];
            $bean = new $class();
        } elseif ($creatorFunc !== null) {
            $definition = [];
            $bean = $creatorFunc($this, $definition);
        } else {
            throw new RuntimeException('No configuration for bean ' . $beanID . ' found.');
        }

        if (is_object($bean)) {
            $this->configure($beanID, $bean, $definition, [$beanID]);
        }

        if (($definition['scope'] ?? static::SINGLETON) === static::SINGLETON) {
            $this->memory[$beanID] = $bean;
        }

        $this->statistics['beansCreated']++;

        return $bean;
    }

    /**
     * The merged configuration: the bean 'Config'.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the bean 'Config' was replaced by something else than the configuration
     */
    public function getConfig(): array
    {
        $config = $this->memory['Config'] ?? null;

        try {
            Functions::assertStringMixedArray($config);
        } catch (RuntimeException $e) {
            throw new RuntimeException('The bean Config is no configuration: ' . $e->getMessage(), previous: $e);
        }

        return $config;
    }

    public function is(mixed $object, string $beanID): bool
    {
        $definition = $this->getDefinition($beanID);

        return $definition !== null && $object instanceof $definition['class'];
    }

    /**
     * @return array<string, int>
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /**
     * Applies the definition to the new bean: first its parent's (and so on up the chain), then its properties, the
     * factory for a BeanFactoryAccessInterface, and its init method.
     *
     * @param array<string, mixed> $definition a checked definition, or [] for a bean of a creator function
     * @param list<string> $chain the bean and the parents whose definitions are being applied, for the cycle check
     */
    protected function configure(string $beanID, object $bean, array $definition, array $chain): void
    {
        $parent = $definition['parent'] ?? null;

        if (is_string($parent)) {
            if (in_array($parent, $chain, true)) {
                throw new RuntimeException(
                    'The bean ' . $chain[0] . ' has a cycle of parents: ' . implode(' > ', [...$chain, $parent]) . '.',
                );
            }

            $this->configure(
                $parent,
                $bean,
                $this->getDefinition($parent) ?? throw new RuntimeException(
                    'The bean ' . $beanID . ' names the parent ' . $parent . ', which has no configuration.',
                ),
                [...$chain, $parent],
            );
        }

        /** @var array<string, string> $properties */
        $properties = $definition['properties'] ?? [];

        foreach ($properties as $dependency => $suffix) {
            $setter = 'set' . ucfirst($suffix);

            if (!method_exists($bean, $setter)) {
                throw new RuntimeException(
                    'The bean ' . $beanID . ' cannot take the bean ' . $dependency . ': ' . $bean::class
                    . ' has no method ' . $setter . '().',
                );
            }

            $bean->{$setter}($this->get($dependency));
        }

        // The factory itself goes to every bean that asks for it through the interface: nearly every bean does
        if ($bean instanceof BeanFactoryAccessInterface) {
            $bean->setBeanFactory($this);
        }

        $initMethod = $definition['initMethod'] ?? null;

        if (!is_string($initMethod)) {
            return;
        }

        if (!is_callable([$bean, $initMethod])) {
            throw new RuntimeException(
                'The bean ' . $beanID . ' names the init method ' . $initMethod . '(), which ' . $bean::class
                . ' does not have as a public method.',
            );
        }

        $bean->{$initMethod}();
    }

    /**
     * The bean's checked definition, null when the configuration has none.
     *
     * @return ?array{class: class-string, scope?: string, properties?: array<string, string>, initMethod?: string, parent?: string}
     *
     * @throws RuntimeException for a definition the factory cannot follow
     */
    protected function getDefinition(string $beanID): ?array
    {
        $definition = $this->getDefinitions()[$beanID] ?? null;

        if ($definition === null) {
            return null;
        }

        if (!is_array($definition)) {
            throw new RuntimeException(
                'The bean ' . $beanID . ' must be defined by an array, not ' . get_debug_type($definition) . '.',
            );
        }

        foreach (array_keys($definition) as $option) {
            if (!in_array($option, self::OPTIONS, true)) {
                throw new RuntimeException(
                    'The bean ' . $beanID . ' has the unknown option ' . $option . ' (known are '
                    . implode(', ', self::OPTIONS) . ').',
                );
            }
        }

        $class = $definition['class'] ?? null;

        if (!is_string($class) || !class_exists($class)) {
            throw new RuntimeException('The bean ' . $beanID . ' names no class that exists.');
        }

        if (!new ReflectionClass($class)->isInstantiable()) {
            throw new RuntimeException(
                'The bean ' . $beanID . ' names the class ' . $class . ', which cannot be instantiated.',
            );
        }

        $scope = $definition['scope'] ?? static::SINGLETON;

        if ($scope !== static::SINGLETON && $scope !== static::PROTOTYPE) {
            throw new RuntimeException('The bean ' . $beanID . ' has a scope other than singleton and prototype.');
        }

        $properties = $definition['properties'] ?? [];

        if (!is_array($properties)) {
            throw new RuntimeException('The bean ' . $beanID . ' must list its properties in an array.');
        }

        foreach ($properties as $dependency => $suffix) {
            if (!is_string($dependency) || !is_string($suffix) || $suffix === '') {
                throw new RuntimeException(
                    'The bean ' . $beanID . ' must list its properties as bean id => setter suffix.',
                );
            }
        }

        foreach (['initMethod', 'parent'] as $option) {
            $value = $definition[$option] ?? null;

            if ($value !== null && (!is_string($value) || $value === '')) {
                throw new RuntimeException('The bean ' . $beanID . ' must name its ' . $option . ' by a string.');
            }
        }

        /** @var array{class: class-string, scope?: string, properties?: array<string, string>, initMethod?: string, parent?: string} $definition */
        return $definition;
    }

    /**
     * The configuration's `beans`: bean id => definition.
     *
     * @return array<mixed>
     */
    protected function getDefinitions(): array
    {
        $beans = $this->getConfig()['beans'] ?? [];

        if (!is_array($beans)) {
            throw new RuntimeException(
                'The configuration\'s beans must be an array, not ' . get_debug_type($beans) . '.',
            );
        }

        return $beans;
    }
}
