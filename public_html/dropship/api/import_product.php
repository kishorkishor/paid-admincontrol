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

$product_id = $data['product_id'] ?? null;
$shop_domain = $data['shop_domain'] ?? null;
$markup = floatval($data['markup'] ?? 50);

if (!$product_id || !$shop_domain) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Get product from database
$stmt = $conn->prepare("SELECT * FROM dropship_products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo json_encode(['success' => false, 'error' => 'Product not found']);
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
        'error' => 'Store configuration incomplete',
        'details' => 'Please reconnect your store to enable product imports.'
    ]);
    exit;
}

// Calculate import price with markup
$import_price = $product['price'] * (1 + ($markup / 100));

// Prepare product data in the format Hermate API expects
$importData = [
    'shop_id' => $shop_id,
    'product_id' => $product_id,
    'product_title' => $product['title'],
    'product_description' => $product['description'],
    'product_price' => $import_price,
    'product_compare_price' => $product['compare_price'] ? floatval($product['compare_price']) : null,
    'product_sku' => $product['sku'],
    'product_image' => $product['image_url'],
    'product_category' => $product['category'],
    'price_markup' => $markup
];

// Call Hermate API to import product
try {
    $response = callHermateAPI('/products/import', 'POST', $importData);
    
    if ($response['success']) {
        // Log the import in local database
        $shopify_product_id = $response['data']['shopify_product']['id'] ?? null;
        
        $stmt = $conn->prepare("
            INSERT INTO dropship_imports 
            (customer_id, product_id, shop_domain, shopify_product_id, import_price, imported_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iissd", $customer['id'], $product_id, $shop_domain, $shopify_product_id, $import_price);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Product imported successfully',
            'product' => $response['data']['shopify_product'] ?? null
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $response['data']['error'] ?? 'Failed to import product',
            'details' => $response['data']['details'] ?? null
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Import failed',
        'details' => $e->getMessage()
    ]);
}
?>