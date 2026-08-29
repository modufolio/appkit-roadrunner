<?php

declare(strict_types=1);

use App\AppFactory;
use App\RoadRunnerApp;
use Modufolio\Psr7\Http\Factory\Psr17Factory;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Jobs\Consumer;
use Spiral\RoadRunner\Worker;

require_once __DIR__ . '/bootstrap.php';

// One worker script serves both pools: RoadRunner starts it with RR_MODE=http
// for the HTTP pool and RR_MODE=jobs for the jobs pool (see .rr.yaml).
$mode = Environment::fromGlobals()->getMode();

if ($mode === Mode::MODE_JOBS) {
    // ── Jobs mode ───────────────────────────────────────────────────────────
    // Jobs are the application's own deferred work, so this branch boots the
    // same App as the HTTP branch and handlers draw their dependencies from
    // it (config/jobs.php). The console stays isolated because it is repair
    // tooling; jobs are not — if the app cannot boot, its jobs should stop.
    $app = AppFactory::create(__DIR__, RoadRunnerApp::class);
    assert($app instanceof RoadRunnerApp);

    // Task name → handler, constructed once per worker process and reused
    // across tasks, same lifecycle discipline as the HTTP app.
    $handlers = (require __DIR__ . '/config/jobs.php')($app);

    $consumer = new Consumer();

    while ($task = $consumer->waitTask()) {
        try {
            $handler = $handlers[$task->getName()] ?? null;

            if ($handler === null) {
                $task->fail(sprintf('No handler registered for task "%s".', $task->getName()));
                continue;
            }

            $handler->handle($task->getPayload());
            $task->ack();
        } catch (\Throwable $e) {
            // Let the pipeline decide on redelivery; memory drops, amqp/sqs requeue.
            $task->fail($e);
        } finally {
            // Same reset contract as the HTTP loop: per-task state (above all
            // the EntityManager identity map) must not bleed into the next task.
            $app->reset();
            gc_collect_cycles();
        }
    }

    return;
}

// ── HTTP mode ───────────────────────────────────────────────────────────────
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
