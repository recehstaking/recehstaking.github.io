<?php
// api/get_receh_price.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

define('CACHE_FILE', __DIR__ . '/../data/receh_price_cache.json');
define('CACHE_DURATION', 60); // Cache 60 detik

if (!file_exists(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0755, true);
}

function getPriceFromGeckoTerminal() {
    
    $pairAddress = '0x420998650af5d6632b11159fe361c014f872e524';
    $network = 'bsc';
    $url = 'https://api.geckoterminal.com/api/v2/search/pools?query=' . $pairAddress . '&network=' . $network . '&include=base_token&page=1';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en-US,en;q=0.9'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        error_log("GeckoTerminal API Error: HTTP $httpCode");
        return null;
    }
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['data']) || empty($data['data'])) {
        error_log("GeckoTerminal API: Invalid response structure");
        return null;
    }
    
    $pool = $data['data'][0];
    $attributes = $pool['attributes'] ?? [];
    
    $priceUsd = isset($attributes['base_token_price_usd']) ? (float) $attributes['base_token_price_usd'] : 0;
    $priceNative = isset($attributes['base_token_price_native_currency']) ? (float) $attributes['base_token_price_native_currency'] : 0;
    $liquidity = isset($attributes['reserve_in_usd']) ? (float) $attributes['reserve_in_usd'] : 0;
    $volume24h = isset($attributes['volume_usd']['h24']) ? (float) $attributes['volume_usd']['h24'] : 0;
    $marketCap = isset($attributes['market_cap_usd']) ? (float) $attributes['market_cap_usd'] : 0;
    $fdv = isset($attributes['fdv_usd']) ? (float) $attributes['fdv_usd'] : 0;
    
    if ($priceUsd <= 0) {
        error_log("GeckoTerminal API: Invalid price");
        return null;
    }
    
    return [
        'price' => $priceUsd,
        'price_native' => $priceNative,
        'liquidity' => $liquidity,
        'volume_24h' => $volume24h,
        'market_cap' => $marketCap,
        'fdv' => $fdv,
        'pool_name' => $attributes['name'] ?? 'RECEH / WBNB',
        'pool_created_at' => $attributes['pool_created_at'] ?? null
    ];
}

if (file_exists(CACHE_FILE)) {
    $cacheContent = file_get_contents(CACHE_FILE);
    $cacheData = json_decode($cacheContent, true);
    
    if ($cacheData && isset($cacheData['timestamp']) && (time() - $cacheData['timestamp']) < CACHE_DURATION) {
        if (isset($cacheData['price']) && $cacheData['price'] > 0) {
            echo json_encode([
                'success' => true,
                'price' => (float) $cacheData['price'],
                'price_native' => (float) ($cacheData['price_native'] ?? 0),
                'liquidity' => (float) ($cacheData['liquidity'] ?? 0),
                'volume_24h' => (float) ($cacheData['volume_24h'] ?? 0),
                'market_cap' => (float) ($cacheData['market_cap'] ?? 0),
                'fdv' => (float) ($cacheData['fdv'] ?? 0),
                'pool_name' => $cacheData['pool_name'] ?? 'RECEH / WBNB',
                'pool_created_at' => $cacheData['pool_created_at'] ?? null,
                'source' => 'cache',
                'cached' => true,
                'timestamp' => date('Y-m-d H:i:s', $cacheData['timestamp'])
            ]);
            exit;
        }
    }
}

$priceData = getPriceFromGeckoTerminal();

if (!$priceData || $priceData['price'] <= 0) {
    
    if (isset($cacheData) && $cacheData && isset($cacheData['price']) && $cacheData['price'] > 0) {
        echo json_encode([
            'success' => true,
            'price' => (float) $cacheData['price'],
            'price_native' => (float) ($cacheData['price_native'] ?? 0),
            'liquidity' => (float) ($cacheData['liquidity'] ?? 0),
            'volume_24h' => (float) ($cacheData['volume_24h'] ?? 0),
            'market_cap' => (float) ($cacheData['market_cap'] ?? 0),
            'fdv' => (float) ($cacheData['fdv'] ?? 0),
            'pool_name' => $cacheData['pool_name'] ?? 'RECEH / WBNB',
            'pool_created_at' => $cacheData['pool_created_at'] ?? null,
            'source' => 'cache_fallback',
            'cached' => true,
            'expired' => true,
            'timestamp' => date('Y-m-d H:i:s', $cacheData['timestamp'] ?? time())
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch price from GeckoTerminal',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

file_put_contents(CACHE_FILE, json_encode([
    'timestamp' => time(),
    'price' => $priceData['price'],
    'price_native' => $priceData['price_native'],
    'liquidity' => $priceData['liquidity'],
    'volume_24h' => $priceData['volume_24h'],
    'market_cap' => $priceData['market_cap'],
    'fdv' => $priceData['fdv'],
    'pool_name' => $priceData['pool_name'],
    'pool_created_at' => $priceData['pool_created_at']
]));

echo json_encode([
    'success' => true,
    'price' => $priceData['price'],
    'price_native' => $priceData['price_native'],
    'liquidity' => $priceData['liquidity'],
    'volume_24h' => $priceData['volume_24h'],
    'market_cap' => $priceData['market_cap'],
    'fdv' => $priceData['fdv'],
    'pool_name' => $priceData['pool_name'],
    'pool_created_at' => $priceData['pool_created_at'],
    'source' => 'geckoterminal',
    'cached' => false,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
