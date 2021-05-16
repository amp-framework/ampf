<?php

declare(strict_types=1);

namespace ampf\doctrine;

interface EntityManagerFactory
{
    public function get(): self;
}
