<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$customer = getDropshipCustomer($conn);
$data = json_decode(file_get_contents('php://input'), true);

$shop_domain = $data['shop_domain'] ?? null;

if (!$shop_domain) {
    echo json_encode([
        'success' => false, 
        'error' => 'Missing shop_domain parameter'
    ]);
    exit;
}

error_log("Attempting to fix store configuration for: $shop_domain (customer: {$customer['email']})");

try {
    // Call Hermate API to get all stores for this customer
    $storesResponse = callHermateAPI('/shopify/stores/' . urlencode($customer['email']), 'GET');
    
    error_log("Hermate API response: " . json_encode($storesResponse));
    
    if (!$storesResponse['success']) {
        throw new Exception('Failed to fetch stores from Hermate API: ' . ($storesResponse['error'] ?? 'Unknown error'));
    }
    
    if (!isset($storesResponse['data']['stores']) || empty($storesResponse['data']['stores'])) {
        throw new Exception('No stores found in Hermate API response');
    }
    
    // Find the matching store
    $shop_id = null;
    $storeName = null;
    
    foreach ($storesResponse['data']['stores'] as $store) {
        error_log("Checking store: " . json_encode($store));
        
        if ($store['shop_domain'] === $shop_domain) {
            $shop_id = $store['id'];
            $storeName = $store['name'] ?? $shop_domain;
            error_log("Found matching store with ID: $shop_id");
            break;
        }
    }
    
    if (!$shop_id) {
        throw new Exception("Store '$shop_domain' not found in Hermate API. Available stores: " . 
            implode(', ', array_column($storesResponse['data']['stores'], 'shop_domain')));
    }
    
    // Get current store details from local database
    $storeDetails = getStoreDetails($conn, $customer['id'], $shop_domain);
    
    if (!$storeDetails) {
        throw new Exception("Store '$shop_domain' not found in local database");
    }
    
    // Update the store with the Hermate shop ID
    error_log("Updating store with shop_id: $shop_id");
    
    $stmt = $conn->prepare("
        UPDATE dropship_stores 
        SET hermate_shop_id = ?, updated_at = NOW() 
        WHERE customer_id = ? AND shop_domain = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('sis', $shop_id, $customer['id'], $shop_domain);
    
    if (!$stmt->execute()) {
        throw new Exception("Database update failed: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("No rows were updated. Store may not exist in database.");
    }
    
    error_log("Successfully updated store configuration. Affected rows: " . $stmt->affected_rows);
    
    echo json_encode([
        'success' => true,
        'message' => 'Store configuration fixed successfully',
        'shop_id' => $shop_id,
        'store_name' => $storeName,
        'affected_rows' => $stmt->affected_rows
    ]);
    
} catch (Exception $e) {
    error_log("Error fixing store configuration: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>