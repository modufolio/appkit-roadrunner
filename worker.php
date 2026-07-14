<?php

declare(strict_types=1);

use App\AppFactory;
use App\RoadRunnerApp;
use Modufolio\Psr7\Http\Factory\Psr17Factory;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

require_once __DIR__ . '/bootstrap.php';

$psr17  = new Psr17Factory();
$worker = new PSR7Worker(Worker::create(), $psr17, $psr17, $psr17);

$app = AppFactory::create(__DIR__, RoadRunnerApp::class);
assert($app instanceof RoadRunnerApp);

$requestCount = 0;
$gcInterval   = 100;

while (true) {
    try {
        $request = $worker->waitRequest();
    } catch (\Throwable $e) {
        $worker->getWorker()->error((string) $e);
        continue;
    }

    if ($request === null) {
        break;
    }

    try {
        $response = $app->handle($request);
        $worker->respond($response);
    } catch (\Throwable $e) {
        $worker->getWorker()->error((string) $e);
    } finally {
        $app->reset();
        if (++$requestCount % $gcInterval === 0) {
            gc_collect_cycles();
        }
    }
}
