<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Bootstrap;

use ampf\Bootstrap\ErrorSettings;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PHP's error settings are the process's: every test puts them back as it found them.
 */
#[CoversClass(ErrorSettings::class)]
final class ErrorSettingsTest extends TestCase
{
    private int $errorReporting;

    /**
     * @var array<string, string|false>
     */
    private array $ini;

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideMalformedBlocks(): iterable
    {
        yield 'the block' => [['errors' => 'on'], 'The configuration\'s errors must be an array, not string.'];
        yield 'display' => [['errors' => ['display' => 1]], 'The configuration\'s errors.display must be true or false.'];
        yield 'log' => [['errors' => ['log' => 'yes']], 'The configuration\'s errors.log must be true or false.'];
        yield 'an empty log file' => [['errors' => ['log-file' => '']], 'The configuration\'s errors.log-file must name a file.'];
        yield 'a log file of another type' => [['errors' => ['log-file' => ['a']]], 'The configuration\'s errors.log-file must name a file.'];
    }

    public function testTheDefaultsReportEverythingDisplayNothingAndLogEverything(): void
    {
        error_reporting(E_ALL & ~E_NOTICE);
        ini_set('display_errors', '1');
        ini_set('log_errors', '0');

        ErrorSettings::applyDefaults();

        self::assertSame(E_ALL, error_reporting());
        self::assertSame('0', ini_get('display_errors'));
        self::assertSame('1', ini_get('log_errors'));
    }

    public function testWithoutABlockNothingIsDisplayedAndEverythingLogged(): void
    {
        ini_set('display_errors', '1');
        ini_set('log_errors', '0');

        ErrorSettings::apply([], '/app');

        self::assertSame('0', ini_get('display_errors'));
        self::assertSame('1', ini_get('log_errors'));
        self::assertSame($this->ini['error_log'], ini_get('error_log'), 'the log stays where it was');
    }

    public function testTheBlockDecides(): void
    {
        ErrorSettings::apply(['errors' => ['display' => true, 'log' => false]], '/app');

        self::assertSame('1', ini_get('display_errors'));
        self::assertSame('0', ini_get('log_errors'));

        ErrorSettings::apply(['errors' => ['display' => false, 'log' => true, 'log-file' => null]], '/app');

        self::assertSame('0', ini_get('display_errors'));
        self::assertSame('1', ini_get('log_errors'));
    }

    public function testARelativeLogFileLiesUnderTheProjectRoot(): void
    {
        ErrorSettings::apply(['errors' => ['log-file' => 'logs/php-errors.log']], '/app/');

        self::assertSame('/app/logs/php-errors.log', ini_get('error_log'));
    }

    public function testAnAbsoluteLogFileStaysAsItIs(): void
    {
        ErrorSettings::apply(['errors' => ['log-file' => '/var/log/app/php.log']], '/app');

        self::assertSame('/var/log/app/php.log', ini_get('error_log'));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideMalformedBlocks')]
    public function testAMalformedBlockIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        ErrorSettings::apply($config, '/app');
    }

    protected function setUp(): void
    {
        $this->errorReporting = error_reporting();
        $this->ini = [
            'display_errors' => ini_get('display_errors'),
            'error_log' => ini_get('error_log'),
            'log_errors' => ini_get('log_errors'),
        ];
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);

        foreach ($this->ini as $name => $value) {
            ini_set($name, (string)$value);
        }
    }
}
