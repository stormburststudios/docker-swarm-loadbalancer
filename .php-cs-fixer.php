<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfig;

$finder = Finder::create();
$finder->in(__DIR__);

return (new Config())
    ->setParallelConfig(new ParallelConfig(10, 20, 120))
    ->setRiskyAllowed(true)
    ->setHideProgress(false)
    ->setRules([
        '@PhpCsFixer'                      => true,
        // '@PhpCsFixer:risky'                => true,
        '@PHP82Migration'                  => true,
        '@PHP80Migration:risky'            => true,
        '@PSR12'                           => true,
        '@PSR12:risky'                     => true,
        '@PHPUnit100Migration:risky'       => true,

        'binary_operator_spaces'     => [
            'default'   => 'align_single_space_minimal',
            'operators' => [
                '='  => 'align_single_space',
                '=>' => 'align_single_space',
            ],
        ],
        'types_spaces'               => [
            'space'                => 'single',
            'space_multiple_catch' => 'single',
        ],

        // Annoyance-fixers:
        'concat_space'               => ['spacing' => 'one'], // This one is a matter of taste.
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed'         => false,
            'allow_unused_params' => false,
            'remove_inheritdoc'   => true,
        ],
        'yoda_style'                 => false, // Disabled as its annoying. Comes with @PhpCsFixer
        'native_function_invocation' => false, // Disabled as adding count($i) -> \count($i) is annoying, but supposedly more performant
    ])
    ->setFinder($finder)
;
