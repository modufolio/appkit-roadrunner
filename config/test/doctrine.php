<?php

declare(strict_types=1);

use Modufolio\Appkit\Doctrine\OrmConfigurator;

return function (OrmConfigurator $orm): void {
    $projectDir = dirname(__DIR__, 2);

    $orm->connection([
        'driver' => 'pdo_sqlite',
        'memory' => true,
    ])->entities(
        $projectDir.'/src/Entity'
    );
};
