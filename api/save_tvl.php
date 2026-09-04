<?php
// api/save_tvl.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ============================================
// KONFIGURASI
// ============================================

define('RPC_URL', 'https://bsc-dataseed1.binance.org/');
define('STAKING_CONTRACT', '0x2eBA2586cf4593778192B557E0eE5674BAAa48CB');
define('TOKEN_CONTRACT', '0x4c9C431Fa7fD104c0E7230d20E1623E62019A1C5');
define('DATA_FILE', __DIR__ . '/../data/tvl_history.json');
define('MAX_POINTS', 5000);

// ============================================
// RPC CALL
// ============================================

function rpc_call($method, $params = []) {
    $ch = curl_init(RPC_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'jsonrpc' => '2.0',
        'method' => $method,
        'params' => $params,
        'id' => 1
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("RPC Error: " . $error);
    }
    if ($httpcode != 200) {
        throw new Exception("HTTP Error: " . $httpcode);
    }
    
    $result = json_decode($response, true);
    if (isset($result['error'])) {
        throw new Exception($result['error']['message']);
    }
    return $result['result'] ?? null;
}

// ============================================
// BACA TVL
// ============================================

function get_tvl() {
    $address = str_pad(str_replace('0x', '', STAKING_CONTRACT), 64, '0', STR_PAD_LEFT);
    $data = '0x70a08231' . $address;
    $result = rpc_call('eth_call', [[
        'to' => TOKEN_CONTRACT,
        'data' => $data
    ], 'latest']);
    return $result ? hexdec($result) / 1e18 : 0;
}

function get_latest_block() {
    $result = rpc_call('eth_blockNumber');
    return $result ? hexdec($result) : 0;
}

// ============================================
// AMBIL HARGA
// ============================================

function get_receh_price() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $apiUrl = $protocol . '://' . $host . '/api/get_receh_price.php';
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode !== 200 || !$response) {
        error_log("Price API call failed: HTTP $httpcode");
        return null;
    }
    
    $data = json_decode($response, true);
    return ($data['success'] ?? false) ? (float) $data['price'] : null;
}

// ============================================
// MAIN EXECUTION
// ============================================

try {
    $tvl = get_tvl();
    $price = get_receh_price();
    $tvl_usd = ($price !== null) ? round($tvl * $price, 2) : null;
    $block = get_latest_block();
    $now = time();
    
    $data = [];
    if (file_exists(DATA_FILE)) {
        $json = file_get_contents(DATA_FILE);
        $data = json_decode($json, true);
        if (!is_array($data)) $data = [];
    }
    
    $last = end($data);
    
    $saveCondition = (!$last) || 
                     ($now - $last['t']) > 300 || 
                     (abs($tvl - $last['v']) > 0.0001);
    
    if ($saveCondition) {
        $newEntry = [
            't' => $now,
            'v' => round($tvl, 8),
            'u' => $tvl_usd,
            'b' => $block,
            'p' => $price
        ];
        
        $data[] = $newEntry;
        
        if (count($data) > MAX_POINTS) {
            $data = array_slice($data, -MAX_POINTS);
        }
        
        file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
        
        echo json_encode([
            'success' => true,
            'message' => 'Data saved',
            'tvl' => $tvl,
            'tvl_usd' => $tvl_usd,
            'price' => $price,
            'block' => $block,
            'total_points' => count($data),
            'action' => 'saved',
            'last_update' => date('Y-m-d H:i:s', $now)
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Still fresh, no save needed',
            'tvl' => $tvl,
            'tvl_usd' => $tvl_usd,
            'price' => $price,
            'block' => $block,
            'total_points' => count($data),
            'action' => 'skipped',
            'last_update' => date('Y-m-d H:i:s', $last['t'] ?? $now)
        ]);
    }
    
} catch (Exception $e) {
    error_log("Save TVL error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
