<?php

declare(strict_types=1);

namespace ampf\Doctrine\Type;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use RuntimeException;

/**
 * DBAL's datetime type with every value in UTC: a DateTime is switched to UTC before it is written — the entity then
 * holds its time as one read from the database holds it —, a DateTimeImmutable is written as its UTC time, and a
 * value read is a DateTime in UTC. The framework puts it in place of `datetime` and `datetimetz`
 * (`doctrine.typeOverrides`).
 */
class UTCDateTimeType extends DateTimeType
{
    /**
     * The UTC zone of every conversion, created once: a zone does not change, and an entity's every datetime is
     * converted — a new zone each time would cost a tenth of a microsecond each.
     */
    protected static ?DateTimeZone $utc = null;

    protected static function getUtc(): DateTimeZone
    {
        return static::$utc ??= new DateTimeZone('UTC');
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof DateTime) {
            $value->setTimezone(static::getUtc());
        } elseif ($value instanceof DateTimeInterface) {
            $value = DateTime::createFromInterface($value)->setTimezone(static::getUtc());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    /**
     * @throws InvalidFormat for a string that is no datetime of the platform's format
     * @throws RuntimeException for a value that is neither a string nor a DateTime
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTime
    {
        if ($value === null || $value instanceof DateTime) {
            return $value;
        }

        if (!is_string($value)) {
            throw new RuntimeException('A datetime from the database is a string, not ' . get_debug_type($value) . '.');
        }

        $converted = DateTime::createFromFormat($platform->getDateTimeFormatString(), $value, static::getUtc());

        if ($converted === false) {
            throw InvalidFormat::new($value, static::class, $platform->getDateTimeFormatString());
        }

        return $converted;
    }
}
