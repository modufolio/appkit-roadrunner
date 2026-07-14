<?php

declare(strict_types=1);

use Modufolio\Appkit\Doctrine\OrmConfigurator;

return function (OrmConfigurator $orm): void {
    $projectDir = dirname(__DIR__);

    $orm->connection([
        'driver' => 'pdo_sqlite',
        'path' => $projectDir.'/database/data.db',
    ])->entities(
        $projectDir.'/src/Entity'
    );
};
