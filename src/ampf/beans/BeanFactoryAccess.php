<?php

namespace ampf\beans;

use \ampf\beans\BeanFactory;

interface BeanFactoryAccess
{
	/**
	 * @return BeanFactory
	 */
	public function getBeanFactory();

	/**
	 * @param BeanFactory $beanFactory
	 */
	public function setBeanFactory(BeanFactory $beanFactory);
}
