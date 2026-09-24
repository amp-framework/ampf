<?php

declare(strict_types=1);

namespace ampf\Service\TimeL10n;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Converts with PHP's dates in the UTC time zone. */
class TimeL10nService implements TimeL10nServiceInterface
{
    public function getUtcDatetime(?int $unixTime = null): string
    {
        return gmdate('Y-m-d H:i:s', $unixTime ?? time());
    }

    /**
     * @throws DateMalformedStringException for a text that is no datetime
     * @throws RuntimeException for a blank datetime, which PHP would read as now
     */
    public function getUnixTimeByUtcDatetime(string $datetime): int
    {
        if (trim($datetime) === '') {
            throw new RuntimeException('A blank datetime is no time: PHP would read it as now.');
        }

        return new DateTimeImmutable($datetime, new DateTimeZone('UTC'))->getTimestamp();
    }
}
