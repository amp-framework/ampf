<?php

declare(strict_types=1);

namespace ampf\beans\impl;

use ampf\beans\BeanFactory;

trait DefaultBeanFactoryAccess
{
    protected ?BeanFactory $__beanFactory = null;

    public function getBeanFactory(): BeanFactory
    {
        assert($this->__beanFactory instanceof BeanFactory);

        return $this->__beanFactory;
    }

    public function setBeanFactory(BeanFactory $beanFactory): void
    {
        $this->__beanFactory = $beanFactory;
    }
}
