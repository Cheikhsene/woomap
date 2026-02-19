<?php
/**
 * File-based cache system.
 * Stores serialized data with TTL support.
 */

if (!defined('WOOMAP')) exit;

class Cache
{
    private string $dir;
    private int $defaultTtl;

    public function __construct(string $subdir = 'cache', int $defaultTtl = 1800)
    {
        $this->dir = DATA_DIR . '/' . $subdir;
        $this->defaultTtl = $defaultTtl;
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
    }

    /**
     * Generate a safe filename from a cache key.
     */
    private function keyToFile(string $key): string
    {
        return $this->dir . '/' . md5($key) . '.cache';
    }

    /**
     * Get a cached value.
     * Returns null if not found or expired.
     */
    public function get(string $key)
    {
        $file = $this->keyToFile($key);
        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = unserialize($content);
        if ($data === false || !isset($data['expires'], $data['value'])) {
            unlink($file);
            return null;
        }

        if ($data['expires'] > 0 && $data['expires'] < time()) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    /**
     * Store a value in cache.
     * TTL of 0 means no expiration.
     */
    public function set(string $key, $value, ?int $ttl = null): void
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $data = [
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value' => $value,
        ];
        file_put_contents($this->keyToFile($key), serialize($data), LOCK_EX);
    }

    /**
     * Remove a cached value.
     */
    public function delete(string $key): void
    {
        $file = $this->keyToFile($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Clear all cached values in this cache directory.
     */
    public function clear(): void
    {
        $files = glob($this->dir . '/*.cache');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Get or compute a cached value.
     * If the key doesn't exist, calls $callback and caches the result.
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        if ($value !== null) {
            $this->set($key, $value, $ttl);
        }
        return $value;
    }
}
