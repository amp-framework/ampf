<?php

namespace ampf\doctrine\impl;

use \ampf\doctrine\Config;

class DefaultConfig implements Config
{
	use \ampf\beans\access\BeanFactoryAccess;

	/**
	 * @var array
	 */
	protected $_config = null;

	public function getCacheDir()
	{
		return $this->getConfig()['cacheDir'];
	}

	public function getConnectionParams()
	{
		return $this->getConfig()['connectionParams'];
	}

	public function getEntities()
	{
		return $this->getConfig()['entities'];
	}

	public function getProxyDir()
	{
		return $this->getConfig()['proxyDir'];
	}

	public function isDevMode()
	{
		return ((bool)$this->getConfig()['isDevMode']);
	}

	public function useSimpleAnnotationReader()
	{
		return ((bool)$this->getConfig()['useSimpleAnnotationReader']);
	}

	// Bean getters

	public function getConfig()
	{
		if (is_null($this->_config))
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
