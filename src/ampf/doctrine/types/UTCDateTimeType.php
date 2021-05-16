<?php

declare(strict_types=1);

namespace ampf\doctrine\types;

use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeType;

class UTCDateTimeType extends DateTimeType
{
    protected static DateTimeZone $utc;

    protected static function getUtc(): DateTimeZone
    {
        if (static::$utc === null) {
            static::$utc = new DateTimeZone('UTC');
        }

        return static::$utc;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value instanceof DateTime) {
            $value->setTimezone(static::getUtc());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null || ($value instanceof DateTime)) {
            return $value;
        }

        // Create the DateTime object with UTC timezone
        $converted = DateTime::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            static::getUtc(),
        );

        if (!$converted) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), $platform->getDateTimeFormatString(), );
        }

        return $converted;
    }
}
