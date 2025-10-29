<?php
require_once 'config.php';
requireLogin();

$customer = getDropshipCustomer($conn);

// Get customer's connected stores from local database (primary source)
$connected_stores = getConnectedStores($conn, $customer['id']);

// Optionally sync with Hermate API in background (non-blocking)
// This keeps data fresh but doesn't block the UI if API is down
if (function_exists('callHermateAPI')) {
    try {
        $response = callHermateAPI('/shopify/stores/' . urlencode($customer['email']), 'GET');
        // If API returns stores, we could optionally update local cache here
        // But we don't block on it
    } catch (Exception $e) {
        // API failed, but we already have local stores so continue
        error_log("Hermate API error (non-critical): " . $e->getMessage());
    }
}

// Get products from database
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$query = "SELECT * FROM dropship_products WHERE 1=1";
$params = [];
$types = '';

if ($category) {
    $query .= " AND category = ?";
    $params[] = $category;
    $types .= 's';
}

if ($search) {
    $query .= " AND (title LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

$query .= " ORDER BY created_at DESC LIMIT 50";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories
$categories_result = $conn->query("SELECT DISTINCT category FROM dropship_products ORDER BY category");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - CosmicTRD Dropshipping</title>
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
        .header h1 {
            font-size: 24px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
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
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group label {
            display: block;
            color: #666;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .filter-group button {
            width: 100%;
            padding: 10px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .product-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            background: #f0f0f0;
        }
        .product-info {
            padding: 20px;
        }
        .product-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            line-height: 1.4;
            height: 44px;
            overflow: hidden;
        }
        .product-prices {
            display: flex;
            gap: 10px;
            align-items: baseline;
            margin-bottom: 15px;
        }
        .product-price {
            font-size: 22px;
            font-weight: 700;
            color: #667eea;
        }
        .product-compare {
            font-size: 16px;
            color: #999;
            text-decoration: line-through;
        }
        .product-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
            margin-bottom: 15px;
        }
        .import-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
            position: relative;
        }
        .import-btn:hover {
            transform: translateY(-2px);
        }
        .import-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .badge {
            background: #e0e7ff;
            color: #667eea;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .alert-info {
            background: #e0f2fe;
            color: #0c4a6e;
            border-left: 4px solid #0284c7;
        }
        .btn-link {
            background: #0284c7;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            position: relative;
        }
        .modal-header {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group select, .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
        }
        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        .modal-actions button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            position: relative;
        }
        .btn-cancel {
            background: #e0e0e0;
            color: #666;
        }
        .btn-confirm {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-confirm:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
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
            z-index: 2000;
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
            z-index: 3000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
        }
        
        .toast {
            background: white;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(500px);
            transition: transform 0.3s ease-out;
            min-width: 300px;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.hide {
            transform: translateX(500px);
        }
        
        .toast-icon {
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 15px;
        }
        
        .toast-message {
            font-size: 14px;
            color: #666;
        }
        
        .toast-close {
            background: none;
            border: none;
            font-size: 20px;
            color: #999;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: background 0.2s;
        }
        
        .toast-close:hover {
            background: #f0f0f0;
        }
        
        .toast.success {
            border-left: 4px solid #10b981;
        }
        
        .toast.success .toast-icon {
            color: #10b981;
        }
        
        .toast.error {
            border-left: 4px solid #ef4444;
        }
        
        .toast.error .toast-icon {
            color: #ef4444;
        }
        
        .toast.info {
            border-left: 4px solid #3b82f6;
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
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($customer['name']); ?>!</span>
                <a href="logout.php" style="color: white; text-decoration: none;">Logout</a>
            </div>
        </div>
    </div>
    
    <nav>
        <ul>
            <li><a href="products.php" class="active">Products</a></li>
            <li><a href="connect_store.php">Connect Store</a></li>
            <li><a href="orders.php">Orders</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <?php if (empty($connected_stores)): ?>
        <div class="alert alert-info">
            <strong>👋 Get Started!</strong> Connect your Shopify store to start importing products.
            <a href="connect_store.php" class="btn-link" style="margin-left: 15px;">Connect Store</a>
        </div>
        <?php endif; ?>
        
        <div class="filters">
            <div class="filter-group">
                <label>Search Products</label>
                <input type="text" id="searchInput" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <label>Category</label>
                <select id="categorySelect">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                            <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <button onclick="applyFilters()">Apply Filters</button>
            </div>
        </div>
        
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($product['title']); ?>"
                     class="product-image">
                
                <div class="product-info">
                    <div class="product-title"><?php echo htmlspecialchars($product['title']); ?></div>
                    
                    <div class="product-prices">
                        <span class="product-price">$<?php echo number_format($product['price'], 2); ?></span>
                        <?php if ($product['compare_price'] > $product['price']): ?>
                        <span class="product-compare">$<?php echo number_format($product['compare_price'], 2); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-meta">
                        <span class="badge"><?php echo htmlspecialchars($product['category']); ?></span>
                        <span>SKU: <?php echo htmlspecialchars($product['sku']); ?></span>
                    </div>
                    
                    <button class="import-btn" onclick="openImportModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['title']); ?>')">
                        Import to Shopify
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Import Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Import Product</div>
            
            <div id="modalProductName" style="color: #666; margin-bottom: 20px;"></div>
            
            <form id="importForm">
                <input type="hidden" id="productId" name="product_id">
                
                <div class="form-group">
                    <label>Select Store</label>
                    <select id="storeSelect" name="shop_domain" required>
                        <option value="">-- Choose Store --</option>
                        <?php foreach ($connected_stores as $store): ?>
                        <option value="<?php echo htmlspecialchars($store['shop_domain']); ?>">
                            <?php echo htmlspecialchars($store['store_name'] ?: $store['shop_domain']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Price Markup (%)</label>
                    <input type="number" name="markup" value="50" min="0" max="300" step="1">
                    <small style="color: #666;">Increase price by this percentage</small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeImportModal()">Cancel</button>
                    <button type="submit" class="btn-confirm" id="importBtn">Import Now</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Importing product...</div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
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
                    ${message ? `<div class="toast-message">${message}</div>` : ''}
                </div>
                <button class="toast-close" onclick="closeToast(this)">×</button>
            `;
            
            container.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => toast.classList.add('show'), 10);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                closeToast(toast.querySelector('.toast-close'));
            }, 5000);
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
        
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const category = document.getElementById('categorySelect').value;
            
            let url = 'products.php?';
            if (search) url += 'search=' + encodeURIComponent(search) + '&';
            if (category) url += 'category=' + encodeURIComponent(category);
            
            window.location.href = url;
        }
        
        function openImportModal(productId, productName) {
            <?php if (empty($connected_stores)): ?>
            showToast('info', 'Store Required', 'Please connect a Shopify store first!');
            setTimeout(() => {
                window.location.href = 'connect_store.php';
            }, 1500);
            return;
            <?php endif; ?>
            
            document.getElementById('productId').value = productId;
            document.getElementById('modalProductName').textContent = productName;
            document.getElementById('importModal').classList.add('active');
        }
        
        function closeImportModal() {
            document.getElementById('importModal').classList.remove('active');
            document.getElementById('importForm').reset();
        }
        
        document.getElementById('importForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            const importBtn = document.getElementById('importBtn');
            
            // Disable button and show loading
            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="spinner"></span>Importing...';
            
            // Show loading overlay
            showLoading();
            
            try {
                const response = await fetch('api/import_product.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                // Hide loading
                hideLoading();
                
                if (result.success) {
                    showToast('success', 'Success!', 'Product imported successfully to your store');
                    closeImportModal();
                } else {
                    showToast('error', 'Import Failed', result.error || 'Failed to import product. Please try again.');
                }
            } catch (error) {
                // Hide loading
                hideLoading();
                showToast('error', 'Error', error.message || 'An unexpected error occurred');
            } finally {
                // Re-enable button
                importBtn.disabled = false;
                importBtn.innerHTML = 'Import Now';
            }
        });
        
        // Close modal on outside click
        document.getElementById('importModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImportModal();
            }
        });
    </script>
</body>
</html>