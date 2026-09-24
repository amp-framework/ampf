<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/config', __DIR__ . '/tests'])
    ->append([__FILE__])
;

return new Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PhpCsFixer' => true,
        '@Symfony' => true,
        '@PHP8x0Migration' => true,
        '@PHP8x1Migration' => true,
        '@PHP8x2Migration' => true,
        '@PHP8x3Migration' => true,
        '@PHP8x4Migration' => true,
        '@PHP8x5Migration' => true,
        '@PHPUnit11x0Migration:risky' => true,
        'cast_spaces' => ['space' => 'none'],
        'class_attributes_separation' => false,
        // PHPCS owns method ordering and PHPDoc layout; PHPUnit uses attributes.
        'php_unit_data_provider_method_order' => false,
        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,
        'phpdoc_separation' => false,
        // PHPCS handles indentation, including mixed PHP/HTML templates.
        'statement_indentation' => false,
        'array_indentation' => false,
        'ordered_types' => ['null_adjustment' => 'always_first', 'sort_algorithm' => 'none'],
        'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],
        'concat_space' => ['spacing' => 'one'],
        'echo_tag_syntax' => ['format' => 'short'],
        'fully_qualified_strict_types' => false,
        'global_namespace_import' => true,
        'increment_style' => ['style' => 'post'],
        'method_argument_space' => ['on_multiline' => 'ignore', 'after_heredoc' => true],
        'multiline_comment_opening_closing' => false,
        'no_alternative_syntax' => false,
        'no_blank_lines_after_phpdoc' => false,
        'no_superfluous_elseif' => false,
        'no_unneeded_control_parentheses' => ['statements' => []],
        'phpdoc_align' => false,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        // PHPCS's RequireMultiLineCall wraps long throws; the two would otherwise fight over them.
        'single_line_throw' => false,
        'trailing_comma_in_multiline' => ['after_heredoc' => true, 'elements' => ['arrays', 'arguments', 'parameters']],
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
    ])
    ->setCacheFile(__DIR__ . '/cache/php-cs-fixer.cache')
    ->setFinder($finder)
;
