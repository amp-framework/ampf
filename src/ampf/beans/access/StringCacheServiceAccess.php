<?php

namespace ampf\beans\access;

use ampf\services\cache\string\StringCacheService;

trait StringCacheServiceAccess
{
	protected $__stringCacheService = null;

	/**
	 * @return StringCacheService
	 */
	public function getStringCacheService()
	{
		if ($this->__stringCacheService === null)
		{
			$this->setStringCacheService($this->getBeanFactory()->get('StringCacheService'));
		}
		return $this->__stringCacheService;
	}

	/**
	 * @param StringCacheService $stringCacheService
	 */
	public function setStringCacheService(StringCacheService $stringCacheService)
	{
		$this->__stringCacheService = $stringCacheService;
	}

	/**
	 * @return \ampf\beans\BeanFactory
	 */
	abstract public function getBeanFactory();
}
