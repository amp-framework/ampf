<?php

declare(strict_types=1);

namespace ampf\Doctrine\Type;

use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use RuntimeException;

/**
 * DBAL's datetime type with every value in UTC: a DateTime is converted to UTC before it is written, and a value read
 * is a DateTime in UTC. The framework puts it in place of `datetime` and `datetimetz` (`doctrine.typeOverrides`).
 */
class UTCDateTimeType extends DateTimeType
{
    protected static ?DateTimeZone $utc = null;

    protected static function getUtc(): DateTimeZone
    {
        if (static::$utc === null) {
            static::$utc = new DateTimeZone('UTC');
        }

        return static::$utc;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof DateTime) {
            $value->setTimezone(static::getUtc());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTime
    {
        if ($value === null || ($value instanceof DateTime)) {
            return $value;
        }

        if (!is_string($value)) {
            throw new RuntimeException();
        }

        // Create the DateTime object with UTC timezone
        $converted = DateTime::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            static::getUtc(),
        );

        if (!$converted) {
            throw InvalidFormat::new($value, static::class, $platform->getDateTimeFormatString());
        }

        return $converted;
    }
}
