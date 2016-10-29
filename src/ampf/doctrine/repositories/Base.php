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
	 * @return Base
	 */
	public function flush(BaseEntity $entity = null)
	{
		$this->getEntityManager()->flush($entity);
		return $this;
	}

	/**
	 * @see \Doctrine\ORM\EntityManager::persist()
	 * @return Base
	 */
	public function persist(BaseEntity $entity)
	{
		$this->getEntityManager()->persist($entity);
		return $this;
	}

	/**
	 * @see \Doctrine\ORM\EntityManager::remove()
	 * @return Base
	 */
	public function remove(BaseEntity $entity)
	{
		$this->getEntityManager()->remove($entity);
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
