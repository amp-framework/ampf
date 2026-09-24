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

    /**
     * The name of one entity, handed to the reader of one entity.
     */
    public function findOneNameAsEntity(): ?SampleEntity
    {
        return $this->entityOrNull($this->createQueryBuilder('s')->select('s.name')->setMaxResults(1)->getQuery());
    }

    /** The names, handed to the reader of a number. */
    public function countNames(): int
    {
        return $this->intResult($this->createQueryBuilder('s')->select('s.name')->setMaxResults(1)->getQuery());
    }

    /** A query that selects rows, handed to the reader of the rows an UPDATE changed. */
    public function renameBySelecting(): int
    {
        return $this->intExecute($this->createQueryBuilder('s')->getQuery());
    }

    /** Removes what a query that selects no entities selects. */
    public function removeNames(): int
    {
        return $this->bulkRemoveQuery($this->createQueryBuilder('s')->select('s.name')->getQuery());
    }

    /**
     * Another entity's repository, typed.
     *
     * @template R of \ampf\Doctrine\Entity\AbstractEntity
     *
     * @param class-string<R> $entityName
     *
     * @return \ampf\Doctrine\Repository\AbstractRepo<R>
     */
    public function repositoryOf(string $entityName): AbstractRepo
    {
        return $this->getRepository($entityName);
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
