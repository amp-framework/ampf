<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Service\Configuration;

use ampf\Service\Configuration\ConfigurationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ConfigurationService::class)]
final class ConfigurationServiceTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideBlocksThatAreRefused(): iterable
    {
        yield 'no block' => [[], 'The configuration\'s configuration.service must be an array of domains, not null.'];
        yield 'a block that is no array' => [
            ['configuration.service' => '.app'],
            'The configuration\'s configuration.service must be an array of domains, not string.',
        ];
        yield 'a domain without values' => [
            ['configuration.service' => ['.app' => 'values']],
            'The configuration\'s configuration.service must map each domain to an array of its values.',
        ];
        yield 'a list' => [
            ['configuration.service' => [['title' => 'app']]],
            'The configuration\'s configuration.service must map each domain to an array of its values.',
        ];
    }

    public function testTheNarrowestDomainWithTheKeyWins(): void
    {
        $service = $this->service()->setDomain('.app.de.admin');

        self::assertSame('admin', $service->get('title'));
        self::assertSame('de', $service->get('language'));
        self::assertSame('https://app.example', $service->get('url'));
        self::assertNull($service->get('missing'));
    }

    public function testAFalseValueOfANarrowerDomainWins(): void
    {
        $service = new ConfigurationService();
        $service->setConfig(['configuration.service' => [
            '.app' => ['feature' => true, 'limit' => 10, 'title' => 'app'],
            '.app.de' => ['feature' => false, 'limit' => 0, 'title' => ''],
        ]]);
        $service->setDomain('.app.de');

        self::assertFalse($service->get('feature'));
        self::assertSame(0, $service->get('limit'));
        self::assertSame('', $service->get('title'));
    }

    public function testAKeySetToNullFallsThroughToABroaderDomain(): void
    {
        self::assertSame('https://app.example', $this->service()->setDomain('.app.de')->get('url'));
    }

    public function testTheCallMayNameADomainOfItsOwn(): void
    {
        $service = $this->service()->setDomain('.app.de.admin');

        self::assertSame('app', $service->get('title', '.app'));
        self::assertSame('de', $service->get('title', '.app.de'));
        self::assertSame('app', $service->get('title', '.app.fr'), 'a domain without the key: its parent\'s');
    }

    public function testADomainWithoutADotHasNoValues(): void
    {
        $service = new ConfigurationService();
        $service->setConfig(['configuration.service' => ['app' => ['title' => 'app'], '.' => ['title' => 'dot']]]);

        self::assertNull($service->get('title', 'app'));
        self::assertSame('dot', $service->get('title', '.'));
        self::assertSame('dot', $service->get('title', '..'));
        self::assertNull($service->get('title', ''));
    }

    public function testWithoutADomainThereIsNoValue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The configuration service has no domain: name one, or set one with setDomain().',
        );

        $this->service()->get('title');
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideBlocksThatAreRefused')]
    public function testABlockThatIsNoArrayOfDomainsIsRefused(array $config, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        new ConfigurationService()->setConfig($config);
    }

    private function service(): ConfigurationService
    {
        $service = new ConfigurationService();
        $service->setConfig(['configuration.service' => [
            '.app' => ['title' => 'app', 'url' => 'https://app.example', 'language' => 'en'],
            '.app.de' => ['title' => 'de', 'language' => 'de', 'url' => null],
            '.app.de.admin' => ['title' => 'admin'],
        ]]);

        return $service;
    }
}
