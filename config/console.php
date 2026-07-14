<?php

declare(strict_types=1);

use App\Console\ConsoleRunner;

require dirname(__DIR__).'/bootstrap.php';

$classLoader = require dirname(__DIR__).'/vendor/autoload.php';

$console = new ConsoleRunner(
    classLoader: $classLoader,
    userClass: App\Entity\User::class,
    projectDir: dirname(__DIR__),
);

$console->addDefaultCommands();
$console->addOrmCommands();
$console->addMigrationsCommands();

$console->run();
