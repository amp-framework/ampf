<?php

declare(strict_types=1);

namespace ampf\services\timel10n\impl;

use ampf\services\timel10n\TimeL10nService;
use DateTime;
use DateTimeZone;
use RuntimeException;

class DefaultTimeL10nService implements TimeL10nService
{
    public function getUtcDatetime(?int $unixTime = null): string
    {
        if ($unixTime === null) {
            $unixTime = time();
        }

        $date = new DateTime();
        $date->setTimestamp($unixTime);
        $date->setTimezone(new DateTimeZone('UTC'));

        return $date->format('Y-m-d H:i:s');
    }

    public function getUnixTimeByUtcDatetime(string $datetime): int
    {
        if (trim($datetime) === '') {
            throw new RuntimeException();
        }

        $date = new DateTime($datetime, new DateTimeZone('UTC'));

        return (int)$date->format('U');
    }
}
