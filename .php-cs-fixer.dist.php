<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/examples',
        __DIR__ . '/scripts',
        __DIR__ . '/consumer-verification/bin',
    ])
    ->append([__FILE__]);

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS3x0' => true,
        // The supplemental PER-CS 3.1 verifier owns the full clear-comment contract.
        'no_break_comment' => false,
        // PER-CS 3.1 forbids a multiline array's opening bracket on its own
        // line, even when that array is a later argument in a multiline call.
        // Keep the 3.0 fixer from restoring that now-invalid 3.0 arrangement;
        // the repository-owned 3.1 verifier checks the array rule and the
        // affected multiline-call layout around such array arguments.
        'method_argument_space' => ['on_multiline' => 'ignore'],
    ])
    ->setFinder($finder);
