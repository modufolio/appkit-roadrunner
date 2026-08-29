<?php

declare(strict_types=1);

return [
    'includes' => [
        __DIR__ . '/vendor/phpstan/phpstan-doctrine/extension.neon',
        __DIR__ . '/vendor/phpstan/phpstan-doctrine/rules.neon',
        __DIR__ . '/vendor/phpstan/phpstan-phpunit/extension.neon',
        __DIR__ . '/vendor/phpstan/phpstan-phpunit/rules.neon',
    ],
    'parameters' => [
        'level' => 8,
        'paths' => [
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ],
        'ignoreErrors' => [
            // Redundant PHPUnit type assertions (assertTrue(true) in the
            // placeholder test, assertions on values PHPStan already knows).
            // Test-readability noise, never a bug — same ignore appkit uses.
            ['identifier' => 'method.alreadyNarrowedType', 'path' => __DIR__ . '/tests/*', 'reportUnmatched' => false],
        ],
    ],
];
