<?php

declare(strict_types=1);

namespace ampf\doctrine;

use Doctrine\ORM\EntityManager;

interface EntityManagerFactory
{
    public function get(): ?EntityManager;
}
