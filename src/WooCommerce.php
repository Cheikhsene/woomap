<?php
/**
 * WooCommerce REST API client.
 * Handles all communication with WooCommerce stores.
 */

if (!defined('WOOMAP')) exit;

class WooCommerce
{
    private string $siteUrl;
    private string $consumerKey;
    private string $consumerSecret;
    private Cache $cache;

    public function __construct(string $siteUrl, string $consumerKey, string $consumerSecret)
    {
        $this->siteUrl = rtrim($siteUrl, '/');
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->cache = new Cache('cache', CACHE_TTL);
    }

    /**
     * Create a WooCommerce client from the active store session.
     */
    public static function fromSession(): ?self
    {
        $store = Store::getActive();
        if (!$store) {
            return null;
        }
        return new self($store['url'], $store['consumer_key'], $store['consumer_secret']);
    }

    /**
     * Make an authenticated request to the WooCommerce API.
     */
    private function request(string $endpoint, array $params = []): array
    {
        $url = $this->siteUrl . '/wp-json/wc/v3/' . ltrim($endpoint, '/');

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($this->consumerKey . ':' . $this->consumerSecret),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("cURL Error: {$error}");
        }

        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        $data = json_decode($body, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $data['message'] ?? "HTTP {$httpCode}";
            throw new RuntimeException("WooCommerce API Error: {$message}");
        }

        // Extract pagination headers
        $totalPages = 1;
        $totalItems = 0;
        if (preg_match('/X-WP-TotalPages:\s*(\d+)/i', $headers, $m)) {
            $totalPages = (int) $m[1];
        }
        if (preg_match('/X-WP-Total:\s*(\d+)/i', $headers, $m)) {
            $totalItems = (int) $m[1];
        }

        return [
            'data' => $data ?? [],
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
        ];
    }

    /**
     * Test the API connection.
     * Returns store info on success, throws on failure.
     */
    public function testConnection(): array
    {
        $result = $this->request('');
        return $result['data'];
    }

    /**
     * Get all orders with pagination.
     */
    public function getOrders(array $filters = []): array
    {
        $cacheKey = 'orders_' . md5($this->siteUrl . serialize($filters));
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $allOrders = [];
        $page = 1;
        $perPage = 100;

        $params = array_merge([
            'per_page' => $perPage,
            'orderby' => 'date',
            'order' => 'desc',
        ], $filters);

        do {
            $params['page'] = $page;
            $result = $this->request('orders', $params);
            $orders = $result['data'];

            if (empty($orders)) {
                break;
            }

            $allOrders = array_merge($allOrders, $orders);
            $page++;
        } while ($page <= $result['total_pages']);

        $this->cache->set($cacheKey, $allOrders);
        return $allOrders;
    }

    /**
     * Get orders with date range filter.
     */
    public function getOrdersByDateRange(string $after = '', string $before = ''): array
    {
        $filters = [];
        if ($after) {
            $filters['after'] = $after . 'T00:00:00';
        }
        if ($before) {
            $filters['before'] = $before . 'T23:59:59';
        }
        return $this->getOrders($filters);
    }

    /**
     * Process orders into map-friendly data.
     */
    public function processOrdersForMap(array $orders): array
    {
        $processed = [];
        $stats = [
            'total' => count($orders),
            'completed' => 0,
            'processing' => 0,
            'cancelled' => 0,
            'other' => 0,
            'total_sales' => 0,
            'by_city' => [],
            'by_status' => [],
            'by_month' => [],
        ];

        foreach ($orders as $order) {
            $status = $order['status'] ?? 'unknown';
            $city = $order['billing']['city'] ?? 'Inconnu';
            $total = (float) ($order['total'] ?? 0);
            $date = $order['date_created'] ?? '';
            $month = $date ? date('Y-m', strtotime($date)) : 'unknown';

            // Build address
            $addressParts = array_filter([
                $order['billing']['address_1'] ?? '',
                $order['billing']['city'] ?? '',
                $order['billing']['state'] ?? '',
                $order['billing']['country'] ?? '',
            ]);
            $address = implode(', ', $addressParts);

            $processed[] = [
                'id' => $order['id'] ?? 0,
                'status' => $status,
                'total' => $total,
                'currency' => $order['currency'] ?? 'XOF',
                'date' => $date,
                'address' => $address,
                'city' => $city,
                'customer' => trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? '')),
                'email' => $order['billing']['email'] ?? '',
                'phone' => $order['billing']['phone'] ?? '',
                'items' => array_map(function ($item) {
                    return [
                        'name' => $item['name'] ?? '',
                        'quantity' => $item['quantity'] ?? 0,
                        'total' => (float) ($item['total'] ?? 0),
                    ];
                }, $order['line_items'] ?? []),
            ];

            // Stats
            switch ($status) {
                case 'completed':
                    $stats['completed']++;
                    $stats['total_sales'] += $total;
                    break;
                case 'processing':
                    $stats['processing']++;
                    $stats['total_sales'] += $total;
                    break;
                case 'cancelled':
                    $stats['cancelled']++;
                    break;
                default:
                    $stats['other']++;
            }

            // By city
            if (!isset($stats['by_city'][$city])) {
                $stats['by_city'][$city] = ['count' => 0, 'total' => 0];
            }
            $stats['by_city'][$city]['count']++;
            $stats['by_city'][$city]['total'] += $total;

            // By status
            if (!isset($stats['by_status'][$status])) {
                $stats['by_status'][$status] = ['count' => 0, 'total' => 0];
            }
            $stats['by_status'][$status]['count']++;
            $stats['by_status'][$status]['total'] += $total;

            // By month
            if (!isset($stats['by_month'][$month])) {
                $stats['by_month'][$month] = ['count' => 0, 'total' => 0];
            }
            $stats['by_month'][$month]['count']++;
            $stats['by_month'][$month]['total'] += $total;
        }

        // Sort cities by count
        arsort($stats['by_city']);
        // Sort months chronologically
        ksort($stats['by_month']);

        return [
            'orders' => $processed,
            'stats' => $stats,
        ];
    }

    /**
     * Get recent orders for notifications (last N minutes).
     */
    public function getRecentOrders(int $minutes = 30): array
    {
        $after = date('Y-m-d\TH:i:s', time() - ($minutes * 60));
        return $this->request('orders', [
            'after' => $after,
            'per_page' => 20,
            'orderby' => 'date',
            'order' => 'desc',
        ])['data'];
    }

    /**
     * Get products.
     */
    public function getProducts(array $params = []): array
    {
        $cacheKey = 'products_' . md5($this->siteUrl . serialize($params));
        return $this->cache->remember($cacheKey, function () use ($params) {
            return $this->request('orders', array_merge(['per_page' => 100], $params))['data'];
        });
    }

    /**
     * Clear the API cache.
     */
    public function clearCache(): void
    {
        $this->cache->clear();
    }
}
