<?php
// migrate_orders_table.php
// Run this ONCE to add customer data columns to your dropship_orders table

require_once 'config.php'; // Your database connection file

// Check and add columns if they don't exist
$columns_to_add = [
    'customer_name' => "VARCHAR(255) DEFAULT NULL AFTER order_number",
    'customer_email' => "VARCHAR(255) DEFAULT NULL AFTER customer_name",
    'customer_phone' => "VARCHAR(50) DEFAULT NULL AFTER customer_email",
    'shipping_address' => "TEXT DEFAULT NULL AFTER customer_phone",
    'billing_address' => "TEXT DEFAULT NULL AFTER shipping_address",
    'currency' => "VARCHAR(10) DEFAULT 'BDT' AFTER total_amount",
    'financial_status' => "VARCHAR(50) DEFAULT NULL AFTER currency",
    'items_count' => "INT DEFAULT 1 AFTER fulfillment_status",
    'order_data' => "LONGTEXT DEFAULT NULL AFTER items_count",
    'updated_at' => "TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
];

echo "<h2>Database Migration: Adding Customer Data Fields</h2>";
echo "<pre>";

foreach ($columns_to_add as $column => $definition) {
    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM dropship_orders LIKE '$column'");
    
    if ($check->num_rows == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE dropship_orders ADD COLUMN $column $definition";
        
        if ($conn->query($sql)) {
            echo "✓ Added column: $column\n";
        } else {
            echo "✗ Error adding $column: " . $conn->error . "\n";
        }
    } else {
        echo "- Column already exists: $column\n";
    }
}

echo "\n";

// Create the dropship_orders table if it doesn't exist
$create_table_sql = "
CREATE TABLE IF NOT EXISTS dropship_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    shop_id INT DEFAULT NULL,
    shopify_order_id VARCHAR(255) NOT NULL,
    order_number VARCHAR(100) NOT NULL,
    customer_name VARCHAR(255) DEFAULT NULL,
    customer_email VARCHAR(255) DEFAULT NULL,
    customer_phone VARCHAR(50) DEFAULT NULL,
    shipping_address TEXT DEFAULT NULL,
    billing_address TEXT DEFAULT NULL,
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'BDT',
    financial_status VARCHAR(50) DEFAULT NULL,
    fulfillment_status VARCHAR(50) DEFAULT 'unfulfilled',
    items_count INT DEFAULT 1,
    order_data LONGTEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_order (shopify_order_id, customer_id),
    KEY idx_customer (customer_id),
    KEY idx_shop (shop_id),
    KEY idx_status (fulfillment_status),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($create_table_sql)) {
    echo "✓ dropship_orders table structure verified\n";
} else {
    echo "✗ Error with table: " . $conn->error . "\n";
}

echo "\n✅ Migration completed!\n";
echo "</pre>";

// Close connection
$conn->close();
?>