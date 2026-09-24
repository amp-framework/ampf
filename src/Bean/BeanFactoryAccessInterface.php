<?php

declare(strict_types=1);

namespace ampf\Bean;

/**
 * A bean that wants the bean factory: the factory hands itself over while it creates the bean, so the bean's access
 * traits can fetch their dependencies lazily. The BeanFactoryAccess trait implements it.
 */
interface BeanFactoryAccessInterface
{
    public function getBeanFactory(): BeanFactoryInterface;

    public function setBeanFactory(BeanFactoryInterface $beanFactory): void;
}
