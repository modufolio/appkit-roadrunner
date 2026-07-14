<?php

declare(strict_types=1);

return [
    'includes' => [
        __DIR__ . '/vendor/phpstan/phpstan-doctrine/extension.neon',
        __DIR__ . '/vendor/phpstan/phpstan-doctrine/rules.neon',
    ],
    'parameters' => [
        'level' => 8,
        'paths' => [
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ],
    ],
];
