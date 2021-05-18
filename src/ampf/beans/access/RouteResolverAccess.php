<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\router\RouteResolver;

trait RouteResolverAccess
{
    protected ?RouteResolver $__routeResolver = null;

    public function getRouteResolver(): RouteResolver
    {
        if ($this->__routeResolver === null) {
            $this->setRouteResolver($this->getBeanFactory()->get('RouteResolver'));
        }
        assert($this->__routeResolver !== null);

        return $this->__routeResolver;
    }

    public function setRouteResolver(RouteResolver $routeResolver): void
    {
        $this->__routeResolver = $routeResolver;
    }

    abstract public function getBeanFactory(): BeanFactory;
}
