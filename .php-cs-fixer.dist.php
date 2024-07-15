<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    //->exclude('somedir')
    //->notPath('src/Symfony/Component/Translation/Tests/fixtures/resources.php')
    ->in(__DIR__);

$config = new Config();
return $config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PhpCsFixer' => true,
        '@Symfony' => true,
        '@PHP80Migration' => true,
        '@PHP81Migration' => true,
        '@PHP82Migration' => true,
        '@PHP83Migration' => true,
        'cast_spaces' => ['space' => 'none'],
        'class_attributes_separation' => false,
        'concat_space' => ['spacing' => 'one'],
        'echo_tag_syntax' => ['format' => 'short'],
        'fully_qualified_strict_types' => false,
        'global_namespace_import' => [],
        'increment_style' => ['style' => 'post'],
        'method_argument_space' => ['on_multiline' => 'ignore', 'after_heredoc' => true],
        'multiline_comment_opening_closing' => false,
        'no_alternative_syntax' => false,
        'no_blank_lines_after_phpdoc' => false,
        'no_superfluous_elseif' => false,
        'no_unneeded_control_parentheses' => ['statements' => []],
        'phpdoc_align' => false,
        'phpdoc_separation' => [
            'groups' => [
                ['author', 'copyright', 'license'],
                ['category', 'package', 'subpackage'],
                ['ORM\*'],
                ['property', 'property-read', 'property-write'],
                ['deprecated', 'link', 'see', 'since']
            ],
        ],
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        'trailing_comma_in_multiline' => ['after_heredoc' => true, 'elements' => ['arrays', 'arguments', 'parameters']],
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
    ])
    ->setFinder($finder)
;
