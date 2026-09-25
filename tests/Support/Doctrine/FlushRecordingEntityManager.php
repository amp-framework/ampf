<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Doctrine;

use Doctrine\ORM\Decorator\EntityManagerDecorator;

/**
 * An entity manager that notes, at every flush, how many removals it writes, and how often it is cleared.
 */
final class FlushRecordingEntityManager extends EntityManagerDecorator
{
    /**
     * @var list<int>
     */
    private array $flushedRemovals = [];

    private int $clears = 0;

    /**
     * The removals each flush wrote, in their order.
     *
     * @return list<int>
     */
    public function getFlushedRemovals(): array
    {
        return $this->flushedRemovals;
    }

    public function getClears(): int
    {
        return $this->clears;
    }

    public function flush(): void
    {
        $this->flushedRemovals[] = count($this->wrapped->getUnitOfWork()->getScheduledEntityDeletions());

        parent::flush();
    }

    public function clear(): void
    {
        $this->clears++;

        parent::clear();
    }
}
