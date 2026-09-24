<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\BeanAccess\Doctrine\Repository;

use ampf\BeanAccess\Doctrine\Repository\AbstractRepoAccess;
use ampf\Tests\Fixtures\App\Doctrine\Entity\NoteEntity;
use ampf\Tests\Fixtures\App\Doctrine\Repository\NoteRepo;

trait NoteRepoAccess
{
    use AbstractRepoAccess;

    protected ?NoteRepo $__noteRepo = null;

    public function getNoteRepo(): NoteRepo
    {
        if ($this->__noteRepo === null) {
            $this->setNoteRepo($this->getDoctrineEntityRepository(NoteEntity::class, NoteRepo::class));
        }

        assert($this->__noteRepo instanceof NoteRepo);

        return $this->__noteRepo;
    }

    public function setNoteRepo(NoteRepo $object): void
    {
        $this->__noteRepo = $object;
    }
}
