<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Doctrine;

use ampf\Doctrine\Repository\AbstractRepo;

/**
 * The repository of SampleEntity, with the queries an application's repository has.
 *
 * @template-extends \ampf\Doctrine\Repository\AbstractRepo<\ampf\Tests\Support\Doctrine\SampleEntity>
 */
class SampleRepo extends AbstractRepo
{
    public function countNamed(string $name): int
    {
        return $this->intResult(
            $this->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->where('s.name = :name')
                ->setParameter('name', $name)
                ->getQuery(),
        );
    }

    /**
     * @return list<\ampf\Tests\Support\Doctrine\SampleEntity>
     */
    public function findNamed(string $name): array
    {
        return $this->entityList(
            $this->createQueryBuilder('s')
                ->where('s.name = :name')
                ->setParameter('name', $name)
                ->orderBy('s.id')
                ->getQuery(),
        );
    }

    public function findOneNamed(string $name): ?SampleEntity
    {
        return $this->entityOrNull(
            $this->createQueryBuilder('s')
                ->where('s.name = :name')
                ->setParameter('name', $name)
                ->getQuery(),
        );
    }

    /**
     * A query that selects no entities, handed to the entity reader.
     *
     * @return list<\ampf\Tests\Support\Doctrine\SampleEntity>
     */
    public function findNamesAsEntities(): array
    {
        return $this->entityList($this->createQueryBuilder('s')->select('s.name')->getQuery());
    }

    public function rename(string $from, string $to): int
    {
        return $this->intExecute(
            $this->getEntityManager()
                ->createQuery('UPDATE ' . SampleEntity::class . ' s SET s.name = :to WHERE s.name = :from')
                ->setParameter('to', $to)
                ->setParameter('from', $from),
        );
    }
}
