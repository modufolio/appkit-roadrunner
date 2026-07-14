<?php

declare(strict_types=1);

namespace App\Tests\Case;

use App\App;
use App\AppFactory;
use App\Tests\Response\TestResponse;
use Doctrine\ORM\Tools\SchemaTool;
use Modufolio\Appkit\Security\User\UserInterface;
use Modufolio\Psr7\Http\ServerRequest;
use Modufolio\Psr7\Http\Stream;
use Modufolio\Psr7\Http\Uri;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\StreamInterface;

abstract class AppTestCase extends BaseTestCase
{
    protected static App $app;

    protected function app(): App
    {
        if (!isset(self::$app)) {
            $app = AppFactory::create(dirname(__DIR__, 2));
            assert($app instanceof App);
            self::$app = $app;
            self::$app->initializeTestState();
        }

        return self::$app;
    }

    protected function tearDown(): void
    {
        if ($this->app()->getState()?->hasSession()) {
            $this->app()->session()->clear();
        }

        $this->app()->reset();
        $this->app()->initializeTestState();
    }

    // ----------------------------
    // Database helpers
    // ----------------------------

    protected function refreshDatabase(): void
    {
        $em = $this->app()->entityManager();
        $connection = $em->getConnection();
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        if (!$metadata) {
            throw new \RuntimeException('No metadata found — check your entity paths in config/doctrine.php.');
        }

        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
            $connection->executeStatement('PRAGMA foreign_keys = OFF');
            $schemaManager = $connection->createSchemaManager();
            foreach ($schemaManager->listTableNames() as $table) {
                $connection->executeStatement("DROP TABLE IF EXISTS {$table}");
            }
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        } else {
            $schemaTool = new SchemaTool($em);
            try {
                $schemaTool->dropSchema($metadata);
            } catch (\Exception) {
            }
        }

        (new SchemaTool($em))->createSchema($metadata);
    }

    // ----------------------------
    // HTTP helpers
    // ----------------------------

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers
     */
    protected function get(string $uri, array $query = [], array $headers = []): TestResponse
    {
        if ($query) {
            $uri .= (str_contains($uri, '?') ? '&' : '?').http_build_query($query);
        }

        return $this->request('GET', $uri, [], null, $headers);
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    protected function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('POST', $uri, $data, null, $headers);
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    protected function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('PUT', $uri, $data, null, $headers);
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    protected function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('PATCH', $uri, $data, null, $headers);
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    protected function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('DELETE', $uri, $data, null, $headers);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function form(string $uri, array $data = []): TestResponse
    {
        return $this->request('POST', $uri, $data, null, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    protected function json(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $headers['Content-Type'] ??= 'application/json';

        return $this->request($method, $uri, $data, null, $headers);
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    protected function request(
        string $method,
        string $uri,
        array $data = [],
        ?string $body = null,
        array $headers = [],
    ): TestResponse {
        $method = strtoupper($method);
        $hasBody = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $contentType = $headers['Content-Type'] ?? null;

        $uriObject = new Uri($uri);
        $stream = $this->buildStream($contentType, $data, $body);

        $headers['Accept'] ??= '*/*';

        // Forward the session cookie so CSRF tokens survive across calls
        if ($this->app()->getState()?->hasSession()) {
            $sessionId = $this->app()->session()->getId();
            if ($sessionId) {
                $headers['Cookie'] = 'PHPSESSID='.$sessionId;
            }
        }

        $serverParams = [
            'HTTP_HOST' => 'localhost',
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'SCRIPT_NAME' => '',
            'QUERY_STRING' => $uriObject->getQuery(),
        ];

        foreach ($headers as $name => $value) {
            $serverParams['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $request = new ServerRequest(
            method: $method,
            uri: $uriObject,
            headers: [],
            body: $stream,
            version: '1.1',
            serverParams: $serverParams
        );

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($hasBody && in_array($contentType, ['application/x-www-form-urlencoded', 'application/json'], true)) {
            $request = $request->withParsedBody($data);
        }

        if ($uriObject->getQuery()) {
            parse_str($uriObject->getQuery(), $queryParams);
            $request = $request->withQueryParams($queryParams);
        }

        if (isset($headers['Cookie'])) {
            $cookieParams = [];
            foreach (explode('; ', $headers['Cookie']) as $cookie) {
                [$name, $value] = explode('=', $cookie, 2) + [null, null];
                if (null !== $name && null !== $value) {
                    $cookieParams[$name] = $value;
                }
            }
            $request = $request->withCookieParams($cookieParams);
        }

        return new TestResponse($this->app()->handle($request));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildStream(?string $contentType, array $data, ?string $raw): StreamInterface
    {
        if (null !== $raw) {
            return Stream::create($raw);
        }

        return match ($contentType) {
            'application/json' => Stream::create(json_encode($data, JSON_THROW_ON_ERROR)),
            'application/x-www-form-urlencoded' => Stream::create(http_build_query($data)),
            default => Stream::create(''),
        };
    }

    // ----------------------------
    // Auth helpers
    // ----------------------------

    protected function actingAs(string $email, string $password): void
    {
        $csrfToken = $this->app()->csrfTokenManager()->getToken('authenticate')->getValue();

        $this->form('/login', [
            'email' => $email,
            'password' => $password,
            '_csrf_token' => $csrfToken,
        ]);

        $token = $this->app()->tokenStorage()->getToken();

        $this->assertNotNull($token, 'Expected an authentication token after login.');
        $this->assertInstanceOf(UserInterface::class, $token->getUser(), 'Expected a valid User after login.');
    }

    protected function login(): void
    {
        $this->actingAs('johndoe@example.com', 'secret');
    }

    protected function logout(): void
    {
        $csrfToken = $this->app()->csrfTokenManager()->getToken('logout')->getValue();

        $this->post('/logout', ['_csrf_token' => $csrfToken], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
    }
}
