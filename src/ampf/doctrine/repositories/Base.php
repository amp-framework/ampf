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
		$obj = new $class();
		$this->getEntityManager()->persist($obj);
		return $obj;
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
	 * @param mixed $model
	 * @return bool
	 */
	public function is($model)
	{
		$class = $this->getClassName();
		return ($model instanceof $class);
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
