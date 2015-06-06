<?php

namespace ampf\beans\access;

use ampf\router\RouteResolver;

trait RouteResolverAccess
{
	protected $__routeResolver = null;

	public function getRouteResolver()
	{
		if ($this->__routeResolver === null)
		{
			$this->setRouteResolver($this->getBeanFactory()->get('RouteResolver'));
		}
		return $this->__routeResolver;
	}

	public function setRouteResolver(RouteResolver $routeResolver)
	{
		$this->__routeResolver = $routeResolver;
	}
}
