<?php

declare(strict_types=1);

namespace ampf\BeanAccess;

use ampf\View\ViewResolverInterface;

trait ViewResolverAccess
{
    use AbstractAccess;

    protected ?ViewResolverInterface $__viewResolver = null;

    public function getViewResolver(): ViewResolverInterface
    {
        if ($this->__viewResolver === null) {
            $object = $this->getBeanFactory()->get(ViewResolverInterface::class);
            assert($object instanceof ViewResolverInterface);
            $this->setViewResolver($object);
        }

        assert($this->__viewResolver instanceof ViewResolverInterface);

        return $this->__viewResolver;
    }

    public function setViewResolver(ViewResolverInterface $object): void
    {
        $this->__viewResolver = $object;
    }
}
