<?php

namespace ampf\beans\access;

use ampf\services\hasher\HasherService;

trait HasherServiceAccess
{
	protected $__hasherService = null;

	/**
	 * @return HasherService
	 */
	public function getHasherService()
	{
		if ($this->__hasherService === null)
		{
			$this->setHasherService($this->getBeanFactory()->get('HasherService'));
		}
		return $this->__hasherService;
	}

	/**
	 * @param HasherService $hasherService
	 */
	public function setHasherService(HasherService $hasherService)
	{
		$this->__hasherService = $hasherService;
	}

	/**
	 * @return \ampf\beans\BeanFactory
	 */
	abstract public function getBeanFactory();
}
