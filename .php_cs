<?php

use PhpCsFixer\Config;
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('build')
    ->exclude('lib')
    ->exclude('vendor');

return Config::create()
    //->registerCustomFixers(new Fixers())
    ->setRiskyAllowed(true)
    ->setUsingCache(false)
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,

        'array_syntax' => ['syntax' => 'short'],
        'blank_line_after_opening_tag' => false,
        'class_attributes_separation' => ['elements' => ['method', 'property']],
        'concat_space' => ['spacing' => 'one'],
        'doctrine_annotation_indentation' => true,
        'doctrine_annotation_spaces' => true,
        'general_phpdoc_annotation_remove' => [
            'annotations' => ['copyright', 'category'],
        ],
        //'header_comment' => ['header' => $header, 'separate' => 'bottom', 'commentType' => 'PHPDoc'],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_superfluous_phpdoc_tags' => true,
        'ordered_class_elements' => true,
        'ordered_imports' => true,
        'phpdoc_order' => true,
        'phpdoc_summary' => false,
        'phpdoc_var_annotation_correct_order' => true,
        'php_unit_test_case_static_method_calls' => true,
        'single_line_throw' => false,
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
    ])
    ->setFinder($finder);
