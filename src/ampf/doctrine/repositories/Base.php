<?php

namespace ampf\doctrine\repositories;

use \Doctrine\ORM\EntityRepository;
use \ampf\doctrine\entities\Base as BaseEntity;

abstract class Base extends EntityRepository
{
	/**
	 * @return Base
	 */
	public function beginTransaction()
	{
		$this->getEntityManager()->beginTransaction();
		return $this;
	}

	/**
	 * @return Base
	 */
	public function commit()
	{
		$this->getEntityManager()->commit();
		return $this;
	}

	/**
	 * @return BaseEntity
	 */
	public function create()
	{
		$class = $this->getClassName();
		return new $class();
	}

	/**
	 * @return int
	 */
	public function findAllCount()
	{
		$qb = $this->createQueryBuilder('t');
		return ((int)$qb
			->select($qb->expr()->count(
				('t.' . $this->getClassMetadata()->getSingleIdentifierFieldName()))
			)
			->getQuery()->getSingleScalarResult()
		);
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
	 * @return Base
	 */
	public function rollback()
	{
		$this->getEntityManager()->rollback();
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
