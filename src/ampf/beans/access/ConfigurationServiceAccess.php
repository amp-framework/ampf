<?php

namespace ampf\beans\access;

use \ampf\services\configuration\ConfigurationService;

trait ConfigurationServiceAccess
{
	protected $__configurationService = null;

	/**
	 * @return ConfigurationService
	 */
	public function getConfigurationService()
	{
		if ($this->__configurationService === null)
		{
			$this->setConfigurationService($this->getBeanFactory()->get('ConfigurationService'));
		}
		return $this->__configurationService;
	}

	/**
	 * @param ConfigurationService $configurationService
	 */
	public function setConfigurationService(ConfigurationService $configurationService)
	{
		$this->__configurationService = $configurationService;
	}

	/**
	 * @return \ampf\beans\BeanFactory
	 */
	abstract public function getBeanFactory();
}
