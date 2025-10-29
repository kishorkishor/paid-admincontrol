<?php
require_once 'config.php';
requireLogin();

$customer = getDropshipCustomer($conn);

// Get customer's connected stores from local database
$connected_stores = getConnectedStores($conn, $customer['id']);

$selected_store = isset($_GET['store']) ? $_GET['store'] : ($connected_stores[0]['shop_domain'] ?? '');
$orders = [];
$missing_customer_data = false;

if ($selected_store) {
    // Get store details including hermate_shop_id from local database
    $store_details = getStoreDetails($conn, $customer['id'], $selected_store);
    
    if ($store_details && $store_details['hermate_shop_id']) {
        $shop_id = $store_details['hermate_shop_id'];
        
        // Fetch orders from Hermate API
        try {
            $response = callHermateAPI("/shopify/stores/$shop_id/sync-orders", 'POST');
            if ($response['success']) {
                // ✅ FIX: Orders are inside 'data' key from callHermateAPI response
                $orders = $response['data']['orders'] ?? [];
                
                // Check if customer data is missing (Shopify plan limitation)
                if (!empty($orders)) {
                    $orders_without_customer = 0;
                    foreach ($orders as $order) {
                        // Check if customer info is missing or empty
                        $has_customer_name = !empty($order['customer_first_name']) || !empty($order['customer_last_name']);
                        $has_customer_email = !empty($order['customer_email']);
                        
                        if (!$has_customer_name && !$has_customer_email) {
                            $orders_without_customer++;
                        }
                    }
                    
                    // If more than 50% of orders are missing customer data, likely a plan limitation
                    if ($orders_without_customer > 0 && ($orders_without_customer / count($orders)) > 0.5) {
                        $missing_customer_data = true;
                    }
                }
            } else {
                error_log("Failed to fetch orders: " . json_encode($response));
            }
        } catch (Exception $e) {
            error_log("Orders API error: " . $e->getMessage());
        }
    } else {
        // Store not properly configured - needs reconnection
        $error_message = "Store configuration incomplete. Please reconnect your store.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - CosmicTRD Dropshipping</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 24px; }
        nav {
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        nav ul {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            list-style: none;
            display: flex;
            gap: 30px;
        }
        nav a {
            display: block;
            padding: 15px 0;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        nav a:hover, nav a.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .toolbar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .toolbar select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
        }
        .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #5568d3;
        }
        .orders-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: #f9f9f9;
        }
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        .badge-fulfilled {
            background: #d4edda;
            color: #155724;
        }
        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        .fulfill-btn {
            padding: 8px 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .fulfill-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state h2 {
            color: #666;
            margin-bottom: 10px;
        }
        .empty-state p {
            color: #999;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-info {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #90caf9;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }
        .alert-warning strong {
            color: #856404;
        }
        .alert-warning a {
            color: #856404;
            text-decoration: underline;
            font-weight: 600;
        }
        .view-details-btn {
            padding: 8px 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
            width: 100%;
        }
        .view-details-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease-in;
        }
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease-out;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 24px;
        }
        .close-modal {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .close-modal:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }
        .modal-body {
            padding: 30px;
        }
        .info-section {
            margin-bottom: 30px;
        }
        .info-section h3 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .info-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }
        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 15px;
            color: #333;
            font-weight: 600;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .items-table th {
            background: #f0f0f0;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #ddd;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .items-table tr:hover {
            background: #f9f9f9;
        }
        .price-summary {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 15px;
        }
        .price-row.total {
            font-weight: 700;
            font-size: 18px;
            padding-top: 15px;
            margin-top: 15px;
            border-top: 2px solid rgba(255,255,255,0.5);
            color: #667eea;
        }
        .status-badge-large {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .no-data-notice {
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            text-align: center;
            color: #999;
            font-style: italic;
        }

        /* Confirm Modal Styles */
        .confirm-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
        }
        .confirm-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .confirm-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            animation: slideIn 0.3s ease-out;
        }
        .confirm-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        .confirm-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .confirm-icon {
            font-size: 28px;
        }
        .confirm-body {
            padding: 25px 30px;
            color: #666;
            font-size: 16px;
            line-height: 1.6;
        }
        .confirm-actions {
            padding: 20px 30px;
            display: flex;
            gap: 10px;
            background: #f9f9f9;
            border-radius: 0 0 15px 15px;
        }
        .confirm-btn {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .confirm-btn-cancel {
            background: #e0e0e0;
            color: #666;
        }
        .confirm-btn-cancel:hover {
            background: #d0d0d0;
        }
        .confirm-btn-confirm {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .confirm-btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Form Input Styles */
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group small {
            font-size: 13px;
        }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }
        .loading-overlay.active {
            display: flex;
        }
        .loading-content {
            background: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #f0f0f0;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loading-text {
            font-size: 18px;
            color: #333;
            font-weight: 600;
        }

        /* Toast Notification */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 4000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 500px;
        }
        .toast {
            background: white;
            padding: 18px 22px;
            border-radius: 10px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: flex-start;
            gap: 14px;
            transform: translateX(550px);
            transition: transform 0.3s ease-out;
            min-width: 350px;
            max-width: 500px;
        }
        .toast.show {
            transform: translateX(0);
        }
        .toast.hide {
            transform: translateX(550px);
        }
        .toast-icon {
            font-size: 28px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .toast-content {
            flex: 1;
            overflow: hidden;
        }
        .toast-title {
            font-weight: 700;
            margin-bottom: 6px;
            font-size: 16px;
            color: #1f2937;
        }
        .toast-message {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 300px;
            overflow-y: auto;
        }
        .toast-close {
            background: none;
            border: none;
            font-size: 22px;
            color: #9ca3af;
            cursor: pointer;
            padding: 0;
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .toast-close:hover {
            background: #f3f4f6;
            color: #4b5563;
        }
        .toast.success {
            border-left: 5px solid #10b981;
        }
        .toast.success .toast-icon {
            color: #10b981;
        }
        .toast.error {
            border-left: 5px solid #ef4444;
        }
        .toast.error .toast-icon {
            color: #ef4444;
        }
        .toast.error .toast-title {
            color: #ef4444;
        }
        .toast.info {
            border-left: 5px solid #3b82f6;
        }
        .toast.info .toast-icon {
            color: #3b82f6;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🚀 CosmicTRD Dropshipping</h1>
            <div style="display: flex; align-items: center; gap: 20px;">
                <span>Welcome, <?php echo htmlspecialchars($customer['name']); ?>!</span>
                <a href="logout.php" style="color: white; text-decoration: none;">Logout</a>
            </div>
        </div>
    </div>
    
    <nav>
        <ul>
            <li><a href="products.php">Products</a></li>
            <li><a href="connect_store.php">Connect Store</a></li>
            <li><a href="orders.php" class="active">Orders</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <?php if (empty($connected_stores)): ?>
        <div class="alert alert-info">
            <strong>👋 Connect Your Store!</strong> You need to connect a Shopify store to view orders.
            <a href="connect_store.php" style="margin-left: 15px; color: #1976d2; font-weight: 600;">Connect Store</a>
        </div>
        <?php else: ?>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Configuration Error:</strong> <?php echo $error_message; ?>
            <a href="connect_store.php">Reconnect Store</a>
        </div>
        <?php endif; ?>
        
        <?php if ($missing_customer_data): ?>
        <div class="alert alert-warning">
            <strong>📊 Limited Customer Data</strong><br>
            Most orders are missing customer information. This is typically due to your Shopify plan restrictions. 
            Customer details are only available on Shopify Advanced plan and higher. 
            <a href="https://www.shopify.com/pricing" target="_blank">Learn more about Shopify plans</a>
        </div>
        <?php endif; ?>
        
        <div class="toolbar">
            <div>
                <label style="margin-right: 10px;">Select Store:</label>
                <select id="storeSelect" onchange="changeStore()">
                    <?php foreach ($connected_stores as $store): ?>
                    <option value="<?php echo htmlspecialchars($store['shop_domain']); ?>" 
                            <?php echo $selected_store === $store['shop_domain'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($store['store_name'] ?: $store['shop_domain']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn" onclick="testFulfillParams()" style="background: #06b6d4;">🧪 Test Params</button>
                <button class="btn" onclick="showManualShopIdModal()" style="background: #ef4444;">✏️ Manual Shop ID</button>
                <button class="btn" onclick="showDebugInfo()" style="background: #8b5cf6;">🐛 Debug Info</button>
                <button class="btn" onclick="fixStoreConfig()" style="background: #10b981;">🔧 Fix Store Config</button>
                <button class="btn" onclick="testAPI()" style="background: #f59e0b;">🧪 Test API</button>
                <button class="btn" onclick="syncOrders()">🔄 Sync Orders</button>
            </div>
        </div>
        
        <?php if (empty($orders)): ?>
        <div class="orders-table">
            <div class="empty-state">
                <h2>📦 No Orders Yet</h2>
                <p>Your orders will appear here once customers start purchasing from your store.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="orders-table">
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($order['order_number'] ?? $order['name']); ?></strong></td>
                        <td>
                            <?php 
                            $customer_name = trim(($order['customer_first_name'] ?? '') . ' ' . ($order['customer_last_name'] ?? ''));
                            $customer_email = $order['customer_email'] ?? '';
                            
                            if ($customer_name):
                            ?>
                                <strong><?php echo htmlspecialchars($customer_name); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($customer_email); ?></small>
                            <?php elseif ($customer_email): ?>
                                <strong><?php echo htmlspecialchars($customer_email); ?></strong>
                            <?php else: ?>
                            <small style="color: #999;">
                                📦 No customer data<br>
                                <span style="font-size: 11px;">(Upgrade to Shopify Advanced)</span>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                        <td><?php echo $order['items_count'] ?? (isset($order['line_items']) ? count($order['line_items']) : 0); ?> item(s)</td>
                        <td><strong><?php echo $order['currency']; ?> <?php echo number_format($order['total_price'], 2); ?></strong></td>
                        <td>
                            <?php
                            $status = $order['fulfillment_status'];
                            $badgeClass = 'badge-pending';
                            $statusText = 'Pending';
                            
                            if ($status === 'fulfilled') {
                                $badgeClass = 'badge-fulfilled';
                                $statusText = 'Fulfilled';
                            } elseif ($status === 'partial') {
                                $badgeClass = 'badge-pending';
                                $statusText = 'Partial';
                            }
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>
                        </td>
                        <td>
                            <button class="view-details-btn" 
                                    onclick='showOrderDetails(<?php echo json_encode($order, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                👁️ View Details
                            </button>
                            <?php if ($status !== 'fulfilled'): ?>
                            <button class="fulfill-btn" 
                                    onclick="showConfirmModal('<?php echo $order['order_id'] ?? $order['id']; ?>', '<?php echo htmlspecialchars($order['order_number'] ?? $order['name']); ?>')">
                                Fulfill Order
                            </button>
                            <?php else: ?>
                            <span style="color: #28a745; display: block; margin-top: 5px;">✓ Fulfilled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Processing order...</div>
        </div>
    </div>
    
    <!-- Confirm Modal -->
    <div id="confirmModal" class="confirm-modal">
        <div class="confirm-content">
            <div class="confirm-header">
                <div class="confirm-title">
                    <span class="confirm-icon">📦</span>
                    <span id="confirmTitle">Confirm Action</span>
                </div>
            </div>
            <div class="confirm-body" id="confirmMessage">
                Are you sure you want to proceed?
            </div>
            <div class="confirm-actions">
                <button class="confirm-btn confirm-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
                <button class="confirm-btn confirm-btn-confirm" id="confirmButton">OK</button>
            </div>
        </div>
    </div>
    
    <!-- Manual Shop ID Modal -->
    <div id="manualShopIdModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2>✏️ Manually Set Hermate Shop ID</h2>
                <button class="close-modal" onclick="closeManualShopIdModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="background: #fef3c7; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #f59e0b;">
                    <strong>⚠️ Note:</strong> Use this only if you know your Hermate Shop ID. 
                    Normally, this should be set automatically when connecting your store through Hermate.
                </div>
                
                <form id="manualShopIdForm">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Store Domain
                        </label>
                        <input type="text" id="manualShopDomain" readonly 
                               style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; background: #f9f9f9; color: #666;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                            Hermate Shop ID <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" id="manualShopId" required placeholder="Enter the Hermate Shop ID (e.g., 12345)"
                               style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            You can get this ID from your Hermate dashboard or API response
                        </small>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 25px;">
                        <button type="button" onclick="closeManualShopIdModal()" 
                                style="flex: 1; padding: 12px; background: #e0e0e0; color: #666; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            Cancel
                        </button>
                        <button type="submit" 
                                style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            Save Shop ID
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Toast Notification System
        function showToast(type, title, message) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icons = {
                success: '✓',
                error: '✕',
                info: 'ℹ'
            };
            
            toast.innerHTML = `
                <div class="toast-icon">${icons[type] || 'ℹ'}</div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    ${message ? `<div class="toast-message">${escapeHtml(message)}</div>` : ''}
                </div>
                <button class="toast-close" onclick="closeToast(this)">×</button>
            `;
            
            container.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => toast.classList.add('show'), 10);
            
            // Auto remove based on type (errors stay longer)
            const duration = type === 'error' ? 8000 : 5000;
            setTimeout(() => {
                if (toast.parentElement) {
                    closeToast(toast.querySelector('.toast-close'));
                }
            }, duration);
        }
        
        function closeToast(button) {
            const toast = button.closest('.toast');
            toast.classList.add('hide');
            toast.classList.remove('show');
            
            setTimeout(() => {
                toast.remove();
            }, 300);
        }
        
        // Loading Overlay
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }
        
        // Confirm Modal
        let confirmCallback = null;
        
        // Test API Connection
        async function testAPI() {
            showToast('info', 'Testing API', 'Checking if fulfill_order.php is accessible...');
            
            try {
                const response = await fetch('api/fulfill_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        order_id: 'test',
                        shop_domain: 'test'
                    })
                });
                
                const text = await response.text();
                
                if (response.status === 404) {
                    showToast('error', 'API Not Found (404)', 'fulfill_order.php is not in the api/ folder. Please check file location.');
                } else if (response.status === 500) {
                    showToast('error', 'Server Error (500)', 'PHP error detected. Check: ' + text.substring(0, 100));
                } else if (response.ok) {
                    showToast('success', 'API Accessible!', 'File found. Response: ' + text.substring(0, 100));
                } else {
                    showToast('error', 'HTTP ' + response.status, text.substring(0, 100));
                }
                
                console.log('Test API Response:', { status: response.status, body: text });
            } catch (error) {
                showToast('error', 'Connection Failed', error.message);
                console.error('Test API Error:', error);
            }
        }
        
        // Test Fulfill Parameters
        async function testFulfillParams() {
            <?php if (empty($orders)): ?>
            showToast('error', 'No Orders', 'You need at least one order to test fulfillment parameters');
            return;
            <?php else: ?>
            const testOrder = <?php echo json_encode($orders[0]); ?>;
            const orderId = testOrder.order_id || testOrder.id;
            const orderNumber = testOrder.order_number || testOrder.name;
            
            showToast('info', 'Testing Parameters', 
                `Testing different parameter combinations with order #${orderNumber}...\n\nThis will NOT actually fulfill the order.`);
            showLoading();
            
            try {
                const response = await fetch('api/test_fulfill_params.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        order_id: orderId,
                        shop_domain: '<?php echo $selected_store; ?>'
                    })
                });
                
                const result = await response.json();
                console.log('Parameter Test Results:', result);
                
                hideLoading();
                
                if (result.success) {
                    showToast('success', 'Test Successful! ✅', 
                        `${result.message}\n\nWorking parameters:\n${result.recommendation}\n\nCheck console for full details.`);
                } else {
                    showToast('error', 'All Tests Failed', 
                        `${result.message}\n\nCheck browser console (F12) for detailed test results.`);
                }
            } catch (error) {
                hideLoading();
                showToast('error', 'Test Error', error.message);
                console.error('Test Fulfill Params Error:', error);
            }
            <?php endif; ?>
        }
        
        // Fix Store Configuration
        async function fixStoreConfig() {
            const store = '<?php echo $selected_store; ?>';
            if (!store) {
                showToast('error', 'No Store Selected', 'Please select a store first');
                return;
            }
            
            showToast('info', 'Fixing Configuration', 'Fetching Hermate Shop ID...');
            showLoading();
            
            try {
                const response = await fetch('api/fix_store_config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        shop_domain: store
                    })
                });
                
                const result = await response.json();
                hideLoading();
                
                if (result.success) {
                    showToast('success', 'Configuration Fixed!', 
                        `Shop ID ${result.shop_id} has been saved. You can now fulfill orders!`);
                    
                    // Reload page after 2 seconds
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    let errorMessage = result.error || 'Could not fix store configuration';
                    if (result.details) {
                        errorMessage += '\n\n' + result.details;
                    }
                    if (result.suggestion) {
                        errorMessage += '\n\n💡 ' + result.suggestion;
                    }
                    
                    showToast('error', 'Fix Failed', errorMessage);
                    
                    // Log additional debug info
                    if (result.customer_email) {
                        console.log('Customer Email:', result.customer_email);
                        console.log('API Endpoint:', result.api_endpoint);
                    }
                }
            } catch (error) {
                hideLoading();
                showToast('error', 'Error', error.message);
                console.error('Fix Store Config Error:', error);
            }
        }
        
        // Show Debug Information
        function showDebugInfo() {
            const debugInfo = `
=== DEBUG INFORMATION ===

Customer Email: <?php echo htmlspecialchars($customer['email']); ?>
Customer Name: <?php echo htmlspecialchars($customer['name']); ?>
Customer ID: <?php echo $customer['id']; ?>

Selected Store: <?php echo htmlspecialchars($selected_store); ?>

API Endpoint Being Called:
GET /shopify/stores/<?php echo urlencode($customer['email']); ?>

Connected Stores in Local DB:
<?php 
foreach ($connected_stores as $store) {
    echo "- " . htmlspecialchars($store['shop_domain']) . " (Hermate ID: " . ($store['hermate_shop_id'] ?? 'NULL') . ")\n";
}
?>

ISSUE: The store is in your local database but not registered with Hermate API.
SOLUTION: Use the "Connect Store" page to properly register this store with Hermate.
            `.trim();
            
            console.log(debugInfo);
            
            showToast('info', 'Debug Info', 
                'Debug information has been logged to the browser console (F12 → Console tab). Also showing below:');
            
            // Also show in an alert for easy copying
            setTimeout(() => {
                alert(debugInfo);
            }, 500);
        }
        
        // Manual Shop ID Modal Functions
        function showManualShopIdModal() {
            const store = '<?php echo $selected_store; ?>';
            if (!store) {
                showToast('error', 'No Store Selected', 'Please select a store first');
                return;
            }
            
            document.getElementById('manualShopDomain').value = store;
            document.getElementById('manualShopId').value = '';
            document.getElementById('manualShopIdModal').classList.add('show');
        }
        
        function closeManualShopIdModal() {
            document.getElementById('manualShopIdModal').classList.remove('show');
            document.getElementById('manualShopIdForm').reset();
        }
        
        // Handle manual shop ID form submission
        document.getElementById('manualShopIdForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const shopDomain = document.getElementById('manualShopDomain').value;
            const shopId = document.getElementById('manualShopId').value.trim();
            
            if (!shopId) {
                showToast('error', 'Required Field', 'Please enter a Hermate Shop ID');
                return;
            }
            
            showLoading();
            
            try {
                const response = await fetch('api/set_shop_id.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        shop_domain: shopDomain,
                        hermate_shop_id: shopId
                    })
                });
                
                const result = await response.json();
                hideLoading();
                
                if (result.success) {
                    showToast('success', 'Shop ID Saved!', 
                        `Hermate Shop ID "${shopId}" has been set for ${shopDomain}. You can now fulfill orders!`);
                    closeManualShopIdModal();
                    
                    // Reload page after 2 seconds
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showToast('error', 'Failed to Save', result.error || 'Could not set shop ID');
                }
            } catch (error) {
                hideLoading();
                showToast('error', 'Error', error.message);
                console.error('Set Shop ID Error:', error);
            }
        });
        
        function showConfirmModal(orderId, orderNumber) {
            const modal = document.getElementById('confirmModal');
            document.getElementById('confirmTitle').textContent = 'Fulfill Order';
            document.getElementById('confirmMessage').textContent = `Fulfill order #${orderNumber}?`;
            
            confirmCallback = () => fulfillOrder(orderId, orderNumber);
            
            modal.classList.add('show');
            
            // Setup confirm button
            const confirmBtn = document.getElementById('confirmButton');
            confirmBtn.onclick = () => {
                closeConfirmModal();
                if (confirmCallback) {
                    confirmCallback();
                }
            };
        }
        
        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('show');
            confirmCallback = null;
        }
        
        function changeStore() {
            const store = document.getElementById('storeSelect').value;
            window.location.href = 'orders.php?store=' + encodeURIComponent(store);
        }
        
        async function syncOrders() {
            const store = '<?php echo $selected_store; ?>';
            if (!store) {
                showToast('error', 'No Store Selected', 'Please select a store first');
                return;
            }
            
            showToast('info', 'Syncing Orders', 'Refreshing order data from Shopify...');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
        
        async function fulfillOrder(orderId, orderNumber) {
            console.log('=== FULFILL ORDER DEBUG ===');
            console.log('Order ID:', orderId);
            console.log('Order Number:', orderNumber);
            console.log('Shop Domain:', '<?php echo $selected_store; ?>');
            
            showLoading();
            
            try {
                const requestData = {
                    order_id: orderId,
                    shop_domain: '<?php echo $selected_store; ?>'
                };
                
                console.log('Sending request to: api/fulfill_order.php');
                console.log('Request data:', requestData);
                
                const response = await fetch('api/fulfill_order.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestData)
                });
                
                console.log('Response received:');
                console.log('- Status:', response.status);
                console.log('- Status Text:', response.statusText);
                console.log('- Content-Type:', response.headers.get('content-type'));
                
                // Get response text
                const responseText = await response.text();
                console.log('- Response Body:', responseText);
                
                hideLoading();
                
                // Handle different HTTP status codes
                if (response.status === 404) {
                    showToast('error', 'API File Not Found', 
                        'The fulfill_order.php file is missing from the api/ folder. Please check your file structure.');
                    return;
                }
                
                if (response.status === 500) {
                    showToast('error', 'Server Error', 
                        'PHP error occurred. Check console for details.');
                    console.error('Server error response:', responseText);
                    return;
                }
                
                if (!response.ok) {
                    showToast('error', `HTTP Error ${response.status}`, 
                        responseText.substring(0, 150));
                    return;
                }
                
                // Try to parse JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                    console.log('Parsed JSON result:', result);
                } catch (parseError) {
                    console.error('Failed to parse JSON:', parseError);
                    console.error('Response was not valid JSON:', responseText);
                    showToast('error', 'Invalid Response', 
                        'Server returned invalid JSON. Check console for details.');
                    return;
                }
                
                // Handle the result
                if (result.success) {
                    showToast('success', 'Order Fulfilled!', 
                        `Order #${orderNumber} has been fulfilled successfully`);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    console.error('Fulfillment failed:', result);
                    
                    let errorMessage = result.error || 'Failed to fulfill order';
                    if (result.details) {
                        errorMessage += '\n' + result.details;
                    }
                    
                    showToast('error', 'Fulfillment Failed', errorMessage);
                }
            } catch (error) {
                console.error('=== FULFILLMENT ERROR ===');
                console.error('Error type:', error.name);
                console.error('Error message:', error.message);
                console.error('Full error:', error);
                
                hideLoading();
                
                let userMessage = error.message;
                if (error.message.includes('Failed to fetch')) {
                    userMessage = 'Network error: Cannot connect to server. Check if the API file exists.';
                }
                
                showToast('error', 'Error', userMessage);
            }
        }
        
        // Modal Functions
        function showOrderDetails(order) {
            const modal = document.getElementById('orderModal');
            
            // Populate modal with order data
            document.getElementById('modalOrderNumber').textContent = order.order_number || order.name || 'N/A';
            document.getElementById('modalOrderDate').textContent = formatDate(order.created_at);
            
            // Order Status
            const statusBadge = document.getElementById('modalStatus');
            statusBadge.textContent = order.fulfillment_status || 'pending';
            statusBadge.className = 'status-badge-large badge-' + (order.fulfillment_status || 'pending');
            
            // Financial Status
            document.getElementById('modalFinancialStatus').textContent = order.financial_status || 'N/A';
            
            // Customer Information
            const customerSection = document.getElementById('customerInfo');
            const customerName = [order.customer_first_name, order.customer_last_name].filter(Boolean).join(' ');
            const customerEmail = order.customer_email || '';
            const customerPhone = order.customer_phone || '';
            
            if (customerName || customerEmail) {
                customerSection.innerHTML = `
                    <div class="info-grid">
                        ${customerName ? `<div class="info-item">
                            <div class="info-label">Customer Name</div>
                            <div class="info-value">${escapeHtml(customerName)}</div>
                        </div>` : ''}
                        ${customerEmail ? `<div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value">${escapeHtml(customerEmail)}</div>
                        </div>` : ''}
                        ${customerPhone ? `<div class="info-item">
                            <div class="info-label">Phone</div>
                            <div class="info-value">${escapeHtml(customerPhone)}</div>
                        </div>` : ''}
                    </div>
                `;
            } else {
                customerSection.innerHTML = `
                    <div class="no-data-notice">
                        Customer information not available (Shopify plan limitation)
                    </div>
                `;
            }
            
            // Shipping Address
            const shippingSection = document.getElementById('shippingAddress');
            if (order.shipping_address1 || order.shipping_city) {
                shippingSection.innerHTML = `
                    <div class="info-item" style="max-width: 100%;">
                        <div class="info-label">Shipping Address</div>
                        <div class="info-value">
                            ${order.shipping_name ? escapeHtml(order.shipping_name) + '<br>' : ''}
                            ${order.shipping_address1 ? escapeHtml(order.shipping_address1) + '<br>' : ''}
                            ${order.shipping_address2 ? escapeHtml(order.shipping_address2) + '<br>' : ''}
                            ${order.shipping_city ? escapeHtml(order.shipping_city) : ''}
                            ${order.shipping_province ? ', ' + escapeHtml(order.shipping_province) : ''}
                            ${order.shipping_zip ? ' ' + escapeHtml(order.shipping_zip) : ''}<br>
                            ${order.shipping_country ? escapeHtml(order.shipping_country) : ''}
                            ${order.shipping_phone ? '<br>Phone: ' + escapeHtml(order.shipping_phone) : ''}
                        </div>
                    </div>
                `;
            } else {
                shippingSection.innerHTML = `
                    <div class="no-data-notice">
                        Shipping address not available
                    </div>
                `;
            }
            
            // Billing Address
            const billingSection = document.getElementById('billingAddress');
            if (order.billing_address1 || order.billing_city) {
                billingSection.innerHTML = `
                    <div class="info-item" style="max-width: 100%;">
                        <div class="info-label">Billing Address</div>
                        <div class="info-value">
                            ${order.billing_name ? escapeHtml(order.billing_name) + '<br>' : ''}
                            ${order.billing_address1 ? escapeHtml(order.billing_address1) + '<br>' : ''}
                            ${order.billing_address2 ? escapeHtml(order.billing_address2) + '<br>' : ''}
                            ${order.billing_city ? escapeHtml(order.billing_city) : ''}
                            ${order.billing_province ? ', ' + escapeHtml(order.billing_province) : ''}
                            ${order.billing_zip ? ' ' + escapeHtml(order.billing_zip) : ''}<br>
                            ${order.billing_country ? escapeHtml(order.billing_country) : ''}
                            ${order.billing_phone ? '<br>Phone: ' + escapeHtml(order.billing_phone) : ''}
                        </div>
                    </div>
                `;
            } else {
                billingSection.innerHTML = `
                    <div class="no-data-notice">
                        Billing address not available
                    </div>
                `;
            }
            
            // Line Items
            const itemsTableBody = document.getElementById('itemsTableBody');
            if (order.line_items && order.line_items.length > 0) {
                itemsTableBody.innerHTML = order.line_items.map(item => `
                    <tr>
                        <td><strong>${escapeHtml(item.title || 'N/A')}</strong>${item.variant_title ? '<br><small style="color: #999;">' + escapeHtml(item.variant_title) + '</small>' : ''}</td>
                        <td style="text-align: center;">${item.quantity || 1}</td>
                        <td style="text-align: right;">${order.currency} ${parseFloat(item.price || 0).toFixed(2)}</td>
                        <td style="text-align: right;"><strong>${order.currency} ${(parseFloat(item.price || 0) * parseInt(item.quantity || 1)).toFixed(2)}</strong></td>
                    </tr>
                `).join('');
            } else {
                itemsTableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #999;">No items available</td></tr>';
            }
            
            // Price Summary
            document.getElementById('modalSubtotal').textContent = `${order.currency} ${parseFloat(order.subtotal_price || 0).toFixed(2)}`;
            document.getElementById('modalTax').textContent = `${order.currency} ${parseFloat(order.total_tax || 0).toFixed(2)}`;
            document.getElementById('modalShipping').textContent = order.shipping_lines && order.shipping_lines.length > 0 
                ? `${order.currency} ${parseFloat(order.shipping_lines[0].price || 0).toFixed(2)}`
                : `${order.currency} 0.00`;
            document.getElementById('modalTotal').textContent = `${order.currency} ${parseFloat(order.total_price || 0).toFixed(2)}`;
            
            // Show modal
            modal.classList.add('show');
        }
        
        function closeModal() {
            const modal = document.getElementById('orderModal');
            modal.classList.remove('show');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target === modal) {
                closeModal();
            }
            
            const confirmModal = document.getElementById('confirmModal');
            if (event.target === confirmModal) {
                closeConfirmModal();
            }
            
            const manualShopIdModal = document.getElementById('manualShopIdModal');
            if (event.target === manualShopIdModal) {
                closeManualShopIdModal();
            }
        }
        
        // Helper functions
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
    
    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📦 Order Details - #<span id="modalOrderNumber"></span></h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Order Status -->
                <div class="info-section">
                    <h3>📊 Order Status</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Order Date</div>
                            <div class="info-value" id="modalOrderDate"></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Fulfillment Status</div>
                            <div class="info-value">
                                <span id="modalStatus" class="status-badge-large"></span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Payment Status</div>
                            <div class="info-value" id="modalFinancialStatus"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Information -->
                <div class="info-section">
                    <h3>👤 Customer Information</h3>
                    <div id="customerInfo"></div>
                </div>
                
                <!-- Shipping Address -->
                <div class="info-section">
                    <h3>🚚 Shipping Address</h3>
                    <div id="shippingAddress"></div>
                </div>
                
                <!-- Billing Address -->
                <div class="info-section">
                    <h3>💳 Billing Address</h3>
                    <div id="billingAddress"></div>
                </div>
                
                <!-- Order Items -->
                <div class="info-section">
                    <h3>📦 Order Items</h3>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th style="text-align: center;">Quantity</th>
                                <th style="text-align: right;">Price</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                        </tbody>
                    </table>
                </div>
                
                <!-- Price Summary -->
                <div class="price-summary">
                    <div class="price-row">
                        <span>Subtotal:</span>
                        <span id="modalSubtotal"></span>
                    </div>
                    <div class="price-row">
                        <span>Tax:</span>
                        <span id="modalTax"></span>
                    </div>
                    <div class="price-row">
                        <span>Shipping:</span>
                        <span id="modalShipping"></span>
                    </div>
                    <div class="price-row total">
                        <span>Total:</span>
                        <span id="modalTotal"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>