<?php

$finder = PhpCsFixer\Finder::create()
    ->in(['src', 'tests', 'config'])
    ->notPath('__cache')
;

return (new PhpCsFixer\Config)
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_class_elements' => true,
        'yoda_style' => false,
        'native_function_invocation' => [
            'include' => ['@compiler_optimized']
        ],
        'php_unit_method_casing' => false,
        'declare_strict_types' => true
    ])
;
