<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Jobs\JobHandlerInterface;
use App\Tests\Case\AppTestCase;

final class JobsTest extends AppTestCase
{
    /** @return array<string, JobHandlerInterface> */
    private function handlers(): array
    {
        $handlers = (require dirname(__DIR__, 2) . '/config/jobs.php')($this->app());
        self::assertIsArray($handlers);

        return $handlers;
    }

    public function testJobsConfigBuildsHandlersFromTheApp(): void
    {
        $handlers = $this->handlers();

        self::assertArrayHasKey('ping', $handlers);
        self::assertContainsOnlyInstancesOf(JobHandlerInterface::class, $handlers);
    }

    public function testPingHandlerWritesThePayloadToTheJobsLog(): void
    {
        $logFile = $this->app()->varDir() . '/log/jobs.log';
        @unlink($logFile);

        $payload = 'feature-test-' . bin2hex(random_bytes(4));
        $this->handlers()['ping']->handle($payload);

        self::assertFileExists($logFile);
        self::assertStringContainsString($payload, (string) file_get_contents($logFile));
    }

    public function testHandlersSurviveThePerTaskResetCycle(): void
    {
        // Mirrors the worker loop: handle → $app->reset() → handle. Handlers
        // are constructed once per worker process, so they must keep working
        // after the app clears its per-task state between tasks.
        $logFile = $this->app()->varDir() . '/log/jobs.log';
        @unlink($logFile);

        $handlers = $this->handlers();

        $first = 'task-one-' . bin2hex(random_bytes(4));
        $handlers['ping']->handle($first);

        $this->app()->reset();
        $this->app()->initializeTestState();

        $second = 'task-two-' . bin2hex(random_bytes(4));
        $handlers['ping']->handle($second);

        $log = (string) file_get_contents($logFile);
        self::assertStringContainsString($first, $log);
        self::assertStringContainsString($second, $log);
    }
}
