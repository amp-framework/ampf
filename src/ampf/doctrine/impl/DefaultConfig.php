<?php

namespace ampf\doctrine\impl;

use \ampf\beans\BeanFactoryAccess;
use \ampf\doctrine\Config;

class DefaultConfig implements BeanFactoryAccess, Config
{
	use \ampf\beans\impl\DefaultBeanFactoryAccess;

	protected $_config = null;

	/**
	 * @return \Doctrine\ORM\Configuration
	 */
	public function getConfiguration()
	{
		return $this->getConfigValue('configuration');
	}

	/**
	 * @return array
	 */
	public function getConnectionParams()
	{
		return $this->getConfigValue('connectionParams');
	}

	/**
	 * @return array
	 */
	public function getMappingOverrides()
	{
		return $this->getConfigValue('mappingOverrides');
	}

	/**
	 * @return array
	 */
	public function getTypeOverrides()
	{
		return $this->getConfigValue('typeOverrides');
	}

	// Protected

	/**
	 * @param string $value
	 * @return mixed
	 */
	protected function getConfigValue(string $value)
	{
		$config = $this->getConfig();
		if (!isset($config[$value]))
		{
			return null;
		}
		return $config[$value];
	}

	// Bean getters

	public function getConfig()
	{
		if ($this->_config === null)
		{
			$this->setConfig($this->getBeanFactory()->get('Config'));
		}
		return $this->_config;
	}

	// Bean setters

	public function setConfig($config)
	{
		if (!is_array($config) || count($config) < 1) throw new \Exception();
		if (!isset($config['doctrine']) || !is_array($config['doctrine']) || count($config['doctrine']) < 1)
		{
			throw new \Exception();
		}
		$this->_config = $config['doctrine'];
	}
}
