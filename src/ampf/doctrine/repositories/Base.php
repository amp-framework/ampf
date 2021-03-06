<?php

namespace ampf\doctrine\repositories;

use \Doctrine\ORM\EntityRepository;
use \ampf\doctrine\entities\Base as BaseEntity;

abstract class Base extends EntityRepository
{
	/**
	 * @param array $criteria
	 * @return int Affected rows
	 * @throws \Exception
	 */
	public function bulkRemoveBy(array $criteria)
	{
		if (count($criteria) < 1)
		{
			throw new \Exception("You may not use this method to truncate a whole table.");
		}

		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb->select('t')->from($this->getClassName(), 't');

		$where = $qb->expr()->andX();
		foreach ($criteria as $key => $value)
		{
			if (!is_string($key) || trim($key) == '')
			{
				throw new \Exception("criteria keys must always be strings");
			}

			if ($value === null)
			{
				$where->add($qb->expr()->isNull(
					('t.' . $key)
				));
			}
			else
			{
				$where->add($qb->expr()->eq(
					('t.' . $key),
					(':' . $key)
				));
				$qb->setParameter($key, $value);
			}
		}
		$qb->where($where);

		return $this->bulkRemoveQuery($qb->getQuery());
	}

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
	 * @param \Doctrine\ORM\Query $query
	 * @return int
	 */
	protected function bulkRemoveQuery(\Doctrine\ORM\Query $query)
	{
		$i = 0;
		$result = $query->iterate();
		while (($row = $result->next()) !== false)
		{
			$i++;
			$this->getEntityManager()->remove($row[0]);

			// Flush every 20 objects. This aint a big number, maybe we need to increase it
			if (($i % 20) === 0)
			{
				$this->getEntityManager()->flush();
				$this->getEntityManager()->clear();
			}
		}
		$this->getEntityManager()->flush();
		return $i;
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
