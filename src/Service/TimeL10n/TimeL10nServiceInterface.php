<?php

declare(strict_types=1);

namespace ampf\Service\TimeL10n;

/** Conversions between UNIX times and UTC datetimes (`Y-m-d H:i:s`). */
interface TimeL10nServiceInterface
{
    public function getUtcDatetime(?int $unixTime = null): string;

    public function getUnixTimeByUtcDatetime(string $datetime): int;
}
