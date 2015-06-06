<?php

namespace ampf\beans\access;

use ampf\database\Config;

trait DatabaseConfigAccess
{
	protected $__databaseConfig = null;

	/**
	 * @return \ampf\database\Config
	 */
	public function getDatabaseConfig()
	{
		if ($this->__databaseConfig === null)
		{
			$this->setDatabaseConfig($this->getBeanFactory()->get('DatabaseConfig'));
		}
		return $this->__databaseConfig;
	}

	/**
	 * @param \ampf\database\Config $config
	 */
	public function setDatabaseConfig(Config $config)
	{
		$this->__databaseConfig = $config;
	}
}
