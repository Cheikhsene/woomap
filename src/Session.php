<?php
/**
 * Secure session management.
 * Handles session lifecycle, encryption, and authentication state.
 */

if (!defined('WOOMAP')) exit;

class Session
{
    private static bool $started = false;

    /**
     * Start session with security hardening.
     */
    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        // Security settings
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', '1');
        }

        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('woomap_session');
        session_start();

        // Regenerate session ID periodically to prevent fixation
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }

        // Check session expiry
        if (isset($_SESSION['_last_activity'])) {
            if (time() - $_SESSION['_last_activity'] > SESSION_LIFETIME) {
                self::destroy();
                self::start();
                return;
            }
        }
        $_SESSION['_last_activity'] = time();

        self::$started = true;
    }

    /**
     * Get a session value.
     */
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session value.
     */
    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Remove a session value.
     */
    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Check if a session key exists.
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Destroy the session completely.
     */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        self::$started = false;
    }

    /**
     * Check if the user is authenticated.
     */
    public static function isAuthenticated(): bool
    {
        return self::has('authenticated') && self::get('authenticated') === true;
    }

    /**
     * Encrypt data using AES-256-CBC.
     */
    public static function encrypt(string $data): string
    {
        $key = hash('sha256', APP_SECRET, true);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Decrypt data encrypted with encrypt().
     */
    public static function decrypt(string $data): string
    {
        $key = hash('sha256', APP_SECRET, true);
        $decoded = base64_decode($data);
        if ($decoded === false || strpos($decoded, '::') === false) {
            return '';
        }
        [$iv, $encrypted] = explode('::', $decoded, 2);
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Flash a message for the next request.
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Get and clear flash messages.
     */
    public static function getFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }
}
