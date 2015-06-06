<?php

namespace ampf\database;

class DefaultConfig implements Config
{
	use \ampf\beans\access\BeanFactoryAccess;

	protected $_config = null;

	public function getDsn()
	{
		return (
			'mysql'
			. ':host=' . $this->getConfig()['host']
			. ';dbname=' . $this->getConfig()['dbname']
			. ';charset=UTF8'
			. ';port=' . $this->getConfig()['port']
			. ';unix_socket=' . $this->getConfig()['socket']
		);
	}

	public function getPassword()
	{
		return $this->getConfig()['password'];
	}

	public function getUsername()
	{
		return $this->getConfig()['username'];
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
		if (!isset($config['database']) || !is_array($config['database']) || count($config['database']) < 1)
		{
			throw new \Exception();
		}
		$this->_config = $config['database'];
	}
}
