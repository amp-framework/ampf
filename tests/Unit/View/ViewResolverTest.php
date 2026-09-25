<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\View;

use ampf\Bean\BeanFactory;
use ampf\View\ViewResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ViewResolver::class)]
final class ViewResolverTest extends TestCase
{
    private const string VIEWS = __DIR__ . '/../../Fixtures/views';

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNoTemplateNames(): iterable
    {
        yield 'a way up' => ['../views/greeting.txt.php'];
        yield 'a way up behind a backslash' => ['nested\..\greeting.txt.php'];
        yield 'two dots in a name' => ['greeting..txt.php'];
        yield 'an absolute path' => ['/etc/passwd'];
        yield 'an empty segment' => ['nested//page.txt.php'];
        yield 'nothing' => [''];
        yield 'a space' => ['greeting .txt.php'];
        yield 'a line feed at the end' => ["greeting.txt.php\n"];
        yield 'a null byte' => ["greeting.txt.php\0"];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideConfigsWithoutADirectory(): iterable
    {
        yield 'no viewDirectory' => [[], 'null'];
        yield 'a viewDirectory that is no string' => [['viewDirectory' => ['views']], 'array'];
    }

    public function testATemplatesNameIsItsPathUnderTheViewDirectory(): void
    {
        $resolver = $this->resolver();
        $directory = (string)realpath(self::VIEWS);

        self::assertSame($directory, $resolver->getViewDirectory());
        self::assertSame($directory . '/greeting.txt.php', $resolver->getViewFilename('greeting.txt.php'));
        self::assertSame($directory . '/nested/page.txt.php', $resolver->getViewFilename('nested/page.txt.php'));
    }

    #[DataProvider('provideNoTemplateNames')]
    public function testANameThatIsNoTemplatesIsRefused(string $name): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The template name ' . $name . ' has a segment other than letters, digits and _.-, or with "..".',
        );

        $this->resolver()->getViewFilename($name);
    }

    public function testATemplateThatDoesNotExistIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There is no template missing.txt.php.');

        $this->resolver()->getViewFilename('missing.txt.php');
    }

    public function testABackslashSeparatesSegmentsAsASlashDoes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There is no template nested\page.txt.php.');

        $this->resolver()->getViewFilename('nested\page.txt.php');
    }

    public function testAResolverMayCheckNamesAndDirectoriesItsOwnWay(): void
    {
        $resolver = new class extends ViewResolver {
            /**
             * @var list<string>
             */
            private array $resolved = [];

            /**
             * @return list<string>
             */
            public function getResolved(): array
            {
                return $this->resolved;
            }

            protected function isValidFilename(string $filename): bool
            {
                // Partials, named with a leading underscore, are no templates of their own
                return !str_starts_with($filename, '_') && parent::isValidFilename($filename);
            }

            protected function resolveDirectory(string $directory): string
            {
                $this->resolved[] = $directory;

                return parent::resolveDirectory($directory);
            }
        };
        $resolver->setConfig(['viewDirectory' => self::VIEWS]);

        self::assertSame([self::VIEWS], $resolver->getResolved());
        self::assertStringEndsWith('/greeting.txt.php', $resolver->getViewFilename('greeting.txt.php'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The template name _greeting.txt.php has a segment other than letters, digits and _.-, or with "..".',
        );

        $resolver->getViewFilename('_greeting.txt.php');
    }

    public function testADirectoryIsNoTemplate(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There is no template nested.');

        $this->resolver()->getViewFilename('nested');
    }

    public function testWithoutAConfigurationTheDirectoryIsTheBeanConfigs(): void
    {
        $beanFactory = new BeanFactory(['viewDirectory' => self::VIEWS . '/nested']);
        $resolver = new ViewResolver();
        $resolver->setBeanFactory($beanFactory);

        self::assertSame((string)realpath(self::VIEWS . '/nested'), $resolver->getViewDirectory());

        $beanFactory->set('Config', ['viewDirectory' => self::VIEWS]);
        self::assertSame((string)realpath(self::VIEWS . '/nested'), $resolver->getViewDirectory(), 'read once');
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideConfigsWithoutADirectory')]
    public function testABeanConfigWithoutADirectoryIsRefused(array $config, string $type): void
    {
        $beanFactory = new BeanFactory([]);
        $beanFactory->set('Config', $config);
        $resolver = new ViewResolver();
        $resolver->setBeanFactory($beanFactory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The configuration\'s viewDirectory must name a directory, not ' . $type . '.');

        $resolver->getViewDirectory();
    }

    public function testAConfigurationWithoutADirectoryIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The configuration has no viewDirectory.');

        new ViewResolver()->setConfig(['viewDirectory' => null]);
    }

    public function testADirectoryThatDoesNotExistIsRefused(): void
    {
        foreach ([self::VIEWS . '/missing', self::VIEWS . '/greeting.txt.php'] as $directory) {
            try {
                new ViewResolver()->setConfig(['viewDirectory' => $directory]);
                self::fail('took ' . $directory);
            } catch (RuntimeException $e) {
                self::assertSame('The view directory ' . $directory . ' does not exist.', $e->getMessage());
            }
        }
    }

    private function resolver(): ViewResolver
    {
        $resolver = new ViewResolver();
        $resolver->setConfig(['viewDirectory' => self::VIEWS]);

        return $resolver;
    }
}
