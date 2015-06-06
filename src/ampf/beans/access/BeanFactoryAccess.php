<?php

namespace ampf\beans\access;

use ampf\beans\BeanFactory;

trait BeanFactoryAccess
{
	protected $__beanFactory = null;

	public function getBeanFactory()
	{
		return $this->__beanFactory;
	}

	public function setBeanFactory(BeanFactory $beanFactory)
	{
		$this->__beanFactory = $beanFactory;
	}
}
