<?php

declare(strict_types=1);

namespace ampf\services\timel10n;

interface TimeL10nService
{
    public function getUtcDatetime(?int $unixTime = null): string;

    public function getUnixTimeByUtcDatetime(string $datetime): int;
}
