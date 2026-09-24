<?php

declare(strict_types=1);

namespace ampf\Doctrine\Repository;

use ampf\Doctrine\Entity\AbstractEntity;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use RuntimeException;

/**
 * The base of an application's repositories (the entity's `repositoryClass`; Doctrine creates them, never the bean
 * factory — AbstractRepoAccess fetches them). Besides the helpers for everyday work it has typed readers for query
 * results: the ORM's `getResult()` is `mixed` to static analysis, so a repository reads its rows through these
 * instead of trusting an annotation.
 *
 * @template T of \ampf\Doctrine\Entity\AbstractEntity
 *
 * @template-extends \Doctrine\ORM\EntityRepository<T>
 */
abstract class AbstractRepo extends EntityRepository
{
    /**
     * Removes every entity that matches the criteria (field => value, null matching NULL), flushing every 20
     * removals; the number removed.
     *
     * @param array<string, mixed> $criteria
     */
    public function bulkRemoveBy(array $criteria): int
    {
        if (count($criteria) < 1) {
            throw new RuntimeException('A removal without criteria would empty the whole table: use a query for that.');
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('t')->from($this->getClassName(), 't');

        $where = $qb->expr()->andX();

        foreach ($criteria as $key => $value) {
            if (trim($key) === '') {
                throw new RuntimeException('A criterion needs the name of a field.');
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

    /**
     * A new entity of this repository's class, persisted (not flushed).
     *
     * @return T
     */
    public function create(): AbstractEntity
    {
        $class = $this->getClassName();
        $entity = new $class();
        $this->getEntityManager()->persist($entity);

        return $entity;
    }

    /** The number of this repository's entities. */
    public function findAllCount(): int
    {
        $qb = $this->createQueryBuilder('t');

        return $this->intResult(
            $qb
                ->select(
                    $qb->expr()->count(
                        ('t.' . $this->getClassMetadata()->getSingleIdentifierFieldName()),
                    ),
                )
                ->getQuery(),
        );
    }

    /** Whether the value is an entity of this repository's class. */
    public function is(mixed $model): bool
    {
        $class = $this->getClassName();

        return $model instanceof $class;
    }

    /**
     * Removes the entities the query selects, flushing and clearing the entity manager every 20 removals; the
     * number removed.
     *
     * @param \Doctrine\ORM\Query<mixed, mixed> $query
     */
    protected function bulkRemoveQuery(Query $query): int
    {
        $i = 0;

        foreach ($query->toIterable() as $object) {
            if (!is_object($object)) {
                throw new RuntimeException('The query did not select entities.');
            }

            $i++;
            $this->getEntityManager()->remove($object);

            // Flush every 20 objects. This aint a big number, maybe we need to increase it
            // @phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
            if (($i % 20) === 0) {
                $this->getEntityManager()->flush();
                $this->getEntityManager()->clear();
            }
        }
        $this->getEntityManager()->flush();

        return $i;
    }

    /**
     * The query's rows as a list of this repository's entities.
     *
     * @param \Doctrine\ORM\Query<mixed, mixed> $query
     *
     * @return list<T>
     */
    protected function entityList(Query $query): array
    {
        $rows = $query->getResult();

        if (!is_array($rows)) {
            throw new RuntimeException(
                'The query\'s result is no list of entities, but ' . get_debug_type($rows) . '.',
            );
        }

        $class = $this->getClassName();
        $result = [];

        foreach ($rows as $row) {
            if (!($row instanceof $class)) {
                throw new RuntimeException(
                    'The query\'s result holds something other than a ' . $class . ': ' . get_debug_type($row) . '.',
                );
            }

            $result[] = $row;
        }

        return $result;
    }

    /**
     * The query's single row as this repository's entity, or null when it matches nothing.
     *
     * @param \Doctrine\ORM\Query<mixed, mixed> $query
     *
     * @return ?T
     */
    protected function entityOrNull(Query $query): ?AbstractEntity
    {
        try {
            $row = $query->getSingleResult();
        } catch (NoResultException) {
            return null;
        }

        $class = $this->getClassName();

        if (!($row instanceof $class)) {
            throw new RuntimeException('The query\'s result is no ' . $class . ', but ' . get_debug_type($row) . '.');
        }

        return $row;
    }

    /**
     * The repository of another entity class, typed as an AbstractRepo.
     *
     * @template R of \ampf\Doctrine\Entity\AbstractEntity
     *
     * @param class-string<R> $entityName
     *
     * @return self<R>
     */
    protected function getRepository(string $entityName): self
    {
        $repo = $this
            ->getEntityManager()
            ->getRepository($entityName)
        ;

        if (!($repo instanceof self)) {
            throw new RuntimeException(
                'The repository of ' . $entityName . ' is no ' . self::class . ', but ' . get_debug_type($repo) . '.',
            );
        }

        return $repo;
    }

    /**
     * The number of rows a DQL UPDATE or DELETE changed.
     *
     * @param \Doctrine\ORM\Query<mixed, mixed> $query
     */
    protected function intExecute(Query $query): int
    {
        $result = $query->execute();

        if (!is_int($result)) {
            throw new RuntimeException(
                'The query\'s result is no number of rows, but ' . get_debug_type($result) . '.',
            );
        }

        return $result;
    }

    /**
     * The query's single scalar (a COUNT, say) as an int.
     *
     * @param \Doctrine\ORM\Query<mixed, mixed> $query
     */
    protected function intResult(Query $query): int
    {
        $result = $query->getSingleScalarResult();

        if (!is_numeric($result)) {
            throw new RuntimeException('The query\'s result is no number, but ' . get_debug_type($result) . '.');
        }

        return (int)$result;
    }
}
