<?php
declare(strict_types=1);

namespace core;

/**
 * Stores simple serialized cache entries on disk.
 *
 * The cache is keyed by hashes so callers can memoize expensive computed values.
 */
class cache
{
    /**
     * Receives environment settings used to locate the cache directory.
     */
    public function __construct(private env $env)
    {
    }

    /**
     * Returns a cached value or resolves and stores a fresh one when expired.
     */
    public function remember(string $key, int $ttl, callable $resolver): mixed
    {
        $path = $this->pathForKey($key);

        if (is_file($path)) {
            $payload = @unserialize((string) file_get_contents($path));

            if (
                is_array($payload)
                && isset($payload['expires_at'])
                && (int) $payload['expires_at'] >= time()
                && array_key_exists('value', $payload)
            ) {
                return $payload['value'];
            }
        }

        $value = $resolver();
        $this->store($key, $value, $ttl);

        return $value;
    }

    /**
     * Deletes a single cached entry if it exists.
     */
    public function forget(string $key): void
    {
        $path = $this->pathForKey($key);

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Deletes every cache file from the configured cache directory.
     */
    public function flush(): void
    {
        $directory = $this->directory();

        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*.cache');

        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Persists a cached value together with its expiration timestamp.
     */
    private function store(string $key, mixed $value, int $ttl): void
    {
        $directory = $this->directory();

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $payload = serialize([
            'expires_at' => time() + $ttl,
            'value' => $value,
        ]);

        file_put_contents($this->pathForKey($key), $payload, LOCK_EX);
    }

    /**
     * Builds the on-disk file path for a cache key.
     */
    private function pathForKey(string $key): string
    {
        return $this->directory() . '/' . hash('sha256', $key) . '.cache';
    }

    /**
     * Returns the absolute cache directory path from configuration.
     */
    private function directory(): string
    {
        $configured = trim((string) $this->env->get('CACHE_DIR', 'storage/cache'));

        return dirname(__DIR__) . '/' . trim($configured, '/');
    }
}
