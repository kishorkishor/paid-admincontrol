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

$order_id = $data['order_id'] ?? null;
$shop_domain = $data['shop_domain'] ?? null;

if (!$order_id || !$shop_domain) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

error_log("=== TESTING HERMATE FULFILL ENDPOINT ===");
error_log("Order ID: $order_id");
error_log("Shop Domain: $shop_domain");
error_log("Customer Email: {$customer['email']}");

$results = [];

// TEST 1: Try with just order_id
error_log("TEST 1: Trying with just order_id");
try {
    $response1 = callHermateAPI('/api/orders/fulfill', 'POST', [
        'order_id' => $order_id
    ]);
    error_log("TEST 1 Response: " . json_encode($response1));
    $results['test1_just_order_id'] = [
        'params' => ['order_id' => $order_id],
        'success' => $response1['success'] ?? false,
        'response' => $response1
    ];
} catch (Exception $e) {
    error_log("TEST 1 Error: " . $e->getMessage());
    $results['test1_just_order_id'] = [
        'params' => ['order_id' => $order_id],
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// TEST 2: Try with shop_domain + order_id
error_log("TEST 2: Trying with shop_domain + order_id");
try {
    $response2 = callHermateAPI('/api/orders/fulfill', 'POST', [
        'shop_domain' => $shop_domain,
        'order_id' => $order_id
    ]);
    error_log("TEST 2 Response: " . json_encode($response2));
    $results['test2_shop_domain_and_order_id'] = [
        'params' => ['shop_domain' => $shop_domain, 'order_id' => $order_id],
        'success' => $response2['success'] ?? false,
        'response' => $response2
    ];
} catch (Exception $e) {
    error_log("TEST 2 Error: " . $e->getMessage());
    $results['test2_shop_domain_and_order_id'] = [
        'params' => ['shop_domain' => $shop_domain, 'order_id' => $order_id],
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// TEST 3: Try with customer_email + order_id
error_log("TEST 3: Trying with customer_email + order_id");
try {
    $response3 = callHermateAPI('/api/orders/fulfill', 'POST', [
        'customer_email' => $customer['email'],
        'order_id' => $order_id
    ]);
    error_log("TEST 3 Response: " . json_encode($response3));
    $results['test3_customer_email_and_order_id'] = [
        'params' => ['customer_email' => $customer['email'], 'order_id' => $order_id],
        'success' => $response3['success'] ?? false,
        'response' => $response3
    ];
} catch (Exception $e) {
    error_log("TEST 3 Error: " . $e->getMessage());
    $results['test3_customer_email_and_order_id'] = [
        'params' => ['customer_email' => $customer['email'], 'order_id' => $order_id],
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// TEST 4: Try with shop_domain + order_id + customer_email
error_log("TEST 4: Trying with all parameters");
try {
    $response4 = callHermateAPI('/api/orders/fulfill', 'POST', [
        'shop_domain' => $shop_domain,
        'order_id' => $order_id,
        'customer_email' => $customer['email']
    ]);
    error_log("TEST 4 Response: " . json_encode($response4));
    $results['test4_all_params'] = [
        'params' => [
            'shop_domain' => $shop_domain, 
            'order_id' => $order_id,
            'customer_email' => $customer['email']
        ],
        'success' => $response4['success'] ?? false,
        'response' => $response4
    ];
} catch (Exception $e) {
    error_log("TEST 4 Error: " . $e->getMessage());
    $results['test4_all_params'] = [
        'params' => [
            'shop_domain' => $shop_domain, 
            'order_id' => $order_id,
            'customer_email' => $customer['email']
        ],
        'success' => false,
        'error' => $e->getMessage()
    ];
}

error_log("=== TEST RESULTS ===");
error_log(json_encode($results, JSON_PRETTY_PRINT));

// Determine which test succeeded
$successfulTest = null;
foreach ($results as $testName => $result) {
    if ($result['success']) {
        $successfulTest = $testName;
        break;
    }
}

echo json_encode([
    'success' => $successfulTest !== null,
    'message' => $successfulTest 
        ? "Test '$successfulTest' succeeded! These parameters work."
        : "All tests failed. Check server logs for details.",
    'successful_test' => $successfulTest,
    'all_results' => $results,
    'recommendation' => $successfulTest 
        ? "Use these parameters: " . json_encode($results[$successfulTest]['params'])
        : "None of the tested parameter combinations worked. You may need shop_id or different parameters."
]);
?>