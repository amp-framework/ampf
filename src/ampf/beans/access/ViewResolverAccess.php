<?php

namespace ampf\beans\access;

use ampf\views\ViewResolver;

trait ViewResolverAccess
{
	protected $__viewResolver = null;

	public function getViewResolver()
	{
		if ($this->__viewResolver === null)
		{
			$this->setViewResolver($this->getBeanFactory()->get('ViewResolver'));
		}
		return $this->__viewResolver;
	}

	public function setViewResolver(ViewResolver $viewResolver)
	{
		$this->__viewResolver = $viewResolver;
	}
}
