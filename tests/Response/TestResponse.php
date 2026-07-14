<?php

declare(strict_types=1);

namespace App\Tests\Response;

use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

class TestResponse
{
    /** @var array<string, mixed>|null */
    private ?array $jsonData = null;
    private bool $jsonParsed = false;

    public function __construct(protected ResponseInterface $response)
    {
    }

    public static function fromResponse(ResponseInterface $response): self
    {
        return new self($response);
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    // ----------------------------
    // Status assertions
    // ----------------------------

    public function assertStatus(int $expected): self
    {
        Assert::assertSame(
            $expected,
            $this->response->getStatusCode(),
            "Expected status {$expected}, got {$this->response->getStatusCode()}"
        );

        return $this;
    }

    public function assertRedirect(?string $uri = null): self
    {
        $status = $this->response->getStatusCode();
        Assert::assertTrue(
            in_array($status, [301, 302, 303, 307, 308]),
            "Expected a redirect status code, got {$status}"
        );

        if (null !== $uri) {
            $this->assertHeader('Location', $uri);
        }

        return $this;
    }

    // ----------------------------
    // Header assertions
    // ----------------------------

    public function assertHeader(string $name, string $expected): self
    {
        $actual = $this->response->getHeaderLine($name);
        Assert::assertSame(
            $expected,
            $actual,
            "Expected header '{$name}' to be '{$expected}', got '{$actual}'"
        );

        return $this;
    }

    // ----------------------------
    // Body helpers
    // ----------------------------

    public function getContent(): string
    {
        $body = $this->response->getBody();
        $body->rewind();

        return $body->getContents();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonData(): array
    {
        if (!$this->jsonParsed) {
            $this->parseJsonData();
        }

        return $this->jsonData ?? [];
    }

    private function parseJsonData(): void
    {
        $this->jsonParsed = true;
        $body = $this->getContent();

        if (empty($body)) {
            $this->jsonData = [];

            return;
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $this->jsonData = is_array($data) ? $data : [];
        } catch (\JsonException $e) {
            Assert::fail('Invalid JSON response: '.$e->getMessage()."\nBody: ".$body);
        }
    }

    // ----------------------------
    // Inertia.js assertions
    // ----------------------------

    public function assertInertia(): self
    {
        $data = $this->jsonData();

        Assert::assertArrayHasKey('component', $data,
            'Response is not an Inertia response — expected "component" key. Body: '.$this->getContent()
        );
        Assert::assertArrayHasKey('props', $data,
            'Response is not an Inertia response — expected "props" key. Body: '.$this->getContent()
        );

        return $this;
    }

    public function component(string $expected): self
    {
        $actual = $this->jsonData()['component'] ?? null;
        Assert::assertSame($expected, $actual, "Expected Inertia component '{$expected}', got '{$actual}'");

        return $this;
    }

    public function hasProp(string $key): self
    {
        $props = $this->jsonData()['props'] ?? [];
        Assert::assertArrayHasKey($key, $props, "Inertia prop '{$key}' is missing");

        return $this;
    }

    public function whereProp(string $key, mixed $expected): self
    {
        $props = $this->jsonData()['props'] ?? [];
        $actual = $this->arrayGet($props, $key);
        Assert::assertEquals($expected, $actual, "Inertia prop '{$key}' value mismatch");

        return $this;
    }

    // ----------------------------
    // Debugging
    // ----------------------------

    public function dump(): self
    {
        echo 'Status: '.$this->response->getStatusCode()."\n";
        echo 'Headers: '.json_encode($this->response->getHeaders())."\n";
        echo 'Body: '.$this->getContent()."\n";

        return $this;
    }

    public function dd(): never
    {
        $this->dump();
        exit(1);
    }

    // ----------------------------
    // Internals
    // ----------------------------

    /**
     * @param array<string, mixed> $array
     */
    private function arrayGet(array $array, string $key, mixed $default = null): mixed
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }
}
