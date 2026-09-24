<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Service\TimeL10n;

use ampf\Service\TimeL10n\TimeL10nService;
use DateMalformedStringException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TimeL10nService::class)]
final class TimeL10nServiceTest extends TestCase
{
    private string $timezone;

    public function testAUnixTimeIsItsUtcDatetimeWhateverTheDefaultTimeZone(): void
    {
        date_default_timezone_set('America/New_York');
        $service = new TimeL10nService();

        self::assertSame('2025-07-01 12:30:00', $service->getUtcDatetime(1_751_373_000));
        self::assertSame('1970-01-01 00:00:00', $service->getUtcDatetime(0));
    }

    public function testWithoutATimeItIsNow(): void
    {
        $before = time();
        $now = new TimeL10nService()->getUtcDatetime();

        self::assertGreaterThanOrEqual($before, new TimeL10nService()->getUnixTimeByUtcDatetime($now));
        self::assertLessThanOrEqual(time(), new TimeL10nService()->getUnixTimeByUtcDatetime($now));
    }

    public function testAUtcDatetimeIsItsUnixTimeWhateverTheDefaultTimeZone(): void
    {
        date_default_timezone_set('Asia/Tokyo');
        $service = new TimeL10nService();

        self::assertSame(1_751_373_000, $service->getUnixTimeByUtcDatetime('2025-07-01 12:30:00'));
        self::assertSame(
            1_751_373_000,
            $service->getUnixTimeByUtcDatetime('2025-07-01 14:30:00+02:00'),
            'a zone of its own counts',
        );
    }

    public function testABlankDatetimeIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A blank datetime is no time: PHP would read it as now.');

        new TimeL10nService()->getUnixTimeByUtcDatetime(' ');
    }

    public function testATextThatIsNoDatetimeIsRefused(): void
    {
        $this->expectException(DateMalformedStringException::class);

        new TimeL10nService()->getUnixTimeByUtcDatetime('the first of never');
    }

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }
}
