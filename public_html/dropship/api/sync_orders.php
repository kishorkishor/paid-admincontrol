<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$customer = getDropshipCustomer($conn);
$shop_domain = $_GET['store'] ?? null;

if (!$shop_domain) {
    echo json_encode(['success' => false, 'error' => 'Store domain required']);
    exit;
}

// Get hermate_shop_id from local database first (primary source)
$shop_id = getHermateShopId($conn, $customer['id'], $shop_domain);

// If not found locally, try to get it from Hermate API
if (!$shop_id) {
    try {
        $storesResponse = callHermateAPI('/shopify/stores/' . urlencode($customer['email']), 'GET');
        
        if ($storesResponse['success'] && isset($storesResponse['data']['stores'])) {
            // Find the shop_id for the given domain
            foreach ($storesResponse['data']['stores'] as $store) {
                if ($store['shop_domain'] === $shop_domain) {
                    $shop_id = $store['id'];
                    
                    // Save it to local database for future use
                    $storeDetails = getStoreDetails($conn, $customer['id'], $shop_domain);
                    if ($storeDetails) {
                        saveConnectedStore(
                            $conn,
                            $customer['id'],
                            $shop_domain,
                            $storeDetails['store_name'],
                            $storeDetails['access_token'],
                            $shop_id
                        );
                    }
                    break;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Failed to get shop_id from API: " . $e->getMessage());
    }
}

// If still no shop_id, we can't proceed
if (!$shop_id) {
    echo json_encode([
        'success' => false,
        'error' => 'Store not configured properly',
        'details' => 'The shop_id is missing. Please reconnect your store in the Connect Store page.',
        'action_required' => 'reconnect_store'
    ]);
    exit;
}

// Call Hermate API to sync orders
try {
    $response = callHermateAPI("/shopify/stores/$shop_id/sync-orders", 'POST');
    
    if ($response['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Orders synced successfully',
            'data' => $response['data']
        ]);
    } else {
        // Better error message from API
        $apiError = $response['data']['error'] ?? 'Unknown error';
        $apiDetails = $response['data']['details'] ?? '';
        
        echo json_encode([
            'success' => false,
            'error' => 'Hermate API error: ' . $apiError,
            'details' => $apiDetails,
            'api_status' => $response['status'] ?? 'unknown',
            'note' => 'This may be a temporary Hermate API issue. Try again in a moment.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to communicate with Hermate API',
        'details' => $e->getMessage(),
        'note' => 'The Hermate API may be temporarily unavailable.'
    ]);
}
?>