<?php

declare(strict_types=1);

$migrationsPath = dirname(__DIR__).'/database/migrations';

if (!is_dir($migrationsPath)) {
    mkdir($migrationsPath, 0755, true);
}

return [
    'table_storage' => [
        'table_name' => 'migrations',
        'version_column_name' => 'version',
        'version_column_length' => 1024,
        'executed_at_column_name' => 'executed_at',
        'execution_time_column_name' => 'execution_time',
    ],
    'migrations_paths' => [
        'Database\Migrations' => $migrationsPath,
    ],
    'all_or_nothing' => false,
    'transactional' => true,
    'check_database_platform' => true,
    'organize_migrations' => 'none',
];
