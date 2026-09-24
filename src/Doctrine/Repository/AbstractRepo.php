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
            throw new RuntimeException('You may not use this method to truncate a whole table.');
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('t')->from($this->getClassName(), 't');

        $where = $qb->expr()->andX();

        foreach ($criteria as $key => $value) {
            if (trim($key) === '') {
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

    /**
     * A new entity of this repository's class, persisted (not flushed).
     *
     * @return T
     */
    public function create(): AbstractEntity
    {
        $class = $this->getClassName();

        // @phpcs:ignore SlevomatCodingStandard.PHP.RequireExplicitAssertion.RequiredExplicitAssertion
        // @var T $obj
        $obj = new $class();

        // @phpstan-ignore-next-line
        if (!($obj instanceof AbstractEntity)) {
            throw new RuntimeException();
        }

        $this->getEntityManager()->persist($obj);

        return $obj;
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
            throw new RuntimeException('Expected a list of entities');
        }

        $class = $this->getClassName();
        $result = [];

        foreach ($rows as $row) {
            if (!($row instanceof $class)) {
                throw new RuntimeException('Expected an instance of ' . $class);
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
            throw new RuntimeException('Expected an instance of ' . $class);
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
            throw new RuntimeException();
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
            throw new RuntimeException('Expected the number of rows changed');
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
            throw new RuntimeException('Expected a numeric result');
        }

        return (int)$result;
    }
}
