<?php

namespace ampf\database\factories;

class PDOMySQL implements Factory
{
	use \ampf\beans\access\BeanFactoryAccess;

	protected $_config = null;

	protected $pdo = null;

	public function getPDO()
	{
		if ($this->pdo === null)
		{
			$dsn = (
				'mysql'
				. ':host=' . $this->getConfig()['host']
				. ';dbname=' . $this->getConfig()['dbname']
				. ';charset=UTF8'
				. ';port=' . $this->getConfig()['port']
				. ';unix_socket=' . $this->getConfig()['socket']
			);
			// Throws a \PDOException on error
			$this->pdo = new \PDO(
				$dsn,
				$this->getConfig()['username'],
				$this->getConfig()['password']
			);
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			// make sure the database connection is UTF8
			$this->pdo->query('SET NAMES UTF8');

			// make sure the database connection uses the same timezone as we do
			$sth = $this->pdo->prepare("SET time_zone = :timezone");
			$sth->execute(array('timezone' => date_default_timezone_get()));
		}
		return $this->pdo;
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
