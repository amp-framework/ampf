<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Doctrine\Type;

use ampf\Doctrine\Type\UTCDateTimeType;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(UTCDateTimeType::class)]
final class UTCDateTimeTypeTest extends TestCase
{
    private UTCDateTimeType $type;

    private AbstractPlatform $platform;

    public function testADateTimeIsSwitchedToUtcAndWritten(): void
    {
        $berlin = new DateTime('2025-07-01 14:30:00', new DateTimeZone('Europe/Berlin'));

        self::assertSame('2025-07-01 12:30:00', $this->type->convertToDatabaseValue($berlin, $this->platform));
        self::assertSame('UTC', $berlin->getTimezone()->getName(), 'the entity holds its time as one read holds it');
        self::assertSame('2025-07-01 12:30:00', $berlin->format('Y-m-d H:i:s'));
    }

    public function testADateTimeImmutableIsWrittenAsItsUtcTime(): void
    {
        $newYork = new DateTimeImmutable('2025-01-01 08:00:00-05:00');

        self::assertSame('2025-01-01 13:00:00', $this->type->convertToDatabaseValue($newYork, $this->platform));
        self::assertSame('-05:00', $newYork->getTimezone()->getName());
    }

    public function testAValueReadIsADateTimeInUtc(): void
    {
        $value = $this->type->convertToPHPValue('2025-07-01 12:30:00', $this->platform);

        self::assertSame('2025-07-01T12:30:00+00:00', $value->format(DATE_ATOM));
        self::assertSame('UTC', $value->getTimezone()->getName());
    }

    public function testNullAndADateTimeAreTakenAsTheyAre(): void
    {
        $dateTime = new DateTime();

        self::assertSame([null, $dateTime, null], [
            $this->type->convertToPHPValue(null, $this->platform),
            $this->type->convertToPHPValue($dateTime, $this->platform),
            $this->type->convertToDatabaseValue(null, $this->platform),
        ]);
    }

    public function testAValueOfAnotherTypeIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A datetime from the database is a string, not int.');

        $this->type->convertToPHPValue(1_751_372_200, $this->platform);
    }

    public function testATextOfAnotherFormatIsRefused(): void
    {
        $this->expectException(InvalidFormat::class);

        $this->type->convertToPHPValue('1st of July', $this->platform);
    }

    protected function setUp(): void
    {
        $this->type = new UTCDateTimeType();
        $this->platform = new MySQL80Platform();
    }
}
