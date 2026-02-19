<?php
/**
 * Multi-store management.
 * Stores encrypted store configurations on disk.
 */

if (!defined('WOOMAP')) exit;

class Store
{
    private static string $storeFile = '';

    /**
     * Get the store file path for the current session.
     */
    private static function getStoreFile(): string
    {
        if (empty(self::$storeFile)) {
            // Each user session gets its own store file
            $sessionId = session_id();
            self::$storeFile = DATA_DIR . '/stores/' . md5($sessionId) . '.dat';
        }
        return self::$storeFile;
    }

    /**
     * Load all stores from disk.
     */
    private static function loadStores(): array
    {
        $file = self::getStoreFile();
        if (!file_exists($file)) {
            return [];
        }

        $encrypted = file_get_contents($file);
        if (empty($encrypted)) {
            return [];
        }

        $json = Session::decrypt($encrypted);
        if (empty($json)) {
            return [];
        }

        $stores = json_decode($json, true);
        return is_array($stores) ? $stores : [];
    }

    /**
     * Save all stores to disk.
     */
    private static function saveStores(array $stores): void
    {
        $json = json_encode($stores);
        $encrypted = Session::encrypt($json);
        file_put_contents(self::getStoreFile(), $encrypted, LOCK_EX);
    }

    /**
     * Get all configured stores.
     */
    public static function getAll(): array
    {
        return self::loadStores();
    }

    /**
     * Get a store by ID.
     */
    public static function get(string $id): ?array
    {
        $stores = self::loadStores();
        return $stores[$id] ?? null;
    }

    /**
     * Add or update a store.
     */
    public static function add(array $storeData): string
    {
        $id = $storeData['id'] ?? md5(uniqid('store_', true));
        $storeData['id'] = $id;

        $stores = self::loadStores();
        $stores[$id] = $storeData;
        self::saveStores($stores);

        return $id;
    }

    /**
     * Remove a store.
     */
    public static function delete(string $id): void
    {
        $stores = self::loadStores();
        unset($stores[$id]);
        self::saveStores($stores);

        // If this was the active store, clear active
        if (Session::get('active_store') === $id) {
            Session::delete('active_store');
        }
    }

    /**
     * Set the active store.
     */
    public static function setActive(string $id): void
    {
        Session::set('active_store', $id);
    }

    /**
     * Get the active store configuration.
     */
    public static function getActive(): ?array
    {
        $id = Session::get('active_store');
        if (!$id) {
            // Default to first store
            $stores = self::loadStores();
            if (empty($stores)) {
                return null;
            }
            $first = reset($stores);
            self::setActive($first['id']);
            return $first;
        }
        return self::get($id);
    }

    /**
     * Get store count.
     */
    public static function count(): int
    {
        return count(self::loadStores());
    }
}
