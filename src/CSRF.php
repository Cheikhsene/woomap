<?php
/**
 * CSRF protection.
 * Generates and validates tokens for form submissions.
 */

if (!defined('WOOMAP')) exit;

class CSRF
{
    /**
     * Generate or retrieve the current CSRF token.
     */
    public static function token(): string
    {
        if (!Session::has('_csrf_token')) {
            Session::set('_csrf_token', bin2hex(random_bytes(32)));
        }
        return Session::get('_csrf_token');
    }

    /**
     * Generate an HTML hidden input field with the CSRF token.
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }

    /**
     * Generate a meta tag for AJAX requests.
     */
    public static function meta(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<meta name="csrf-token" content="' . $token . '">';
    }

    /**
     * Verify a submitted CSRF token.
     */
    public static function verify(?string $token = null): bool
    {
        if ($token === null) {
            $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        $sessionToken = Session::get('_csrf_token', '');
        if (empty($token) || empty($sessionToken)) {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }

    /**
     * Regenerate the CSRF token (call after successful verification).
     */
    public static function regenerate(): void
    {
        Session::set('_csrf_token', bin2hex(random_bytes(32)));
    }
}
