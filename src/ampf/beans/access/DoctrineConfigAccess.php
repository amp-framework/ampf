<?php

namespace ampf\beans\access;

use ampf\doctrine\Config;

trait DoctrineConfigAccess
{
	protected $__doctrineConfig = null;

	/**
	 * @return \ampf\doctrine\Config
	 */
	public function getDoctrineConfig()
	{
		if ($this->__doctrineConfig === null)
		{
			$this->setDoctrineConfig($this->getBeanFactory()->get('DoctrineConfig'));
		}
		return $this->__doctrineConfig;
	}

	/**
	 * @param \ampf\doctrine\Config $config
	 */
	public function setDoctrineConfig(Config $config)
	{
		$this->__doctrineConfig = $config;
	}
}
