<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\CodeStyle;

use ampf\Tests\Support\CodeStyle\NoTrailingWhitespaceInInlineHtmlFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

/** The HTML of a template keeps no whitespace at the end of a line, and nothing else of it changes. */
final class NoTrailingWhitespaceInInlineHtmlFixerTest extends TestCase
{
    /** @return iterable<string, array{string, string}> a template and what the fixer makes of it */
    public static function templates(): iterable
    {
        yield 'spaces after a tag' => ["<?php\n?>\n<div>   \n<p>text</p>\n</div>\n", "<?php\n?>\n<div>\n<p>text</p>\n</div>\n"];
        yield 'a blank line that holds indentation' => ["<?php\n?>\n<p>a</p>\n    \n<p>b</p>\n", "<?php\n?>\n<p>a</p>\n\n<p>b</p>\n"];
        yield 'tabs and spaces together' => ["<?php\n?>\n<p>a</p> \t \n", "<?php\n?>\n<p>a</p>\n"];
        yield 'windows line endings' => ["<?php\r\n?>\r\n<p>a</p>  \r\n  \r\n", "<?php\r\n?>\r\n<p>a</p>\r\n\r\n"];
        yield 'many lines' => ["<?php\n?>\n a \n b  \n\n c\t\n", "<?php\n?>\n a\n b\n\n c\n"];
        yield 'the last line has no line break' => ["<?php\n?>\n<p>a</p>\n   ", "<?php\n?>\n<p>a</p>\n"];
        yield 'a file that is only HTML' => ["<p>a</p>  \n  \n", "<p>a</p>\n\n"];
        yield 'spaces after a closing tag' => ["<?php if (true) : ?>   \n<p>a</p>\n<?php endif; ?>\n", "<?php if (true) : ?>\n<p>a</p>\n<?php endif; ?>\n"];
        yield 'the HTML between two blocks of PHP' => [
            "<?php\n\$a = 1;\n?>\n<p>a</p>  \n<?php\n\$b = 2;\n?>\n  \n<p>b</p>\n",
            "<?php\n\$a = 1;\n?>\n<p>a</p>\n<?php\n\$b = 2;\n?>\n\n<p>b</p>\n",
        ];
    }

    /** @return iterable<string, array{string}> a template that has nothing to fix */
    public static function cleanTemplates(): iterable
    {
        yield 'indentation before a tag on the same line' => ["<?php\n?>\n<ul>\n    <li><?= \$a; ?></li>\n</ul>\n"];
        yield 'text with a space inside the line' => ["<?php\n?>\n<p>one two  three</p>\n"];
        yield 'a file that ends in a line break' => ["<?php\n?>\n<p>a</p>\n"];
        yield 'a file that ends without one' => ["<?php\n?>\n<p>a</p>"];
        yield 'blank lines of PHP code, which the other fixers keep' => ["<?php\n\$a = 1;   \n    \n\$b = 2;\n"];
    }

    private static function fixed(string $code): string
    {
        $tokens = Tokens::fromCode($code);
        new NoTrailingWhitespaceInInlineHtmlFixer()->fix(new SplFileInfo(__FILE__), $tokens);

        return $tokens->generateCode();
    }

    #[DataProvider('templates')]
    public function testTheHtmlOfATemplateKeepsNoWhitespaceAtTheEndOfALine(string $template, string $expected): void
    {
        self::assertSame($expected, self::fixed($template));
    }

    #[DataProvider('cleanTemplates')]
    public function testWhatHasNoTrailingWhitespaceInItsHtmlIsLeftAsItIs(string $template): void
    {
        self::assertSame($template, self::fixed($template));
    }

    public function testTheRuleIsTheNamedOneOfAMachineThatAppliesIt(): void
    {
        $fixer = new NoTrailingWhitespaceInInlineHtmlFixer();

        self::assertSame('Ampf/no_trailing_whitespace_in_inline_html', $fixer->getName());
        self::assertFalse($fixer->isRisky());
        self::assertTrue($fixer->supports(new SplFileInfo('views/page.html.php')));
        self::assertStringContainsString('trailing whitespace', $fixer->getDefinition()->getSummary());
    }

    public function testOnlyAFileThatHasHtmlIsACandidate(): void
    {
        $fixer = new NoTrailingWhitespaceInInlineHtmlFixer();

        self::assertTrue($fixer->isCandidate(Tokens::fromCode("<?php\n?>\n<p>a</p>\n")));
        self::assertFalse($fixer->isCandidate(Tokens::fromCode("<?php\n\$a = 1;\n")));
    }
}
