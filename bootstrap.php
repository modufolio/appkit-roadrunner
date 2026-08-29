<?php

declare(strict_types=1);

use Modufolio\Appkit\Core\Env;

const BASE_DIR = __DIR__;

require __DIR__.'/vendor/autoload.php';

// Publish the environment before anything reads configuration. Once frozen it
// is immutable and reachable anywhere through env() / Env::instance(). Real
// environment variables (RoadRunner's server.env, phpunit.xml <env>) always
// take precedence over the .env file.
(new Env())->fromFile(__DIR__.'/.env')->freeze();
