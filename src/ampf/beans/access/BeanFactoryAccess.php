<?php

namespace ampf\beans\access;

use ampf\beans\BeanFactory;

trait BeanFactoryAccess
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
