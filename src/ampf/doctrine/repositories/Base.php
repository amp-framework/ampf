<?php

namespace ampf\doctrine\repositories;

use \Doctrine\ORM\EntityRepository;
use \ampf\doctrine\entities\Base as BaseEntity;

abstract class Base extends EntityRepository
{
	/**
	 * @return BaseEntity
	 */
	public function create()
	{
		$class = $this->getClassName();
		return new $class();
	}

	/**
	 * @see \Doctrine\ORM\EntityManager::flush()
	 */
	public function flush(BaseEntity $entity = null)
	{
		$this->getEntityManager()->flush($entity);
		return $this;
	}

	public function persist(BaseEntity $entity)
	{
		$this->getEntityManager()->persist($entity);
		return $this;
	}

	/**
	 * @param string $entityName
	 * @return Base
	 */
	protected function getRepository($entityName)
	{
		return $this
			->getEntityManager()
			->getRepository($entityName)
		;
	}
}
