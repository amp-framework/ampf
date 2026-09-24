<?php

declare(strict_types=1);

namespace ampf\BeanAccess;

use ampf\Router\RouteResolverInterface;

trait RouteResolverAccess
{
    use AbstractAccess;

    protected ?RouteResolverInterface $__routeResolver = null;

    public function getRouteResolver(): RouteResolverInterface
    {
        if ($this->__routeResolver === null) {
            $object = $this->getBeanFactory()->get(RouteResolverInterface::class);
            assert($object instanceof RouteResolverInterface);
            $this->setRouteResolver($object);
        }

        assert($this->__routeResolver instanceof RouteResolverInterface);

        return $this->__routeResolver;
    }

    public function setRouteResolver(RouteResolverInterface $object): void
    {
        $this->__routeResolver = $object;
    }
}
