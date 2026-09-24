<?php

declare(strict_types=1);

namespace ampf\BeanAccess;

use ampf\Bean\BeanFactoryInterface;

/**
 * Implements BeanFactoryAccessInterface: the bean factory hands itself to every bean that implements the interface
 * (property injection), and the host's access traits ask it for their beans.
 */
trait BeanFactoryAccess
{
    protected ?BeanFactoryInterface $__beanFactory = null;

    public function getBeanFactory(): BeanFactoryInterface
    {
        assert($this->__beanFactory instanceof BeanFactoryInterface);

        return $this->__beanFactory;
    }

    public function setBeanFactory(BeanFactoryInterface $beanFactory): void
    {
        $this->__beanFactory = $beanFactory;
    }
}
