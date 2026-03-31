<?php
declare(strict_types=1);

namespace core;

use Dotenv\Dotenv;

/**
 * Loads environment variables from the project root and exposes lookup helpers.
 *
 * Values are loaded lazily so dependent services can request config on demand.
 */
class env
{
    private bool $loaded = false;

    /**
     * Stores the application root path used to locate the environment file.
     */
    public function __construct(private string $rootPath)
    {
    }

    /**
     * Loads environment variables once for the current request lifecycle.
     */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $dotenv = Dotenv::createImmutable($this->rootPath);
        $dotenv->safeLoad();
        $this->loaded = true;
    }

    /**
     * Returns a configuration value from the environment with a fallback default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();

        $value = getenv($key);

        if ($value === false || $value === null) {
            $value = $_SERVER[$key] ?? $_ENV[$key] ?? null;
        }

        if ($value === false || $value === null) {
            return $default;
        }

        return $value;
    }
}
