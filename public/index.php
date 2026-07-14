<?php

declare(strict_types=1);

use App\AppFactory;
use Modufolio\Psr7\Http\Emitter;
use Modufolio\Psr7\Http\Factory\ServerRequestCreatorFactory;

require_once dirname(__DIR__).'/bootstrap.php';

$request = ServerRequestCreatorFactory::create()->fromGlobals();
$app = AppFactory::create(dirname(__DIR__));

(new Emitter())->emit($app->handle($request));
