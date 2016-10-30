<?php

namespace ampf\beans\access;

use \Doctrine\ORM\EntityManagerInterface;

trait DoctrineEntityManagerAccess
{
	protected $__doctrineEntityManager = null;

	/**
	 * @return EntityManagerInterface
	 */
	public function getDoctrineEntityManager()
	{
		if ($this->__doctrineEntityManager === null)
		{
			$this->setDoctrineEntityManager(
				$this->getBeanFactory()->get('EntityManagerFactory')->get()
			);
		}
		return $this->__doctrineEntityManager;
	}

	/**
	 * @param EntityManagerInterface $entityManager
	 */
	public function setDoctrineEntityManager(EntityManagerInterface $entityManager)
	{
		$this->__doctrineEntityManager = $entityManager;
	}

	/**
	 * @return \ampf\beans\BeanFactory
	 */
	abstract public function getBeanFactory();
}
