<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Service\Translator;

use ampf\Bean\BeanFactory;
use ampf\Service\Translator\TranslatorService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TranslatorService::class)]
final class TranslatorServiceTest extends TestCase
{
    private const string DIRECTORY = __DIR__ . '/../../../Fixtures/translations';

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNoLanguages(): iterable
    {
        yield 'nothing' => [''];
        yield 'a blank' => [' '];
        yield 'a path' => ['../../etc/passwd'];
        yield 'a path in the directory' => ['de/../en_GB'];
        yield 'a file name' => ['de.todo'];
        yield 'a line feed at the end' => ["de\n"];
        yield 'one letter' => ['d'];
        yield 'a digit first' => ['1de'];
        yield 'an empty part' => ['de_'];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function provideDirectoriesThatAreRefused(): iterable
    {
        yield 'no directory' => [[], 'de', 'The configuration\'s translation.dir must name a directory, not null.'];
        yield 'a directory that is no string' => [
            ['translation.dir' => ['translations']],
            'de',
            'The configuration\'s translation.dir must name a directory, not array.',
        ];
        yield 'no file of the language' => [
            ['translation.dir' => self::DIRECTORY],
            'fr',
            'There is no translation file ' . self::DIRECTORY . '/fr.php.',
        ];
        yield 'a file that returns no array' => [
            ['translation.dir' => self::DIRECTORY],
            'xx',
            'The translation file ' . self::DIRECTORY . '/xx.php returns no array.',
        ];
        yield 'a file with a text that is no text' => [
            ['translation.dir' => self::DIRECTORY],
            'yy',
            'The translation file ' . self::DIRECTORY . '/yy.php maps the key GREETING to something other than a text.',
        ];
    }

    public function testAKeyTranslatesToTheLanguagesText(): void
    {
        $translator = $this->translator('de');

        self::assertSame('Tschüss', $translator->translate('FAREWELL'));
        self::assertSame('Hallo Ada', $translator->translate('GREETING', ['Ada']));
        self::assertSame('Hallo %s', $translator->translate('GREETING'));
        self::assertSame('Hallo %s', $translator->translate('GREETING', []), 'no arguments, nothing put in');
        self::assertSame('MISSING', $translator->translate('MISSING'), 'a key without a text is itself');
    }

    public function testAnotherLanguageHasItsOwnTexts(): void
    {
        $translator = $this->translator('de');
        self::assertSame('Tschüss', $translator->translate('FAREWELL'));

        $translator->setLanguage('en_GB');

        self::assertSame('en_GB', $translator->getLanguage());
        self::assertSame('Goodbye', $translator->translate('FAREWELL'));
        $this->expectOutputString('');
    }

    public function testALanguageWithoutTextsIsReadOnce(): void
    {
        $beanFactory = new BeanFactory(['translation.dir' => self::DIRECTORY]);
        $translator = new TranslatorService();
        $translator->setBeanFactory($beanFactory);
        $translator->setLanguage('empty');
        self::assertSame('FAREWELL', $translator->translate('FAREWELL'));

        $beanFactory->set('Config', ['translation.dir' => '/nowhere']);

        self::assertSame('FAREWELL', $translator->translate('FAREWELL'), 'no second look for its file');
    }

    public function testTheSameLanguageKeepsItsTexts(): void
    {
        $beanFactory = new BeanFactory(['translation.dir' => self::DIRECTORY]);
        $translator = new TranslatorService();
        $translator->setBeanFactory($beanFactory);
        $translator->setLanguage('de');
        $translator->translate('FAREWELL');

        $beanFactory->set('Config', ['translation.dir' => '/nowhere']);
        $translator->setLanguage('de');

        self::assertSame('Tschüss', $translator->translate('FAREWELL'));
    }

    public function testAKeyIsFoundByItsText(): void
    {
        $translator = $this->translator('de');

        self::assertSame('FAREWELL', $translator->getKey('tschüss'));
        self::assertSame('OVER', $translator->getKey('ÜBER'), 'the case of every letter');
        self::assertSame('OVER', $translator->getKey('über'));
        self::assertSame('FAREWELL', $translator->getKey('Tschüss', false));
        self::assertNull($translator->getKey('tschüss', false), 'the case counts');
        self::assertNull($translator->getKey('Servus'));
    }

    public function testATranslatorMayLoadTheTextsItsOwnWay(): void
    {
        $translator = new class extends TranslatorService {
            private int $loads = 0;

            public function getLoads(): int
            {
                return $this->loads;
            }

            /**
             * @return array<string, string>
             */

            protected function getConfig(): array
            {
                return ['EXTRA' => 'Noch was'] +

                parent::getConfig();
            }

            /**
             * @return array<string, string>
             */

            protected function loadTexts(string $directory): array
            {
                $this->loads++;

                return parent::loadTexts($directory);
            }
        };
        $translator->setBeanFactory(new BeanFactory(['translation.dir' => self::DIRECTORY]));
        $translator->setLanguage('de');

        self::assertSame('Noch was', $translator->translate('EXTRA'));
        self::assertSame('Tschüss', $translator->translate('FAREWELL'));
        self::assertSame(1, $translator->getLoads(), 'once for the language');
    }

    public function testWithoutALanguageThereAreNoTexts(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The translator has no language: setLanguage() first.');

        new TranslatorService()->getLanguage();
    }

    #[DataProvider('provideNoLanguages')]
    public function testWhatIsNoLanguageCodeIsRefused(string $language): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A language is a code such as de or en_GB, not ' . $language . '.');

        new TranslatorService()->setLanguage($language);
    }

    public function testABlankKeyIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A blank key has no translation.');

        $this->translator('de')->translate(' ');
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideDirectoriesThatAreRefused')]
    public function testAConfigurationWithoutTheLanguagesTextsIsRefused(
        array $config,
        string $language,
        string $message,
    ): void {
        $translator = new TranslatorService();
        $translator->setBeanFactory(new BeanFactory($config));
        $translator->setLanguage($language);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        $translator->translate('GREETING');
    }

    public function testAFailingFilePrintsNothing(): void
    {
        $translator = $this->translator('zz');
        $level = ob_get_level();

        try {
            $translator->translate('GREETING');
            self::fail('the file did not fail');
        } catch (RuntimeException $e) {
            self::assertSame('The translation file failed.', $e->getMessage());
        }

        self::assertSame($level, ob_get_level());
        $this->expectOutputString('');
    }

    private function translator(string $language): TranslatorService
    {
        $translator = new TranslatorService();
        $translator->setBeanFactory(new BeanFactory(['translation.dir' => self::DIRECTORY]));
        $translator->setLanguage($language);

        return $translator;
    }
}
