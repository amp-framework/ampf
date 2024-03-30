<?php

declare(strict_types=1);

namespace ampf\beans;

interface BeanFactory
{
    public function get(string $beanID, ?callable $creatorFunc = null): mixed;

    public function set(string $beanID, mixed $object): self;

    public function has(string $beanID): bool;

    public function is(mixed $object, string $beanID): bool;

    /**
     * @return array<string, int>
     */
    public function getStatistics(): array;
}
