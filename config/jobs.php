<?php

declare(strict_types=1);

use App\App;
use App\Jobs\PingHandler;

// Task name → handler for the jobs pool. Jobs are the application's own
// deferred work, so handlers draw their dependencies from the booted App —
// one construction site, no separate wiring. Handlers are constructed once
// per worker process and reused across tasks; the worker calls $app->reset()
// after every task, so only application-level services belong here (never
// session(), tokenStorage(), or request() — no request creates their state).
return function (App $app): array {
    return [
        'ping' => new PingHandler($app->varDir() . '/log/jobs.log'),
    ];
};
