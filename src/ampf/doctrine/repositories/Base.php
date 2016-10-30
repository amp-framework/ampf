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
