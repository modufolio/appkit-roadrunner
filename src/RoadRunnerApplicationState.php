<?php

declare(strict_types=1);

namespace App;

use Modufolio\Appkit\Core\AbstractApplicationState;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;
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
    private ?string $requestSessionId = null;

    /**
     * One handler per worker process, not per request. A new state object is
     * created for every request, but the handler (and for Redis, its TCP
     * connection) is safe to reuse across requests — recreating it would throw
     * away the main benefit of a long-lived worker.
     */
    private static ?\SessionHandlerInterface $handler = null;

    public function getSession(): FlashBagAwareSessionInterface
    {
        if (null !== $this->session) {
            return $this->session;
        }

        $cookies = $this->request->getCookieParams();
        $requestSessionId = $cookies[$this->sessionCookieName] ?? null;

        $handler = self::$handler ??= $this->createSessionHandler();

        $this->sessionStorage = new NativeSessionStorage([
            'name' => $this->sessionCookieName,
            'use_cookies' => 0,
            'use_trans_sid' => 0,
            'cache_limiter' => '',
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ], $handler);

        $this->session = new Session($this->sessionStorage);

        if (null !== $requestSessionId && $this->isValidSessionId($requestSessionId)) {
            session_id($requestSessionId);
            $this->requestSessionId = $requestSessionId;
        } else {
            if ('' !== session_id()) {
                session_id('');
            }
        }

        $this->session->start();

        return $this->session;
    }

    /**
     * True when the client's cookie no longer matches the live session id —
     * a fresh session, or an id migration during login. Computed at call time
     * rather than captured when the session starts, because the firewall
     * regenerates the id *after* start; a flag set during start() would miss
     * the migration and the client would keep a dead session id.
     */
    public function isNewSession(): bool
    {
        if (null === $this->session || !$this->session->isStarted()) {
            return false;
        }

        return session_id() !== $this->requestSessionId;
    }

    public function getSessionId(): ?string
    {
        if (null !== $this->session && $this->session->isStarted()) {
            return session_id() ?: null;
        }

        return null;
    }

    public function reset(): void
    {
        if (null !== $this->session && $this->session->isStarted()) {
            $this->session->save();
        }

        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }

        if ('' !== session_id()) {
            session_id('');
        }

        $_SESSION = [];
        $this->requestSessionId = null;

        parent::reset();
    }

    private function isValidSessionId(string $id): bool
    {
        return '' !== $id && 1 === preg_match('/^[a-zA-Z0-9,\-]{22,256}$/', $id);
    }

    /**
     * Build the session handler selected by SESSION_DRIVER.
     *
     * `file` (the default) keeps sessions in var/sessions — zero dependencies,
     * right for a single server. `redis` keeps them in Redis (REDIS_URL) so
     * any worker on any host can serve any request — the choice for multiple
     * servers or containers, where the local filesystem is ephemeral. Note
     * that the Redis handler is lockless (last write wins): two parallel
     * requests on one session won't queue on a file lock, but they can
     * overwrite each other's session writes.
     */
    private function createSessionHandler(): \SessionHandlerInterface
    {
        $driver = (string) env('SESSION_DRIVER', 'file');

        return match ($driver) {
            'file' => new NativeFileSessionHandler($this->baseDir.'/var/sessions'),
            // @phpstan-ignore argument.type (createRedisClient returns \Redis, or \Predis\Client when installed — a type PHPStan can't see without predis/predis)
            'redis' => new RedisSessionHandler($this->createRedisClient(), ['prefix' => 'session:']),
            default => throw new \RuntimeException(sprintf('Unsupported SESSION_DRIVER "%s" — expected "file" or "redis".', $driver)),
        };
    }

    /**
     * @return object A \Redis or \Predis\Client instance, whichever is available
     */
    private function createRedisClient(): object
    {
        $url = (string) env('REDIS_URL', 'redis://127.0.0.1:6379');
        $parts = parse_url($url);

        if (false === $parts || !isset($parts['host'])) {
            throw new \RuntimeException(sprintf('Invalid REDIS_URL "%s".', $url));
        }

        if (extension_loaded('redis')) {
            $redis = new \Redis();
            $redis->connect($parts['host'], $parts['port'] ?? 6379);

            if (isset($parts['pass'])) {
                $redis->auth(isset($parts['user']) ? [$parts['user'], $parts['pass']] : $parts['pass']);
            }

            $database = trim($parts['path'] ?? '', '/');
            if ('' !== $database) {
                $redis->select((int) $database);
            }

            return $redis;
        }

        // predis/predis is not a dependency of this skeleton, so refer to it
        // dynamically — installed it works, absent it stays invisible to
        // autoloading and static analysis alike.
        $predis = 'Predis\Client';
        if (class_exists($predis)) {
            return new $predis($url);
        }

        throw new \RuntimeException('SESSION_DRIVER=redis requires the phpredis extension or the predis/predis package.');
    }
}
