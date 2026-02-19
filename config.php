<?php
/**
 * WooMap Configuration
 * Loads environment variables and defines application constants.
 */

// Prevent direct access
if (!defined('WOOMAP')) {
    http_response_code(403);
    exit('Access denied');
}

// Base paths
define('BASE_DIR', __DIR__);
define('SRC_DIR', BASE_DIR . '/src');
define('TEMPLATE_DIR', BASE_DIR . '/templates');
define('PUBLIC_DIR', BASE_DIR . '/public');
define('DATA_DIR', BASE_DIR . '/data');

/**
 * Parse .env file and load variables.
 */
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove surrounding quotes
        if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) {
            $value = $m[2];
        }
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

/**
 * Get an environment variable with optional default.
 */
function env(string $key, $default = null)
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    // Cast booleans
    $lower = strtolower($value);
    if ($lower === 'true') return true;
    if ($lower === 'false') return false;
    if ($lower === 'null') return null;
    return $value;
}

// Load .env file
loadEnv(BASE_DIR . '/.env');

// Application settings
define('APP_NAME', env('APP_NAME', 'WooMap'));
define('APP_URL', env('APP_URL', 'http://localhost'));
define('APP_DEBUG', env('APP_DEBUG', false));
define('APP_SECRET', env('APP_SECRET', 'CHANGE_ME_TO_A_RANDOM_32_CHAR_STRING'));
define('APP_VERSION', '2.0.0');

// Session settings
define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 3600));

// Cache settings
define('CACHE_TTL', (int) env('CACHE_TTL', 1800));
define('GEOCODE_CACHE_TTL', (int) env('GEOCODE_CACHE_TTL', 86400));

// Nominatim settings
define('NOMINATIM_USER_AGENT', env('NOMINATIM_USER_AGENT', 'WooMap/2.0'));

// Ensure data directories exist
foreach (['cache', 'geocode', 'stores'] as $dir) {
    $path = DATA_DIR . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}
