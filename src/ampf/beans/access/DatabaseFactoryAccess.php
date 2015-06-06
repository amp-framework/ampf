<?php

namespace ampf\beans\access;

use ampf\database\factories\Factory;

trait DatabaseFactoryAccess
{
	protected $__databaseFactory = null;

	/**
	 * @return \ampf\database\factories\Factory
	 */
	public function getDatabaseFactory()
	{
		if ($this->__databaseFactory === null)
		{
			$this->setDatabaseFactory($this->getBeanFactory()->get('DatabaseFactory'));
		}
		return $this->__databaseFactory;
	}

	public function setDatabaseFactory(Factory $databaseFactory)
	{
		$this->__databaseFactory = $databaseFactory;
	}
}
