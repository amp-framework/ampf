<?php

declare(strict_types=1);

namespace ampf\beans;

interface BeanFactoryAccess
{
    public function getBeanFactory(): BeanFactory;

    public function setBeanFactory(BeanFactory $beanFactory): void;
}
