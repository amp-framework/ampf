<?php

declare(strict_types=1);

namespace ampf\Tests\Support\CodeStyle;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

use function assert;
use function count;
use function is_string;

use const T_INLINE_HTML;

/**
 * A line of the HTML of a template does not end in a space or a tab. PHP-CS-Fixer's own fixers for trailing whitespace work on
 * PHP code and leave the text between `?>` and `<?php` alone, and PHP_CodeSniffer does not look at it either, so a blank line
 * of a template that holds indentation stayed. Whitespace that is followed by a tag on the same line is the indentation of
 * the tag and stays; so does whatever stands in PHP code, which the other fixers keep clean.
 *
 * It is a custom fixer of the shared configuration (`.php-cs-fixer.dist.php`), where its rule is `Ampf/no_trailing_whitespace_in_inline_html`.
 */
final class NoTrailingWhitespaceInInlineHtmlFixer extends AbstractFixer
{
    public function getName(): string
    {
        return 'Ampf/no_trailing_whitespace_in_inline_html';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'There must be no trailing whitespace at the end of a line of the HTML of a template.',
            [new CodeSample("<?php\n\$a = 1;\n?>\n<p>text</p>   \n    \n")],
        );
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_INLINE_HTML);
    }

    // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- the signature is the base class's
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $last = count($tokens) - 1;

        for ($index = $last; $index >= 0; $index--) {
            $token = $tokens[$index];

            if (!$token->isGivenKind(T_INLINE_HTML)) {
                continue;
            }
            $content = $token->getContent();
            $fixed = $this->withoutTrailingWhitespace($content, $index === $last);

            if ($fixed !== $content) {
                $tokens[$index] = new Token([T_INLINE_HTML, $fixed]);
            }
        }
    }

    /** @param bool $atTheEnd whether the text ends the file, where a last line without a line break is a line too */
    private function withoutTrailingWhitespace(string $html, bool $atTheEnd): string
    {
        $fixed = preg_replace($atTheEnd ? '/[ \t]+(?=\r?\n|\z)/' : '/[ \t]+(?=\r?\n)/', '', $html);
        assert(is_string($fixed));

        return $fixed;
    }
}
