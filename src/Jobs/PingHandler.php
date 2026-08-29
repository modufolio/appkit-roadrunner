<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * Smoke-test handler: appends the payload to var/log/jobs.log so an
 * end-to-end push → consume run has an observable effect.
 */
final class PingHandler implements JobHandlerInterface
{
    public function __construct(private readonly string $logFile)
    {
    }

    public function handle(string $payload): void
    {
        $dir = \dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $this->logFile,
            sprintf("[%s] pid=%d ping %s\n", date('c'), getmypid(), $payload),
            FILE_APPEND | LOCK_EX,
        );
    }
}
