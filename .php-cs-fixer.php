<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/Tests',
    ]);

$config = new PhpCsFixer\Config();

// https://cs.symfony.com/doc/ruleSets/index.html
// https://cs.symfony.com/doc/rules/

return $config->setRules([
    'declare_strict_types' => false,
    '@Symfony' => true,
    'yoda_style' => false,
    'phpdoc_summary' => false,
    'phpdoc_types_order' => false,
    'ordered_imports' => true,
    'no_unused_imports' => true,
    'single_line_throw' => false,
    'phpdoc_align' => false,
    'concat_space' => ['spacing' => 'one'],
    'no_superfluous_elseif' => true,
    'no_useless_else' => true,
    'nullable_type_declaration_for_default_null_value' => [
        'use_nullable_type_declaration' => true,
    ],
    'list_syntax' => ['syntax' => 'short'],
    'array_indentation' => true,
    'class_definition' => [
        'multi_line_extends_each_single_line' => true,
    ],
    'standardize_increment' => false,
])->setFinder($finder)->setRiskyAllowed(true);
