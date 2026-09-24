<?php

declare(strict_types=1);

namespace ampf\Bean;

use RuntimeException;

/**
 * The dependency container: every object an application needs is a bean, named by an id (an interface for a
 * service, a role name such as 'Request' or 'View' for what a transport fills in) and created from the `beans`
 * configuration on its first use. Nothing is discovered, autowired or scanned.
 */
interface BeanFactoryInterface
{
    /**
     * The bean with this id: a cached singleton, or a new object from the bean's configuration. An id without a
     * configuration is created by $creatorFunc (called with the factory and an empty configuration) when one is
     * given; a configured bean wins over the callable.
     *
     * @throws RuntimeException for an id without a configuration and without a $creatorFunc
     */
    public function get(string $beanID, ?callable $creatorFunc = null): mixed;

    /**
     * The merged configuration: the bean 'Config'.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the bean 'Config' was replaced by something else than a configuration
     */
    public function getConfig(): array;

    /** Puts an object in place of the bean with this id (a double in a test, a request built by hand). */
    public function set(string $beanID, mixed $object): self;

    /** Whether the id names a bean: one created or set already, or a configured one. */
    public function has(string $beanID): bool;

    /** Whether the object is an instance of the class the id's configuration names. */
    public function is(mixed $object, string $beanID): bool;

    /**
     * @return array<string, int>
     */
    public function getStatistics(): array;
}
