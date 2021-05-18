<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\views\ViewResolver;

trait ViewResolverAccess
{
    protected ?ViewResolver $__viewResolver = null;

    public function getViewResolver(): ViewResolver
    {
        if ($this->__viewResolver === null) {
            $this->setViewResolver($this->getBeanFactory()->get('ViewResolver'));
        }
        assert($this->__viewResolver !== null);

        return $this->__viewResolver;
    }

    public function setViewResolver(ViewResolver $viewResolver): void
    {
        $this->__viewResolver = $viewResolver;
    }

    abstract public function getBeanFactory(): BeanFactory;
}
