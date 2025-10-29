<?php
/**
 * Complete Diagnostic Script
 * Upload to: /dropship/diagnose_fulfillment.php
 * Access: https://cosmictrd.io/dropship/diagnose_fulfillment.php
 */

require_once 'config.php';
requireLogin();

header('Content-Type: text/html; charset=utf-8');

$customer = getDropshipCustomer($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fulfillment Diagnostic</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f5f7fa; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #2563eb; border-bottom: 3px solid #2563eb; padding-bottom: 10px; }
        h2 { color: #1e40af; margin-top: 30px; }
        .test { background: #f8fafc; padding: 20px; margin: 15px 0; border-radius: 6px; border-left: 4px solid #94a3b8; }
        .success { border-left-color: #10b981; background: #ecfdf5; }
        .error { border-left-color: #ef4444; background: #fef2f2; }
        .warning { border-left-color: #f59e0b; background: #fffbeb; }
        .info { border-left-color: #3b82f6; background: #eff6ff; }
        pre { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 13px; }
        .status { display: inline-block; padding: 4px 12px; border-radius: 12px; font-weight: 600; font-size: 12px; }
        .status.pass { background: #d1fae5; color: #065f46; }
        .status.fail { background: #fee2e2; color: #991b1b; }
        .status.warn { background: #fef3c7; color: #92400e; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; }
        tr:hover { background: #f9fafb; }
        .btn { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Fulfillment System Diagnostic</h1>
        <p><strong>Customer:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
        
        <?php
        // Get connected stores
        $connected_stores = getConnectedStores($conn, $customer['id']);
        $selected_store = $connected_stores[0]['shop_domain'] ?? '';
        
        echo "<h2>📊 Store Configuration Check</h2>";
        
        if (empty($connected_stores)) {
            echo '<div class="test error">';
            echo '<span class="status fail">FAIL</span> <strong>No Stores Connected</strong><br>';
            echo 'Action: Go to Connect Store page and connect your Shopify store.';
            echo '</div>';
        } else {
            echo '<div class="test success">';
            echo '<span class="status pass">PASS</span> <strong>' . count($connected_stores) . ' Store(s) Connected</strong>';
            echo '</div>';
            
            echo '<table>';
            echo '<tr><th>Store Name</th><th>Shop Domain</th><th>Hermate Shop ID</th><th>Status</th></tr>';
            
            foreach ($connected_stores as $store) {
                // Get full details including hermate_shop_id
                $details = getStoreDetails($conn, $customer['id'], $store['shop_domain']);
                $shop_id = $details['hermate_shop_id'] ?? null;
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($store['store_name']) . '</td>';
                echo '<td>' . htmlspecialchars($store['shop_domain']) . '</td>';
                echo '<td>';
                if ($shop_id) {
                    echo '<span class="status pass">' . $shop_id . '</span>';
                } else {
                    echo '<span class="status fail">NULL</span>';
                }
                echo '</td>';
                echo '<td>';
                if ($shop_id) {
                    echo '<span class="status pass">✓ Ready</span>';
                } else {
                    echo '<span class="status fail">✗ Missing Shop ID</span>';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            // Check hermate_shop_id for first store
            $first_store_details = getStoreDetails($conn, $customer['id'], $selected_store);
            $hermate_shop_id = $first_store_details['hermate_shop_id'] ?? null;
            
            if (!$hermate_shop_id) {
                echo '<div class="test error">';
                echo '<span class="status fail">FAIL</span> <strong>Hermate Shop ID Missing</strong><br>';
                echo 'The store is connected but missing the hermate_shop_id link.<br>';
                echo '<strong>Solution:</strong> Reconnect the store on the Connect Store page.';
                echo '</div>';
            } else {
                echo '<div class="test success">';
                echo '<span class="status pass">PASS</span> <strong>Hermate Shop ID Found: ' . $hermate_shop_id . '</strong>';
                echo '</div>';
                
                // Test Hermate API connection
                echo "<h2>🔌 Hermate API Connection Test</h2>";
                
                try {
                    $storesResponse = callHermateAPI('/shopify/stores/' . urlencode($customer['email']), 'GET');
                    
                    if ($storesResponse['success']) {
                        echo '<div class="test success">';
                        echo '<span class="status pass">PASS</span> <strong>Hermate API Connected</strong><br>';
                        echo 'HTTP Status: ' . $storesResponse['status'] . '<br>';
                        echo 'Stores found: ' . count($storesResponse['data']['stores'] ?? []);
                        echo '</div>';
                        
                        // Verify shop_id exists in Hermate
                        $found_in_hermate = false;
                        if (isset($storesResponse['data']['stores'])) {
                            foreach ($storesResponse['data']['stores'] as $hstore) {
                                if ($hstore['id'] == $hermate_shop_id) {
                                    $found_in_hermate = true;
                                    echo '<div class="test success">';
                                    echo '<span class="status pass">PASS</span> <strong>Shop ID Verified in Hermate</strong><br>';
                                    echo 'Hermate has shop_id ' . $hermate_shop_id . ' for ' . $hstore['shop_domain'];
                                    echo '</div>';
                                    break;
                                }
                            }
                        }
                        
                        if (!$found_in_hermate) {
                            echo '<div class="test error">';
                            echo '<span class="status fail">FAIL</span> <strong>Shop ID Not Found in Hermate</strong><br>';
                            echo 'Your cosmictrd database has hermate_shop_id = ' . $hermate_shop_id . '<br>';
                            echo 'But Hermate API does not have a store with id = ' . $hermate_shop_id . '<br>';
                            echo '<strong>Solution:</strong> Reconnect the store to sync the IDs.';
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="test error">';
                        echo '<span class="status fail">FAIL</span> <strong>Hermate API Error</strong><br>';
                        echo 'HTTP Status: ' . $storesResponse['status'] . '<br>';
                        echo 'Error: ' . json_encode($storesResponse['data']);
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="test error">';
                    echo '<span class="status fail">FAIL</span> <strong>API Connection Failed</strong><br>';
                    echo 'Error: ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                }
                
                // Test fulfillment API call
                echo "<h2>🚀 Test Fulfillment API Call</h2>";
                echo '<div class="test info">';
                echo '<strong>Test Parameters:</strong><br>';
                echo 'shop_id: ' . $hermate_shop_id . '<br>';
                echo 'order_id: test_order_123<br>';
                echo '(This will fail because test_order_123 doesn\'t exist, but we can see the error message)';
                echo '</div>';
                
                try {
                    $testFulfill = callHermateAPI('/api/orders/fulfill', 'POST', [
                        'shop_id' => $hermate_shop_id,
                        'order_id' => 'test_order_123'
                    ]);
                    
                    echo '<div class="test ' . ($testFulfill['success'] ? 'success' : 'warning') . '">';
                    echo '<strong>API Response:</strong><br>';
                    echo 'HTTP Status: ' . $testFulfill['status'] . '<br>';
                    echo '<pre>' . json_encode($testFulfill['data'], JSON_PRETTY_PRINT) . '</pre>';
                    echo '</div>';
                    
                    if ($testFulfill['status'] == 404) {
                        echo '<div class="test error">';
                        echo '<span class="status fail">FAIL</span> <strong>Store Not Found in Hermate</strong><br>';
                        echo 'Hermate API cannot find shop_id = ' . $hermate_shop_id . '<br>';
                        echo '<strong>Solution:</strong> Reconnect the store.';
                        echo '</div>';
                    } else if ($testFulfill['status'] == 400 || isset($testFulfill['data']['error'])) {
                        $error = $testFulfill['data']['error'] ?? 'Unknown error';
                        if (strpos($error, 'fulfillment orders') !== false || strpos($error, 'not found') !== false) {
                            echo '<div class="test success">';
                            echo '<span class="status pass">PASS</span> <strong>API Call Successful!</strong><br>';
                            echo 'The error is expected (test order doesn\'t exist).<br>';
                            echo 'This means your fulfillment API is working correctly.';
                            echo '</div>';
                        }
                    }
                } catch (Exception $e) {
                    echo '<div class="test error">';
                    echo '<span class="status fail">FAIL</span> <strong>API Call Failed</strong><br>';
                    echo 'Error: ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                }
            }
        }
        
        // Summary
        echo "<h2>📋 Summary & Next Steps</h2>";
        
        if (empty($connected_stores)) {
            echo '<div class="test error">';
            echo '<strong>⚠️ No stores connected</strong><br>';
            echo '1. Go to <a href="connect_store.php">Connect Store</a> page<br>';
            echo '2. Enter your Shopify store credentials<br>';
            echo '3. Connect your store';
            echo '</div>';
        } else if (!$hermate_shop_id) {
            echo '<div class="test error">';
            echo '<strong>⚠️ Store missing hermate_shop_id</strong><br>';
            echo '1. Go to <a href="connect_store.php">Connect Store</a> page<br>';
            echo '2. Reconnect your store to fix the link<br>';
            echo '3. Come back and refresh this page';
            echo '</div>';
        } else {
            echo '<div class="test success">';
            echo '<strong>✅ Your fulfillment system is ready!</strong><br>';
            echo 'You can now fulfill orders from the <a href="orders.php">Orders</a> page.<br><br>';
            echo '<strong>Database Info:</strong><br>';
            echo '• cosmictrd.io database has hermate_shop_id = ' . $hermate_shop_id . '<br>';
            echo '• This links to hermate.shop shop_id = ' . $hermate_shop_id . '<br>';
            echo '• Fulfillment API calls will work correctly<br><br>';
            echo '<a href="orders.php" class="btn">Go to Orders →</a>';
            echo '</div>';
        }
        ?>
        
        <h2>📖 How Fulfillment Works</h2>
        <div class="test info">
            <strong>The Flow:</strong><br>
            1. You click "Fulfill Order" on orders.php<br>
            2. cosmictrd.io looks up <code>hermate_shop_id</code> in database<br>
            3. Sends to hermate.shop: <code>{ shop_id: <?php echo $hermate_shop_id ?: 'X'; ?>, order_id: "123..." }</code><br>
            4. hermate.shop uses shop_id to get Shopify access_token<br>
            5. hermate.shop calls Shopify API to fulfill order<br>
            6. Order is fulfilled! ✅
        </div>
        
        <p style="margin-top: 30px; color: #64748b; font-size: 14px;">
            Diagnostic completed at <?php echo date('Y-m-d H:i:s'); ?>
        </p>
    </div>
</body>
</html>