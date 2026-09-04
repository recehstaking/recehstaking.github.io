<?php
// api/tvl_latest.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$data_file = __DIR__ . '/../data/tvl_history.json';

if (!file_exists($data_file)) {
    echo json_encode(['success' => false, 'error' => 'No data yet']);
    exit;
}

$json = file_get_contents($data_file);
$data = json_decode($json, true);

if (!$data || !is_array($data) || count($data) === 0) {
    echo json_encode(['success' => false, 'error' => 'No data']);
    exit;
}

$last = end($data);
$first = reset($data);

$usdValues = [];
$tvlValues = [];
$priceValues = [];

foreach ($data as $p) {
    if (isset($p['u']) && $p['u'] !== null && $p['u'] > 0) {
        $usdValues[] = floatval($p['u']);
    }
    if (isset($p['v']) && $p['v'] > 0) {
        $tvlValues[] = floatval($p['v']);
    }
    if (isset($p['p']) && $p['p'] !== null && $p['p'] > 0) {
        $priceValues[] = floatval($p['p']);
    }
}

function get_latest_price() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $url = $protocol . '://' . $host . '/api/get_receh_price.php';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            return floatval($data['price']);
        }
    }
    return null;
}

$currentPrice = get_latest_price();

// TVL USD
$tvl_usd = null;
if ($currentPrice && isset($last['v'])) {
    $tvl_usd = round(floatval($last['v']) * $currentPrice, 2);
} elseif (isset($last['u'])) {
    $tvl_usd = floatval($last['u']);
}

echo json_encode([
    'success' => true,
    'current' => [
        'tvl' => isset($last['v']) ? floatval($last['v']) : 0,
        'tvl_usd' => $tvl_usd,
        'price' => $currentPrice ?? $last['p'] ?? null,
        'block' => $last['b'] ?? 0,
        'timestamp' => date('Y-m-d H:i:s', $last['t'])
    ],
    'summary' => [
        'total_points' => count($data),
        'first_data' => date('Y-m-d H:i:s', $first['t']),
        'last_data' => date('Y-m-d H:i:s', $last['t']),
        
        'min_tvl_usd' => !empty($usdValues) ? round(min($usdValues), 2) : null,
        'max_tvl_usd' => !empty($usdValues) ? round(max($usdValues), 2) : null,
        'avg_tvl_usd' => !empty($usdValues) ? round(array_sum($usdValues) / count($usdValues), 2) : null,
        
        'min_tvl' => !empty($tvlValues) ? round(min($tvlValues), 8) : 0,
        'max_tvl' => !empty($tvlValues) ? round(max($tvlValues), 8) : 0,
        'min_price' => !empty($priceValues) ? round(min($priceValues), 8) : null,
        'max_price' => !empty($priceValues) ? round(max($priceValues), 8) : null,
        'current_price' => $currentPrice ?? $last['p'] ?? null
    ]
]);
?>
