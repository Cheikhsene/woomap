<?php
/**
 * Authentication handler.
 * Supports WooCommerce Application Passwords and OAuth.
 */

if (!defined('WOOMAP')) exit;

class Auth
{
    /**
     * Login using WordPress Application Password.
     * Verifies credentials by making a test API call.
     *
     * @return array Store info on success
     * @throws RuntimeException on failure
     */
    public static function loginWithAppPassword(string $siteUrl, string $consumerKey, string $consumerSecret): array
    {
        $siteUrl = rtrim(trim($siteUrl), '/');

        if (empty($siteUrl) || empty($consumerKey) || empty($consumerSecret)) {
            throw new RuntimeException('Tous les champs sont requis.');
        }

        if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('URL du site invalide.');
        }

        // Test the connection
        $woo = new WooCommerce($siteUrl, $consumerKey, $consumerSecret);

        try {
            $storeInfo = $woo->testConnection();
        } catch (RuntimeException $e) {
            throw new RuntimeException('Impossible de se connecter : ' . $e->getMessage());
        }

        // Store credentials encrypted in session
        $storeData = [
            'id' => md5($siteUrl . $consumerKey),
            'url' => $siteUrl,
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
            'name' => self::extractStoreName($siteUrl, $storeInfo),
            'auth_method' => 'app_password',
            'connected_at' => date('Y-m-d H:i:s'),
        ];

        // Save store and set as active
        Store::add($storeData);
        Store::setActive($storeData['id']);

        // Set authenticated
        Session::set('authenticated', true);
        Session::set('auth_method', 'app_password');

        return $storeData;
    }

    /**
     * Get the OAuth authorization URL for WooCommerce.
     */
    public static function getOAuthUrl(string $siteUrl, string $callbackUrl): string
    {
        $siteUrl = rtrim(trim($siteUrl), '/');

        if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('URL du site invalide.');
        }

        // Store the site URL in session for the callback
        Session::set('oauth_site_url', $siteUrl);

        $params = [
            'app_name' => APP_NAME,
            'scope' => 'read',
            'user_id' => uniqid('woomap_'),
            'return_url' => $callbackUrl,
            'callback_url' => $callbackUrl,
        ];

        return $siteUrl . '/wc-auth/v1/authorize?' . http_build_query($params);
    }

    /**
     * Handle the OAuth callback from WooCommerce.
     */
    public static function handleOAuthCallback(array $params): array
    {
        // WooCommerce sends: consumer_key, consumer_secret, key_permissions
        if (empty($params['consumer_key']) || empty($params['consumer_secret'])) {
            // Check if it's a JSON body (WooCommerce POSTs to callback)
            $body = file_get_contents('php://input');
            if ($body) {
                $data = json_decode($body, true);
                if ($data) {
                    $params = array_merge($params, $data);
                }
            }
        }

        if (empty($params['consumer_key']) || empty($params['consumer_secret'])) {
            throw new RuntimeException('Clés OAuth non reçues. Veuillez réessayer.');
        }

        $siteUrl = Session::get('oauth_site_url', '');
        if (empty($siteUrl)) {
            throw new RuntimeException('URL du site non trouvée. Veuillez recommencer.');
        }

        // Test the connection
        $woo = new WooCommerce($siteUrl, $params['consumer_key'], $params['consumer_secret']);

        try {
            $storeInfo = $woo->testConnection();
        } catch (RuntimeException $e) {
            throw new RuntimeException('Connexion OAuth échouée : ' . $e->getMessage());
        }

        $storeData = [
            'id' => md5($siteUrl . $params['consumer_key']),
            'url' => $siteUrl,
            'consumer_key' => $params['consumer_key'],
            'consumer_secret' => $params['consumer_secret'],
            'name' => self::extractStoreName($siteUrl, $storeInfo),
            'auth_method' => 'oauth',
            'permissions' => $params['key_permissions'] ?? 'read',
            'connected_at' => date('Y-m-d H:i:s'),
        ];

        // Save store and set as active
        Store::add($storeData);
        Store::setActive($storeData['id']);

        // Set authenticated
        Session::set('authenticated', true);
        Session::set('auth_method', 'oauth');

        // Clean up
        Session::delete('oauth_site_url');

        return $storeData;
    }

    /**
     * Get the current authenticated store info.
     */
    public static function getCurrentStore(): ?array
    {
        if (!Session::isAuthenticated()) {
            return null;
        }
        return Store::getActive();
    }

    /**
     * Logout and clear session.
     */
    public static function logout(): void
    {
        Session::destroy();
    }

    /**
     * Check if the current session is valid.
     */
    public static function check(): bool
    {
        return Session::isAuthenticated() && Store::getActive() !== null;
    }

    /**
     * Extract a readable store name from URL and API response.
     */
    private static function extractStoreName(string $siteUrl, array $storeInfo): string
    {
        if (!empty($storeInfo['store']['meta']['links']['help'])) {
            // Try to get site name from API response
            $parsed = parse_url($siteUrl);
            return $parsed['host'] ?? $siteUrl;
        }

        $parsed = parse_url($siteUrl);
        return $parsed['host'] ?? $siteUrl;
    }
}
