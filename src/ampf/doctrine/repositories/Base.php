<?php

declare(strict_types=1);

namespace ampf\doctrine\repositories;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use RuntimeException;

abstract class Base extends EntityRepository
{
    /** @param array<string, mixed> $criteria */
    public function bulkRemoveBy(array $criteria): int
    {
        if (count($criteria) < 1) {
            throw new RuntimeException('You may not use this method to truncate a whole table.');
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('t')->from($this->getClassName(), 't');

        $where = $qb->expr()->andX();
        foreach ($criteria as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                throw new RuntimeException('criteria keys must always be strings');
            }

            if ($value === null) {
                $where->add(
                    $qb->expr()->isNull(
                        ('t.' . $key),
                    ),
                );
            } else {
                $where->add(
                    $qb->expr()->eq(
                        ('t.' . $key),
                        (':' . $key),
                    ),
                );
                $qb->setParameter($key, $value);
            }
        }
        $qb->where($where);

        return $this->bulkRemoveQuery($qb->getQuery());
    }

    public function create(): self
    {
        $class = $this->getClassName();
        $obj = new $class();
        $this->getEntityManager()->persist($obj);

        return $obj;
    }

    public function findAllCount(): int
    {
        $qb = $this->createQueryBuilder('t');

        return (int)$qb
            ->select(
                $qb->expr()->count(
                    ('t.' . $this->getClassMetadata()->getSingleIdentifierFieldName()),
                ),
            )
            ->getQuery()->getSingleScalarResult()
        ;
    }

    public function is(mixed $model): bool
    {
        $class = $this->getClassName();

        return $model instanceof $class;
    }

    protected function bulkRemoveQuery(Query $query): int
    {
        $i = 0;
        foreach ($query->toIterable() as $row) {
            $i++;
            $this->getEntityManager()->remove($row[0]);

            // Flush every 20 objects. This aint a big number, maybe we need to increase it
            if (($i % 20) === 0) {
                $this->getEntityManager()->flush();
                $this->getEntityManager()->clear();
            }
        }
        $this->getEntityManager()->flush();

        return $i;
    }

    protected function getRepository(string $entityName): self
    {
        return $this
            ->getEntityManager()
            ->getRepository($entityName)
        ;
    }
}
