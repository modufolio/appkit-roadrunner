<?php

declare(strict_types=1);

namespace App;

use Modufolio\Appkit\Core\AbstractApplicationState;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * ApplicationState implementation for the RoadRunner runtime.
 *
 * Unlike the native PHP server, a RoadRunner worker handles many requests in a
 * single long-lived process. That breaks PHP's automatic Set-Cookie emission
 * for sessions and lets PHP's session globals leak between requests.
 *
 * This class addresses both: it tells PHP not to manage cookies (the framework
 * does that on the PSR-7 response) and explicitly closes the session at the
 * end of each request so the next request starts clean.
 */
final class RoadRunnerApplicationState extends AbstractApplicationState
{
    private bool $newSession = false;

    public function getSession(): FlashBagAwareSessionInterface
    {
        if ($this->session !== null) {
            return $this->session;
        }

        $cookies = $this->request->getCookieParams();
        $requestSessionId = $cookies[$this->sessionCookieName] ?? null;

        $handler = new NativeFileSessionHandler($this->baseDir . '/var/sessions');

        $this->sessionStorage = new NativeSessionStorage([
            'name'             => $this->sessionCookieName,
            'use_cookies'      => 0,
            'use_trans_sid'    => 0,
            'cache_limiter'    => '',
            'cookie_httponly'  => true,
            'cookie_samesite'  => 'Lax',
        ], $handler);

        $this->session = new Session($this->sessionStorage);

        if ($requestSessionId !== null && $this->isValidSessionId($requestSessionId)) {
            session_id($requestSessionId);
            $this->newSession = false;
        } else {
            if (session_id() !== '') {
                session_id('');
            }
            $this->newSession = true;
        }

        $this->session->start();

        if ($requestSessionId !== null && session_id() !== $requestSessionId) {
            $this->newSession = true;
        }

        return $this->session;
    }

    public function isNewSession(): bool
    {
        return $this->newSession;
    }

    public function getSessionId(): ?string
    {
        if ($this->session !== null && $this->session->isStarted()) {
            return session_id() ?: null;
        }

        return null;
    }

    public function reset(): void
    {
        if ($this->session !== null && $this->session->isStarted()) {
            $this->session->save();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (session_id() !== '') {
            session_id('');
        }

        $_SESSION = [];
        $this->newSession = false;

        parent::reset();
    }

    private function isValidSessionId(string $id): bool
    {
        return $id !== '' && preg_match('/^[a-zA-Z0-9,\-]{22,256}$/', $id) === 1;
    }
}
