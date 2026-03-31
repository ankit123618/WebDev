<?php
declare(strict_types=1);

namespace Core;

use core\env;
use Throwable;

/**
 * Writes structured log lines to the file configured for the application.
 *
 * Each entry is enriched with runtime context such as caller information and request data.
 */
class logger
{
    /**
     * Stores the environment service used to resolve log settings.
     */
    public function __construct(private env $env)
    {
    }

    /**
     * Writes an informational log entry.
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /**
     * Writes an error log entry.
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /**
     * Logs a throwable together with its important metadata.
     */
    public function exception(Throwable $exception, string $message = 'Unhandled exception', array $context = []): void
    {
        $this->write('ERROR', $message, $context + [
            'exception_class' => $exception::class,
            'exception_code' => (string) $exception->getCode(),
            'exception_message' => $exception->getMessage(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
        ]);
    }

    /**
     * Returns the absolute path to the active log file.
     */
    public function currentLogFilePath(): string
    {
        return $this->getLogFilePath();
    }

    /**
     * Formats and appends a single log line to disk.
     */
    private function write(string $level, string $message, array $context = []): void
    {
        $timezone = $this->getTimezone();
        date_default_timezone_set($timezone);

        $file = $this->getLogFilePath();
        $directory = dirname($file);

        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            error_log('Logger failed to create directory: ' . $directory);
            return;
        }

        $context = $this->withRuntimeContext($context);
        $line = date('Y-m-d H:i:s') . ' [' . $level . '] ' . $message . $this->formatContext($context) . "\n";

        if (!file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('Logger failed to write to file: ' . $file . ' | message=' . $message, 3, "__DIR__ . /storage/logs/app.log");
        }
    }

    /**
     * Resolves the configured log file path with safe defaults.
     */
    private function getLogFilePath(): string
    {
        $root = dirname(__DIR__);
        $logDir = trim((string) $this->env->get('LOG_DIR', 'storage/logs'));
        $logFile = trim((string) $this->env->get('LOG_FILE', 'app.log'));

        if ($logDir === '') {
            $logDir = 'storage/logs';
        }

        if ($logFile === '') {
            $logFile = 'app.log';
        }

        return $root . '/' . trim($logDir, '/') . '/' . ltrim($logFile, '/');
    }

    /**
     * Merges caller and request metadata into the supplied log context.
     */
    private function withRuntimeContext(array $context): array
    {
        $caller = $this->detectCaller();

        if ($caller !== null) {
            $context += [
                'caller_file' => $caller['file'],
                'caller_line' => $caller['line'],
            ];

            if ($caller['function'] !== '') {
                $context += ['caller_function' => $caller['function']];
            }
        }

        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $requestUri = $_SERVER['REQUEST_URI'] ?? null;

        if (is_string($requestMethod) && $requestMethod !== '') {
            $context += ['request_method' => $requestMethod];
        }

        if (is_string($requestUri) && $requestUri !== '') {
            $context += ['request_uri' => $requestUri];
        }

        return $context;
    }

    /**
     * Finds the first stack frame outside the logger itself.
     */
    private function detectCaller(): ?array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12);

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';

            if ($class === self::class) {
                continue;
            }

            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;

            if (!is_string($file) || !is_int($line)) {
                continue;
            }

            return [
                'file' => $file,
                'line' => $line,
                'function' => (string) ($frame['function'] ?? ''),
            ];
        }

        return null;
    }

    /**
     * Converts context values into a compact string appended to the log line.
     */
    private function formatContext(array $context): string
    {
        if ($context === []) {
            return '';
        }

        $parts = [];

        foreach ($context as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } elseif (!is_scalar($value)) {
                $value = gettype($value);
            }

            $parts[] = $key . '=' . json_encode((string) $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($parts === []) {
            return '';
        }

        return ' | ' . implode(' | ', $parts);
    }

    /**
     * Returns the timezone used for timestamps in log entries.
     */
    private function getTimezone(): string
    {
        $timezone = trim((string) $this->env->get('APP_TIMEZONE', ''));

        if ($timezone !== '') {
            return $timezone;
        }

        $configuredTimezone = trim((string) ini_get('date.timezone'));

        if ($configuredTimezone !== '') {
            return $configuredTimezone;
        }

        return 'UTC';
    }
}
