<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\router\RouteResolver;

trait RouteResolverAccess
{
    protected ?RouteResolver $__routeResolver = null;

    abstract public function getBeanFactory(): BeanFactory;

    public function getRouteResolver(): RouteResolver
    {
        if ($this->__routeResolver === null) {
            $routeResolver = $this->getBeanFactory()->get('RouteResolver');
            assert($routeResolver instanceof RouteResolver);
            $this->setRouteResolver($routeResolver);
        }
        assert($this->__routeResolver !== null);

        return $this->__routeResolver;
    }

    public function setRouteResolver(RouteResolver $routeResolver): void
    {
        $this->__routeResolver = $routeResolver;
    }
}
