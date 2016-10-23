<?php

namespace ampf\beans\impl;

use \ampf\beans\BeanFactory;

trait DefaultBeanFactoryAccess
{
	protected $__beanFactory = null;

	/**
	 * @return BeanFactory
	 */
	public function getBeanFactory()
	{
		return $this->__beanFactory;
	}

	/**
	 * @param BeanFactory $beanFactory
	 */
	public function setBeanFactory(BeanFactory $beanFactory)
	{
		$this->__beanFactory = $beanFactory;
	}
}
