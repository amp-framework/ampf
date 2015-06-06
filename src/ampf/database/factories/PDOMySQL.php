<?php

namespace ampf\database\factories;

class PDOMySQL implements Factory
{
	use \ampf\beans\access\BeanFactoryAccess;
	use \ampf\beans\access\DatabaseConfigAccess;

	protected $pdo = null;

	public function getPDO()
	{
		if ($this->pdo === null)
		{
			$dsn = $this->getDatabaseConfig()->getDsn();
			// Throws a \PDOException on error
			$this->pdo = new \PDO(
				$dsn,
				$this->getDatabaseConfig()->getUsername(),
				$this->getDatabaseConfig()->getPassword()
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
}
