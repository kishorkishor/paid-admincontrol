<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, log them instead

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$customer = getDropshipCustomer($conn);
$data = json_decode(file_get_contents('php://input'), true);

$order_id = $data['order_id'] ?? null;
$shop_domain = $data['shop_domain'] ?? null;

// Validate input
if (!$order_id || !$shop_domain) {
    echo json_encode([
        'success' => false, 
        'error' => 'Missing required fields',
        'details' => 'Both order_id and shop_domain are required'
    ]);
    exit;
}

// Debug log
error_log("Attempting to fulfill order: $order_id for shop: $shop_domain (customer: {$customer['email']})");

// Get hermate_shop_id from local database first (primary source)
$shop_id = getHermateShopId($conn, $customer['id'], $shop_domain);
error_log("Local database shop_id: " . ($shop_id ?: 'NOT FOUND'));

// If not found locally, try to get it from Hermate API
if (!$shop_id) {
    error_log("Shop ID not found locally, attempting to fetch from Hermate API...");
    
    try {
        $storesResponse = callHermateAPI('/shopify/stores/' . urlencode($customer['email']), 'GET');
        error_log("Hermate API stores response: " . json_encode($storesResponse));
        
        if ($storesResponse['success'] && isset($storesResponse['data']['stores'])) {
            // Find the shop_id for the given domain
            foreach ($storesResponse['data']['stores'] as $store) {
                if ($store['shop_domain'] === $shop_domain) {
                    $shop_id = $store['id'];
                    error_log("Found shop_id from API: $shop_id");
                    
                    // Save it to local database for future use
                    $storeDetails = getStoreDetails($conn, $customer['id'], $shop_domain);
                    if ($storeDetails) {
                        error_log("Saving shop_id to local database...");
                        saveConnectedStore(
                            $conn,
                            $customer['id'],
                            $shop_domain,
                            $storeDetails['store_name'],
                            $storeDetails['access_token'],
                            $shop_id
                        );
                    } else {
                        error_log("Warning: Could not get store details to save shop_id");
                    }
                    break;
                }
            }
            
            if (!$shop_id) {
                error_log("Shop domain '$shop_domain' not found in API response");
            }
        } else {
            error_log("API call unsuccessful or no stores in response");
        }
    } catch (Exception $e) {
        error_log("Exception while fetching from Hermate API: " . $e->getMessage());
    }
}

// If still no shop_id, we can't proceed
if (!$shop_id) {
    error_log("FAILED: No shop_id found for $shop_domain");
    
    echo json_encode([
        'success' => false,
        'error' => 'Store Configuration Missing',
        'details' => "Your store '$shop_domain' is not properly configured with Hermate. Please go to Connect Store page and reconnect your Shopify store."
    ]);
    exit;
}

error_log("Proceeding to fulfill order with shop_id: $shop_id");

// Call Hermate API to fulfill order
try {
    $fulfillRequest = [
        'shop_id' => $shop_id,
        'order_id' => $order_id
    ];
    
    error_log("Calling Hermate fulfill API with: " . json_encode($fulfillRequest));
    
    $response = callHermateAPI('/api/orders/fulfill', 'POST', $fulfillRequest);
    
    error_log("Hermate fulfill response: " . json_encode($response));
    
    if ($response['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Order fulfilled successfully',
            'data' => $response['data'] ?? []
        ]);
    } else {
        $errorMsg = $response['data']['error'] ?? $response['error'] ?? 'Failed to fulfill order';
        $detailsMsg = $response['data']['details'] ?? $response['details'] ?? 'Please check your Hermate API configuration';
        
        error_log("Fulfillment failed: $errorMsg - $detailsMsg");
        
        echo json_encode([
            'success' => false,
            'error' => $errorMsg,
            'details' => $detailsMsg
        ]);
    }
} catch (Exception $e) {
    error_log("Exception during fulfillment: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => 'Fulfillment API Error',
        'details' => $e->getMessage()
    ]);
}
?>