<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\Doctrine\Entity;

use ampf\Doctrine\Entity\AbstractEntity;
use ampf\Tests\Fixtures\App\Doctrine\Repository\NoteRepo;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/** A note: a text and the time it was written. */
#[ORM\Entity(repositoryClass: NoteRepo::class)]
#[ORM\Table(name: 'note')]
class NoteEntity extends AbstractEntity
{
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 200)]
    private string $text = '';

    #[ORM\Column(type: 'datetime')]
    private DateTime $writtenAt;

    public function __construct()
    {
        $this->writtenAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getWrittenAt(): DateTime
    {
        return $this->writtenAt;
    }
}
