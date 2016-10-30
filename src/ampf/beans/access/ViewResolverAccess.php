<?php

namespace ampf\beans\access;

use ampf\views\ViewResolver;

trait ViewResolverAccess
{
	protected $__viewResolver = null;

	/**
	 * @return ViewResolver
	 */
	public function getViewResolver()
	{
		if ($this->__viewResolver === null)
		{
			$this->setViewResolver($this->getBeanFactory()->get('ViewResolver'));
		}
		return $this->__viewResolver;
	}

	/**
	 * @param ViewResolver $viewResolver
	 */
	public function setViewResolver(ViewResolver $viewResolver)
	{
		$this->__viewResolver = $viewResolver;
	}

	/**
	 * @return \ampf\beans\BeanFactory
	 */
	abstract public function getBeanFactory();
}
