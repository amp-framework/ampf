<?php

declare(strict_types=1);

namespace ampf\doctrine;

use Doctrine\ORM\Configuration;

interface Config
{
    public function getConfiguration(): Configuration;

    /** @return array<string, mixed> */
    public function getConnectionParams(): array;

    /** @return array<string, mixed> */
    public function getMappingOverrides(): array;

    /** @return array<string, mixed> */
    public function getTypeOverrides(): array;
}
