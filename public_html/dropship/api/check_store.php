<?php
// Diagnostic tool to check store configuration
header('Content-Type: application/json');

$diagnostic = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => [],
    'overall_status' => 'checking'
];

try {
    // Check 1: Can we load config?
    if (!file_exists('../config.php')) {
        $diagnostic['checks']['config_file'] = [
            'status' => 'fail',
            'message' => 'config.php not found',
            'solution' => 'Upload config.php to /dropship/ directory'
        ];
        echo json_encode($diagnostic, JSON_PRETTY_PRINT);
        exit;
    }
    
    require_once '../config.php';
    
    $diagnostic['checks']['config_file'] = [
        'status' => 'pass',
        'message' => 'config.php loaded successfully'
    ];
    
    // Check 2: Are we logged in?
    session_start();
    if (!isset($_SESSION['customer_id'])) {
        $diagnostic['checks']['authentication'] = [
            'status' => 'fail',
            'message' => 'Not logged in',
            'solution' => 'Log in to the dropship system first'
        ];
        echo json_encode($diagnostic, JSON_PRETTY_PRINT);
        exit;
    }
    
    $diagnostic['checks']['authentication'] = [
        'status' => 'pass',
        'message' => 'User is logged in',
        'customer_id' => $_SESSION['customer_id']
    ];
    
    // Check 3: Can we connect to database?
    if (!isset($conn) || !$conn) {
        $diagnostic['checks']['database_connection'] = [
            'status' => 'fail',
            'message' => 'Database connection failed',
            'solution' => 'Check database credentials in config.php'
        ];
        echo json_encode($diagnostic, JSON_PRETTY_PRINT);
        exit;
    }
    
    $diagnostic['checks']['database_connection'] = [
        'status' => 'pass',
        'message' => 'Database connected'
    ];
    
    // Get customer info
    $customer = getDropshipCustomer($conn);
    $shop_domain = $_GET['store'] ?? null;
    
    $diagnostic['store_requested'] = $shop_domain;
    $diagnostic['customer_email'] = $customer['email'] ?? 'unknown';
    
    // Check 4: Does table exist?
    $table_check = $conn->query("SHOW TABLES LIKE 'dropship_stores'");
    if ($table_check->num_rows === 0) {
        $diagnostic['checks']['table_exists'] = [
            'status' => 'fail',
            'message' => 'Table dropship_stores does not exist',
            'solution' => 'Run dropship_stores_table.sql in phpMyAdmin'
        ];
        echo json_encode($diagnostic, JSON_PRETTY_PRINT);
        exit;
    }
    
    $diagnostic['checks']['table_exists'] = [
        'status' => 'pass',
        'message' => 'Table dropship_stores exists'
    ];
    
    // Check 5: Does column exist?
    $columns = $conn->query("DESCRIBE dropship_stores");
    $has_shop_id_column = false;
    $column_list = [];
    while ($col = $columns->fetch_assoc()) {
        $column_list[] = $col['Field'];
        if ($col['Field'] === 'hermate_shop_id') {
            $has_shop_id_column = true;
        }
    }
    
    $diagnostic['checks']['hermate_shop_id_column'] = [
        'status' => $has_shop_id_column ? 'pass' : 'fail',
        'message' => $has_shop_id_column ? 'Column hermate_shop_id exists' : 'Column hermate_shop_id is MISSING',
        'solution' => $has_shop_id_column ? null : 'Run update_stores_table.sql in phpMyAdmin',
        'existing_columns' => $column_list
    ];
    
    if (!$has_shop_id_column) {
        echo json_encode($diagnostic, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Check 6: Store exists in database?
    if ($shop_domain) {
        $stmt = $conn->prepare("SELECT * FROM dropship_stores WHERE customer_id = ? AND shop_domain = ?");
        $stmt->bind_param("is", $customer['id'], $shop_domain);
        $stmt->execute();
        $store = $stmt->get_result()->fetch_assoc();
        
        if ($store) {
            $diagnostic['checks']['store_in_database'] = [
                'status' => 'pass',
                'message' => 'Store found in local database',
                'store_name' => $store['store_name'],
                'shop_domain' => $store['shop_domain'],
                'active' => $store['active'] ? 'yes' : 'no',
                'has_access_token' => !empty($store['access_token']) ? 'yes' : 'no'
            ];
            
            // Check 7: Has shop_id?
            if ($store['hermate_shop_id']) {
                $diagnostic['checks']['has_shop_id'] = [
                    'status' => 'pass',
                    'message' => 'Shop ID is saved',
                    'shop_id' => $store['hermate_shop_id']
                ];
            } else {
                $diagnostic['checks']['has_shop_id'] = [
                    'status' => 'fail',
                    'message' => '⚠️ Shop ID is NULL - Store needs to be reconnected',
                    'solution' => 'Go to Connect Store page and reconnect this store',
                    'action_url' => 'https://cosmictrd.io/dropship/connect_store.php'
                ];
            }
        } else {
            $diagnostic['checks']['store_in_database'] = [
                'status' => 'fail',
                'message' => 'Store NOT found in database',
                'solution' => 'Go to Connect Store page and add this store',
                'action_url' => 'https://cosmictrd.io/dropship/connect_store.php'
            ];
        }
    } else {
        $diagnostic['checks']['store_provided'] = [
            'status' => 'fail',
            'message' => 'No store domain provided in URL',
            'solution' => 'Add ?store=yourstore.myshopify.com to the URL'
        ];
    }
    
    // Check 8: Required functions exist?
    $functions_to_check = ['getHermateShopId', 'getStoreDetails', 'saveConnectedStore', 'getConnectedStores'];
    $functions_check = [];
    $all_functions_exist = true;
    
    foreach ($functions_to_check as $func) {
        $exists = function_exists($func);
        $functions_check[$func] = $exists ? '✅ exists' : '❌ MISSING';
        if (!$exists) $all_functions_exist = false;
    }
    
    $diagnostic['checks']['required_functions'] = [
        'status' => $all_functions_exist ? 'pass' : 'fail',
        'message' => $all_functions_exist ? 'All required functions exist' : 'Some functions are missing',
        'functions' => $functions_check,
        'solution' => $all_functions_exist ? null : 'Upload the latest config.php to /dropship/'
    ];
    
    // Overall status
    $all_pass = true;
    foreach ($diagnostic['checks'] as $check) {
        if (isset($check['status']) && $check['status'] === 'fail') {
            $all_pass = false;
            break;
        }
    }
    
    $diagnostic['overall_status'] = $all_pass ? '✅ ALL CHECKS PASSED' : '❌ SOME CHECKS FAILED';
    $diagnostic['system_ready'] = $all_pass;
    
    if ($all_pass) {
        $diagnostic['message'] = '🎉 System is properly configured! If sync still fails, it\'s likely a Hermate API issue.';
    } else {
        $diagnostic['message'] = '⚠️ Please fix the failed checks above, then try again.';
    }
    
} catch (Exception $e) {
    $diagnostic['error'] = [
        'message' => 'Unexpected error occurred',
        'details' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    $diagnostic['overall_status'] = 'ERROR';
}

echo json_encode($diagnostic, JSON_PRETTY_PRINT);
?>