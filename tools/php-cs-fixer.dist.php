<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in([dirname(__DIR__) . '/src', dirname(__DIR__) . '/tests'])
    ->name('*.php');

// Formatting, import order and modern syntax only. The single risky-flagged rule
// enabled is declare_strict_types, which merely asserts what every file already has.
return (new Config())
    ->setRiskyAllowed(true)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setCacheFile(sys_get_temp_dir() . '/rtly-kit.php-cs-fixer.cache')
    ->setRules([
        '@PSR12' => true,
        '@PHP82Migration' => true,
        'declare_strict_types' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
        'no_unused_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'blank_line_before_statement' => ['statements' => ['return']],
        'single_line_empty_body' => true,
    ])
    ->setFinder($finder);
