<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Service\Asset;

use ampf\Bean\BeanFactory;
use ampf\Service\Asset\AssetService;
use ampf\Testing\ExpectsExactMessage;
use ampf\Testing\TestHttpRequest;
use ampf\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** The address of an asset that the web server serves: the address it is served under, the path and the version of its content. */
#[CoversClass(AssetService::class)]
final class AssetServiceTest extends TestCase
{
    use ExpectsExactMessage;

    /** The SHA-256 of "abc", of which the version is the first 16 characters. */
    private const string ABC = 'ba7816bf8f01cfea';

    private TemporaryDirectory $root;

    public function testTheAddressCarriesTheVersionOfTheContent(): void
    {
        $this->put('css/site.css', 'abc');

        self::assertSame(
            '/assets/css/site.css?v=' . self::ABC,
            $this->service(['directory' => $this->root->getPath()])->link(new TestHttpRequest(), 'css/site.css'),
        );
    }

    public function testAnotherContentIsAnotherVersion(): void
    {
        $this->put('site.css', 'abc');
        $first = $this->service(['directory' => $this->root->getPath()])->link(new TestHttpRequest(), 'site.css');
        $this->put('site.css', 'abd');

        self::assertNotSame(
            $first,
            $this->service(['directory' => $this->root->getPath()])->link(new TestHttpRequest(), 'site.css'),
        );
    }

    public function testAFileIsReadOnceForARequest(): void
    {
        $this->put('site.css', 'abc');
        $service = $this->service(['directory' => $this->root->getPath()]);
        $first = $service->link(new TestHttpRequest(), 'site.css');
        $this->put('site.css', 'changed meanwhile');

        self::assertSame($first, $service->link(new TestHttpRequest(), 'site.css'));
    }

    public function testTheAssetsAreServedUnderTheAddressTheConfigurationNames(): void
    {
        $this->put('site.css', 'abc');

        self::assertSame(
            '/static/site.css?v=' . self::ABC,
            $this->service(['directory' => $this->root->getPath(), 'path' => 'static'])->link(
                new TestHttpRequest(),
                'site.css',
            ),
        );
    }

    public function testTheAddressFollowsTheBasePathOfTheApplication(): void
    {
        $this->put('site.css', 'abc');
        $request = new TestHttpRequest(server: ['SCRIPT_NAME' => '/app/index.php', 'REQUEST_URI' => '/app/']);

        self::assertSame(
            '/app/assets/site.css?v=' . self::ABC,
            $this->service(['directory' => $this->root->getPath()])->link($request, 'site.css'),
        );
    }

    public function testAFileThatIsNotThereIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageExactly(
            'The asset ' . $this->root->getPath() . '/missing.css does not exist or cannot be read.',
        );

        $this->service(['directory' => $this->root->getPath()])->link(new TestHttpRequest(), 'missing.css');
    }

    public function testWithoutADirectoryThereIsNoAsset(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageExactly('The configuration names no assets.directory.');

        $this->service([])->link(new TestHttpRequest(), 'site.css');
    }

    protected function setUp(): void
    {
        $this->root = new TemporaryDirectory('ampf-assets');
    }

    protected function tearDown(): void
    {
        $this->root->remove();
    }

    /** A file of the assets directory. */
    private function put(string $path, string $content): void
    {
        $file = $this->root->getPath() . '/' . $path;

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0o755, true);
        }
        file_put_contents($file, $content);
    }

    /** @param array<string, string> $assets the configuration's `assets` block */
    private function service(array $assets): AssetService
    {
        $service = new AssetService();
        $service->setBeanFactory(new BeanFactory(['assets' => $assets]));

        return $service;
    }
}
