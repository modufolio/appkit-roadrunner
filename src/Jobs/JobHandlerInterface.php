<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * A jobs-mode task handler. Implementations must be idempotent where the
 * pipeline may redeliver (memory does not; amqp/sqs can).
 */
interface JobHandlerInterface
{
    public function handle(string $payload): void;
}
