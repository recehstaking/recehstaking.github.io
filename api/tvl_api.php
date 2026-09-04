<?php
// api/tvl_api.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$data_file = __DIR__ . '/../data/tvl_history.json';

if (!file_exists($data_file)) {
    echo json_encode([
        'success' => false, 
        'error' => 'No data yet', 
        'data' => []
    ]);
    exit;
}

$json = file_get_contents($data_file);
$data = json_decode($json, true);

if (!$data || !is_array($data) || count($data) === 0) {
    echo json_encode([
        'success' => false, 
        'error' => 'No data', 
        'data' => []
    ]);
    exit;
}

$result = [];
foreach ($data as $p) {
    $result[] = [
        'timestamp' => date('Y-m-d H:i:s', $p['t']),
        'tvl' => $p['v'],
        'tvl_usd' => $p['u'] ?? null,
        'price' => $p['p'] ?? null,
        'block' => $p['b'] ?? 0
    ];
}

echo json_encode([
    'success' => true,
    'data' => $result,
    'total' => count($result),
    'all_data_points' => count($data),
    'from' => !empty($result) ? $result[0]['timestamp'] : null,
    'to' => !empty($result) ? end($result)['timestamp'] : null
]);
?>
