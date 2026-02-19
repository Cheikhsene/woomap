<?php
/**
 * Geocoder using Nominatim (OpenStreetMap).
 * Free, no API key required.
 * Respects Nominatim usage policy (max 1 req/sec).
 */

if (!defined('WOOMAP')) exit;

class Geocoder
{
    private Cache $cache;
    private static float $lastRequestTime = 0;

    private const API_URL = 'https://nominatim.openstreetmap.org/search';

    public function __construct()
    {
        $this->cache = new Cache('geocode', GEOCODE_CACHE_TTL);
    }

    /**
     * Geocode a single address.
     * Returns [lat, lng] or null if not found.
     */
    public function geocode(string $address): ?array
    {
        $address = trim($address);
        if (empty($address)) {
            return null;
        }

        // Check cache first
        $cacheKey = 'geo_' . md5(strtolower($address));
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Respect rate limit (1 request per second)
        $this->rateLimit();

        $params = [
            'q' => $address,
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 0,
        ];

        $url = self::API_URL . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . NOMINATIM_USER_AGENT,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data) || !isset($data[0]['lat'], $data[0]['lon'])) {
            // Cache negative result to avoid repeated lookups
            $this->cache->set($cacheKey, false, 3600);
            return null;
        }

        $result = [
            'lat' => (float) $data[0]['lat'],
            'lng' => (float) $data[0]['lon'],
            'display_name' => $data[0]['display_name'] ?? $address,
        ];

        $this->cache->set($cacheKey, $result);
        return $result;
    }

    /**
     * Geocode multiple addresses.
     * Returns an associative array: address => [lat, lng] or null.
     */
    public function geocodeBatch(array $addresses): array
    {
        $results = [];
        foreach ($addresses as $address) {
            $results[$address] = $this->geocode($address);
        }
        return $results;
    }

    /**
     * Enforce Nominatim rate limit (max 1 request per second).
     */
    private function rateLimit(): void
    {
        $now = microtime(true);
        $elapsed = $now - self::$lastRequestTime;
        if ($elapsed < 1.0) {
            usleep((int) ((1.0 - $elapsed) * 1000000));
        }
        self::$lastRequestTime = microtime(true);
    }

    /**
     * Get known coordinates for major Senegalese cities.
     * Fallback when geocoding fails.
     */
    public static function getKnownCities(): array
    {
        return [
            'Dakar' => ['lat' => 14.7167, 'lng' => -17.4677],
            'Thiès' => ['lat' => 14.7886, 'lng' => -16.9260],
            'Saint-Louis' => ['lat' => 16.0326, 'lng' => -16.4818],
            'Ziguinchor' => ['lat' => 12.5681, 'lng' => -16.2719],
            'Kaolack' => ['lat' => 14.1389, 'lng' => -16.0758],
            'Mbour' => ['lat' => 14.4167, 'lng' => -16.9667],
            'Rufisque' => ['lat' => 14.7167, 'lng' => -17.2667],
            'Tambacounda' => ['lat' => 13.7709, 'lng' => -13.6673],
            'Kolda' => ['lat' => 12.8833, 'lng' => -14.9500],
            'Matam' => ['lat' => 15.6559, 'lng' => -13.2554],
            'Louga' => ['lat' => 15.6167, 'lng' => -16.2167],
            'Diourbel' => ['lat' => 14.6500, 'lng' => -16.2333],
            'Fatick' => ['lat' => 14.3333, 'lng' => -16.4000],
            'Kaffrine' => ['lat' => 14.1058, 'lng' => -15.5503],
            'Kédougou' => ['lat' => 12.5564, 'lng' => -12.1747],
            'Sédhiou' => ['lat' => 12.7081, 'lng' => -15.5569],
            'Touba' => ['lat' => 14.8500, 'lng' => -15.8833],
            'Saly' => ['lat' => 14.4500, 'lng' => -17.0167],
            'Pikine' => ['lat' => 14.7500, 'lng' => -17.3833],
            'Guédiawaye' => ['lat' => 14.7833, 'lng' => -17.3833],
        ];
    }

    /**
     * Try to geocode using known cities first, then Nominatim.
     */
    public function smartGeocode(string $address, string $city = ''): ?array
    {
        // Try known cities first (instant, no API call)
        if ($city) {
            $known = self::getKnownCities();
            foreach ($known as $knownCity => $coords) {
                if (stripos($city, $knownCity) !== false || stripos($knownCity, $city) !== false) {
                    return $coords;
                }
            }
        }

        // Fall back to Nominatim
        return $this->geocode($address);
    }
}
