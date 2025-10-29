<?php
require_once 'config.php';
requireLogin();

$customer = getDropshipCustomer($conn);
$error = '';
$success = '';

// Handle store connection
if (isset($_POST['connect_store'])) {
    $shop_domain = trim($_POST['shop_domain']);
    $shop_name = trim($_POST['shop_name']);
    $access_token = trim($_POST['access_token']);
    
    // Use shop_domain as shop_name if not provided
    if (empty($shop_name)) {
        $shop_name = $shop_domain;
    }
    
    if (empty($shop_domain) || empty($access_token)) {
        $error = 'Shop domain and access token are required';
    } else {
        // Validate shop domain format
        if (!preg_match('/^[a-z0-9-]+\.myshopify\.com$/', $shop_domain)) {
            $error = 'Invalid shop domain format. Use: yourstore.myshopify.com';
        } else {
            // Save to local database first (primary storage)
            $saved = saveConnectedStore($conn, $customer['id'], $shop_domain, $shop_name, $access_token);
            
            if ($saved) {
                $success = 'Store connected successfully!';
                
                // Optionally sync with Hermate API (non-blocking)
                try {
                    $response = callHermateAPI('/shopify/connect', 'POST', [
                        'shop_domain' => $shop_domain,
                        'customer_name' => $customer['name'],
                        'access_token' => $access_token,
                        'customer_email' => $customer['email']
                    ]);
                    
                    if ($response['success']) {
                        // Extract shop_id from response and update local database
                        $hermate_shop_id = $response['data']['shop']['id'] ?? $response['data']['id'] ?? null;
                        
                        if ($hermate_shop_id) {
                            // Update the store with hermate_shop_id
                            saveConnectedStore($conn, $customer['id'], $shop_domain, $shop_name, $access_token, $hermate_shop_id);
                            error_log("Hermate shop_id saved: " . $hermate_shop_id);
                        }
                    } else {
                        // Log API error but don't fail - local storage succeeded
                        error_log("Hermate API sync warning: " . json_encode($response));
                    }
                } catch (Exception $e) {
                    // API failed, but local save succeeded so continue
                    error_log("Hermate API error (non-critical): " . $e->getMessage());
                }
            } else {
                $error = 'Failed to save store connection. Please try again.';
            }
        }
    }
}

// Get connected stores from local database
$connected_stores = getConnectedStores($conn, $customer['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect Store - CosmicTRD Dropshipping</title>
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
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .card h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .card p {
            color: #666;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            color: #333;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
        }
        .form-group small {
            display: block;
            color: #999;
            margin-top: 5px;
        }
        .btn {
            padding: 14px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        .stores-list {
            margin-top: 40px;
        }
        .store-item {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .store-info h3 {
            color: #333;
            margin-bottom: 5px;
        }
        .store-info p {
            color: #666;
            margin: 0;
        }
        .badge {
            background: #4caf50;
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        .instructions {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #2196f3;
        }
        .instructions h3 {
            color: #1976d2;
            margin-bottom: 15px;
        }
        .instructions ol {
            margin-left: 20px;
            line-height: 1.8;
        }
        .instructions code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🚀 CosmicTRD Dropshipping</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($customer['name']); ?>!</span>
                <a href="logout.php" style="color: white; text-decoration: none; margin-left: 20px;">Logout</a>
            </div>
        </div>
    </div>
    
    <nav>
        <ul>
            <li><a href="products.php">Products</a></li>
            <li><a href="connect_store.php" class="active">Connect Store</a></li>
            <li><a href="orders.php">Orders</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <h2>Connect Your Shopify Store</h2>
            <p>Link your Shopify store to import products and manage orders</p>
            
            <div class="instructions">
                <h3>📝 How to Get Your Shopify API Token:</h3>
                <ol>
                    <li>Go to your Shopify Admin</li>
                    <li>Navigate to <strong>Settings → Apps and sales channels</strong></li>
                    <li>Click <strong>"Develop apps"</strong></li>
                    <li>Click <strong>"Create an app"</strong> and give it a name (e.g., "CosmicTRD Dropship")</li>
                    <li>Go to <strong>Configuration</strong> and click <strong>"Configure"</strong> under Admin API</li>
                    <li>Enable these scopes:
                        <ul>
                            <li><code>read_products, write_products</code></li>
                            <li><code>read_orders, write_orders</code></li>
                            <li><code>read_assigned_fulfillment_orders, write_assigned_fulfillment_orders</code></li>
                        </ul>
                    </li>
                    <li>Click <strong>"Install app"</strong></li>
                    <li>Copy the <strong>Admin API access token</strong></li>
                </ol>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Store Name (Optional)</label>
                    <input type="text" name="shop_name" placeholder="My Awesome Store">
                    <small>A friendly name to identify your store</small>
                </div>
                
                <div class="form-group">
                    <label>Shop Domain *</label>
                    <input type="text" name="shop_domain" placeholder="yourstore.myshopify.com" required>
                    <small>Your Shopify store domain (e.g., mystore.myshopify.com)</small>
                </div>
                
                <div class="form-group">
                    <label>Admin API Access Token *</label>
                    <input type="text" name="access_token" placeholder="shpat_xxxxxxxxxxxxxxxxxxxxx" required>
                    <small>The Admin API access token from your Shopify app</small>
                </div>
                
                <button type="submit" name="connect_store" class="btn">Connect Store</button>
            </form>
        </div>
        
        <?php if (!empty($connected_stores)): ?>
        <div class="card">
            <h2>Connected Stores</h2>
            <div class="stores-list">
                <?php foreach ($connected_stores as $store): ?>
                <div class="store-item">
                    <div class="store-info">
                        <h3><?php echo htmlspecialchars($store['store_name'] ?: $store['shop_domain']); ?></h3>
                        <p><?php echo htmlspecialchars($store['shop_domain']); ?></p>
                    </div>
                    <span class="badge">✓ Connected</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>