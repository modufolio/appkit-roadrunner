<?php

declare(strict_types=1);

namespace App\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Minimal PSR-3 file logger.
 *
 * Writes one line per record to `<logDir>/app.log`, with errors mirrored to
 * `error.log` so production tail is small. Replace with Monolog or similar
 * if you need rotation, multiple handlers, structured logging, etc.
 */
final class FileLogger extends AbstractLogger
{
    private const ERROR_LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
    ];

    public function __construct(private readonly string $logDir)
    {
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $entry = sprintf(
            "[%s] %s: %s%s\n",
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            strtoupper((string) $level),
            $this->interpolate((string) $message, $context),
            [] !== $context ? ' '.json_encode($this->scrubContext($context), JSON_UNESCAPED_SLASHES) : '',
        );

        file_put_contents($this->logDir.'/app.log', $entry, FILE_APPEND | LOCK_EX);

        if (in_array($level, self::ERROR_LEVELS, true)) {
            file_put_contents($this->logDir.'/error.log', $entry, FILE_APPEND | LOCK_EX);
        }
    }

    /** @param array<string, mixed> $context */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof \Stringable) {
                $replace['{'.$key.'}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * Scrub fields that should never reach a log file.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function scrubContext(array $context): array
    {
        foreach (['password', 'plainPassword', 'token', 'authorization', 'cookie'] as $key) {
            if (array_key_exists($key, $context)) {
                $context[$key] = '[redacted]';
            }
        }

        return $context;
    }
}
