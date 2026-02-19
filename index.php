<?php
/**
 * WooMap - Entry Point & Router
 * Routes all requests to appropriate handlers.
 *
 * @version 2.0.0
 * @license MIT
 */

define('WOOMAP', true);

// Load configuration
require_once __DIR__ . '/config.php';

// Autoload source files
foreach (glob(SRC_DIR . '/*.php') as $file) {
    require_once $file;
}

// Start session
Session::start();

// ==================
// Router
// ==================

$route = $_GET['route'] ?? '';
$route = '/' . trim($route, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Public routes (no auth required)
$publicRoutes = ['/login', '/oauth/start', '/oauth/callback'];

// Check authentication for protected routes
if (!in_array($route, $publicRoutes) && !Auth::check()) {
    if (isAjax()) {
        jsonResponse(['success' => false, 'error' => 'Non authentifié'], 401);
    }
    redirect('/login');
}

// Route handling
switch ($route) {
    // ==================
    // Auth Routes
    // ==================

    case '/login':
        if ($method === 'POST') {
            handleLogin();
        } else {
            if (Auth::check()) {
                redirect('/dashboard');
            }
            renderTemplate('login');
        }
        break;

    case '/oauth/start':
        handleOAuthStart();
        break;

    case '/oauth/callback':
        handleOAuthCallback();
        break;

    case '/logout':
        Auth::logout();
        redirect('/login');
        break;

    // ==================
    // Dashboard
    // ==================

    case '/':
    case '/dashboard':
        renderPage('dashboard', 'Carte des commandes', 'dashboard');
        break;

    // ==================
    // Analytics
    // ==================

    case '/analytics':
        renderPage('analytics', 'Analytiques', 'analytics');
        break;

    // ==================
    // Store Management
    // ==================

    case '/stores':
        renderPage('stores', 'Boutiques', 'stores');
        break;

    case '/stores/add':
        handleStoreAdd();
        break;

    case '/stores/switch':
        handleStoreSwitch();
        break;

    case '/stores/delete':
        handleStoreDelete();
        break;

    // ==================
    // API Endpoints
    // ==================

    case '/api/orders':
        handleApiOrders();
        break;

    case '/api/geocode':
        handleApiGeocode();
        break;

    case '/api/notifications':
        handleApiNotifications();
        break;

    case '/api/clear-cache':
        handleApiClearCache();
        break;

    // ==================
    // Export
    // ==================

    case '/export/csv':
        handleExportCSV();
        break;

    case '/export/print':
        handleExportPrint();
        break;

    // ==================
    // 404
    // ==================

    default:
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>404</title><link rel="stylesheet" href="/public/css/app.css"></head>';
        echo '<body><div class="login-page"><div style="text-align:center;color:white;">';
        echo '<h1 style="font-size:72px;margin-bottom:16px;">404</h1>';
        echo '<p style="font-size:18px;opacity:0.8;">Page non trouvée</p>';
        echo '<a href="/dashboard" style="color:#60a5fa;margin-top:16px;display:inline-block;">Retour au dashboard</a>';
        echo '</div></div></body></html>';
        break;
}

// ==================
// Route Handlers
// ==================

function handleLogin(): void
{
    if (!CSRF::verify()) {
        Session::flash('error', 'Token de sécurité invalide. Veuillez réessayer.');
        redirect('/login');
    }

    $siteUrl = trim($_POST['site_url'] ?? '');
    $consumerKey = trim($_POST['consumer_key'] ?? '');
    $consumerSecret = trim($_POST['consumer_secret'] ?? '');

    try {
        Auth::loginWithAppPassword($siteUrl, $consumerKey, $consumerSecret);
        CSRF::regenerate();
        Session::flash('success', 'Boutique connectée avec succès !');
        redirect('/dashboard');
    } catch (RuntimeException $e) {
        Session::flash('error', $e->getMessage());
        redirect('/login');
    }
}

function handleOAuthStart(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/login');
    }

    if (!CSRF::verify()) {
        Session::flash('error', 'Token de sécurité invalide.');
        redirect('/login');
    }

    $siteUrl = trim($_POST['site_url'] ?? '');
    $callbackUrl = APP_URL . '/oauth/callback';

    try {
        $authUrl = Auth::getOAuthUrl($siteUrl, $callbackUrl);
        redirect($authUrl);
    } catch (RuntimeException $e) {
        Session::flash('error', $e->getMessage());
        redirect('/login');
    }
}

function handleOAuthCallback(): void
{
    try {
        $params = array_merge($_GET, $_POST);
        Auth::handleOAuthCallback($params);
        CSRF::regenerate();
        Session::flash('success', 'Boutique connectée via OAuth !');
        redirect('/dashboard');
    } catch (RuntimeException $e) {
        Session::flash('error', $e->getMessage());
        redirect('/login');
    }
}

function handleStoreAdd(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify()) {
        redirect('/stores');
    }

    $siteUrl = trim($_POST['site_url'] ?? '');
    $consumerKey = trim($_POST['consumer_key'] ?? '');
    $consumerSecret = trim($_POST['consumer_secret'] ?? '');

    try {
        Auth::loginWithAppPassword($siteUrl, $consumerKey, $consumerSecret);
        Session::flash('success', 'Boutique ajoutée avec succès !');
    } catch (RuntimeException $e) {
        Session::flash('error', $e->getMessage());
    }

    redirect('/stores');
}

function handleStoreSwitch(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CSRF::verify()) {
            redirect('/stores');
        }
        $storeId = $_POST['store_id'] ?? '';
    } else {
        $storeId = $_GET['id'] ?? '';
    }

    if ($storeId && Store::get($storeId)) {
        Store::setActive($storeId);
        Session::flash('success', 'Boutique activée.');
    } else {
        Session::flash('error', 'Boutique introuvable.');
    }

    redirect('/stores');
}

function handleStoreDelete(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify()) {
        redirect('/stores');
    }

    $storeId = $_POST['store_id'] ?? '';
    if ($storeId) {
        Store::delete($storeId);
        Session::flash('success', 'Boutique supprimée.');

        // If no stores left, logout
        if (Store::count() === 0) {
            Auth::logout();
            redirect('/login');
            return;
        }
    }

    redirect('/stores');
}

// ==================
// API Handlers
// ==================

function handleApiOrders(): void
{
    try {
        $woo = WooCommerce::fromSession();
        if (!$woo) {
            jsonResponse(['success' => false, 'error' => 'Boutique non connectée'], 401);
        }

        $filters = [];
        if (!empty($_GET['after'])) {
            $filters['after'] = $_GET['after'] . 'T00:00:00';
        }
        if (!empty($_GET['before'])) {
            $filters['before'] = $_GET['before'] . 'T23:59:59';
        }
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        $orders = $woo->getOrders($filters);
        $data = $woo->processOrdersForMap($orders);

        jsonResponse(['success' => true, 'data' => $data]);
    } catch (RuntimeException $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

function handleApiGeocode(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'POST required'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $addresses = $input['addresses'] ?? [];
    $cities = $input['cities'] ?? [];

    if (empty($addresses)) {
        jsonResponse(['success' => true, 'coordinates' => []]);
    }

    $geocoder = new Geocoder();
    $coordinates = [];

    foreach ($addresses as $address) {
        $city = $cities[$address] ?? '';
        $result = $geocoder->smartGeocode($address, $city);
        $coordinates[$address] = $result;
    }

    jsonResponse(['success' => true, 'coordinates' => $coordinates]);
}

function handleApiNotifications(): void
{
    try {
        $woo = WooCommerce::fromSession();
        if (!$woo) {
            jsonResponse(['success' => false, 'error' => 'Non connecté'], 401);
        }

        $recent = $woo->getRecentOrders(60);

        $orders = array_map(function ($order) {
            return [
                'id' => $order['id'] ?? 0,
                'customer' => trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? '')),
                'total' => (float) ($order['total'] ?? 0),
                'status' => $order['status'] ?? '',
                'date' => $order['date_created'] ?? '',
            ];
        }, $recent);

        jsonResponse(['success' => true, 'orders' => $orders]);
    } catch (RuntimeException $e) {
        jsonResponse(['success' => true, 'orders' => []]);
    }
}

function handleApiClearCache(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false], 405);
    }

    $cache = new Cache('cache');
    $cache->clear();
    jsonResponse(['success' => true]);
}

// ==================
// Export Handlers
// ==================

function handleExportCSV(): void
{
    try {
        $woo = WooCommerce::fromSession();
        if (!$woo) {
            redirect('/login');
        }

        $orders = $woo->getOrders();
        $data = $woo->processOrdersForMap($orders);

        $storeName = '';
        $store = Store::getActive();
        if ($store) {
            $storeName = preg_replace('/[^a-zA-Z0-9_-]/', '', $store['name']);
        }

        $filename = 'woomap-' . ($storeName ?: 'export') . '-' . date('Y-m-d') . '.csv';
        Export::toCSV($data['orders'], $filename);
        exit;
    } catch (RuntimeException $e) {
        Session::flash('error', 'Erreur d\'export : ' . $e->getMessage());
        redirect('/dashboard');
    }
}

function handleExportPrint(): void
{
    try {
        $woo = WooCommerce::fromSession();
        if (!$woo) {
            redirect('/login');
        }

        $orders = $woo->getOrders();
        $data = $woo->processOrdersForMap($orders);

        echo Export::toPrintHTML($data['orders'], $data['stats']);
        exit;
    } catch (RuntimeException $e) {
        Session::flash('error', 'Erreur d\'export : ' . $e->getMessage());
        redirect('/dashboard');
    }
}

// ==================
// Helper Functions
// ==================

/**
 * Render a template inside the layout.
 */
function renderPage(string $template, string $title, string $currentPage): void
{
    $pageTitle = $title;
    ob_start();
    require TEMPLATE_DIR . '/' . $template . '.php';
    $content = ob_get_clean();
    require TEMPLATE_DIR . '/layout.php';
}

/**
 * Render a standalone template (no layout).
 */
function renderTemplate(string $template): void
{
    require TEMPLATE_DIR . '/' . $template . '.php';
}

/**
 * Send a JSON response.
 */
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Redirect to a URL.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Check if the request is AJAX.
 */
function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
