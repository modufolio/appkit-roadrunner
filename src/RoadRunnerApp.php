<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RoadRunnerApp extends App
{
    private int $requestCount = 0;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->state?->reset();
        $this->state = null;

        $this->state = new RoadRunnerApplicationState($request, $this->baseDir, $this->firewallConfig);

        ++$this->requestCount;
        $debug = $this->environment()->isDev() && $request->hasHeader('X-Debug');
        $startTime = $debug ? hrtime(true) : 0;
        $startMemory = $debug ? memory_get_usage() : 0;

        try {
            $response = $this->handleAuthentication($request);
        } catch (\Throwable $e) {
            $response = $this->exceptionHandler()->handle($e, $request);
        }

        $response = $this->attachSessionCookie($response);

        if ($debug) {
            $response = $this->attachDebugHeaders($response, $startTime, $startMemory);
        }

        return $this->prepareResponse()->prepare($request, $response);
    }

    private function attachDebugHeaders(
        ResponseInterface $response,
        int|float $startTime,
        int $startMemory,
    ): ResponseInterface {
        $elapsedMs = (hrtime(true) - $startTime) / 1_000_000;
        $deltaKb = (memory_get_usage() - $startMemory) / 1024;

        return $response
            ->withHeader('X-Worker-PID', (string) getmypid())
            ->withHeader('X-Worker-Request-Count', (string) $this->requestCount)
            ->withHeader('X-Request-Time-Ms', sprintf('%.2f', $elapsedMs))
            ->withHeader('X-Memory-Delta-KB', sprintf('%.2f', $deltaKb))
            ->withHeader('X-Memory-Peak-MB', sprintf('%.2f', memory_get_peak_usage() / 1024 / 1024));
    }

    private function attachSessionCookie(ResponseInterface $response): ResponseInterface
    {
        if (!$this->state instanceof RoadRunnerApplicationState) {
            return $response;
        }

        if (!$this->state->isNewSession()) {
            return $response;
        }

        $sessionId = $this->state->getSessionId();
        if (null === $sessionId) {
            return $response;
        }

        $cookie = sprintf(
            '%s=%s; Path=/; HttpOnly; SameSite=Lax',
            $this->state->getSessionCookieName(),
            $sessionId
        );

        return $response->withAddedHeader('Set-Cookie', $cookie);
    }
}
