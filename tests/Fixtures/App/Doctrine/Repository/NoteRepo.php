<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\Doctrine\Repository;

use ampf\Doctrine\Repository\AbstractRepo;
use ampf\Tests\Fixtures\App\Doctrine\Entity\NoteEntity;

/**
 * @template-extends \ampf\Doctrine\Repository\AbstractRepo<\ampf\Tests\Fixtures\App\Doctrine\Entity\NoteEntity>
 */
class NoteRepo extends AbstractRepo
{
    public function add(string $text): NoteEntity
    {
        $note = $this->create();
        $note->setText($text);
        $this->getEntityManager()->flush();

        return $note;
    }

    /**
     * @return list<\ampf\Tests\Fixtures\App\Doctrine\Entity\NoteEntity>
     */
    public function findInOrder(): array
    {
        return $this->entityList($this->createQueryBuilder('n')->orderBy('n.id')->getQuery());
    }
}
