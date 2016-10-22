<?php

namespace ampf\beans\access;

use \ampf\doctrine\EntityManagerFactory;

trait EntityManagerFactoryAccess
{
	protected $__entityManagerFactory = null;

	/**
	 * @return EntityManagerFactory
	 */
	public function getEntityManagerFactory()
	{
		if ($this->__entityManagerFactory === null)
		{
			$this->setEntityManagerFactory($this->getBeanFactory()->get('EntityManagerFactory'));
		}
		return $this->__entityManagerFactory;
	}

	/**
	 * @param EntityManagerFactory $entityManagerFactory
	 */
	public function setEntityManagerFactory(EntityManagerFactory $entityManagerFactory)
	{
		$this->__entityManagerFactory = $entityManagerFactory;
	}
}
