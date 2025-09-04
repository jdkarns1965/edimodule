<?php
require_once 'src/config/database.php';
require_once 'src/config/app.php';

echo "=== EDI System Migration Validation ===\n\n";

$allTestsPassed = true;

// Test 1: Database Connection
echo "1. Testing database connection...\n";
try {
    $db = DatabaseConfig::getInstance();
    if ($db->testConnection()) {
        echo "    Database connection successful\n";
    } else {
        echo "    Database connection failed\n";
        $allTestsPassed = false;
    }
} catch (Exception $e) {
    echo "    Database connection error: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

// Test 2: Schema Validation
echo "\n2. Validating database schema...\n";
try {
    $conn = $db->getConnection();
    
    $requiredTables = [
        'trading_partners',
        'ship_to_locations', 
        'edi_transactions',
        'delivery_schedules',
        'shipments',
        'shipment_items',
        'customer_edi_configs',
        'customer_connections',
        'part_master',
        'part_location_mapping'
    ];
    
    $stmt = $conn->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "    Table $table exists\n";
        } else {
            echo "    Table $table missing\n";
            $allTestsPassed = false;
        }
    }
    
    echo "   Total tables: " . count($existingTables) . "\n";
    
} catch (Exception $e) {
    echo "    Schema validation error: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

// Test 3: Default Data
echo "\n3. Checking default data...\n";
try {
    // Check Nifco trading partner
    $stmt = $conn->query("SELECT COUNT(*) FROM trading_partners WHERE partner_code = 'NIFCO'");
    $nifcoCount = $stmt->fetchColumn();
    
    if ($nifcoCount > 0) {
        echo "    Nifco trading partner exists\n";
    } else {
        echo "    Nifco trading partner missing\n";
        $allTestsPassed = false;
    }
    
    // Check ship-to locations
    $stmt = $conn->query("SELECT COUNT(*) FROM ship_to_locations");
    $locationCount = $stmt->fetchColumn();
    
    if ($locationCount >= 3) {
        echo "    Ship-to locations populated ($locationCount locations)\n";
    } else {
        echo "    Insufficient ship-to locations ($locationCount found)\n";
        $allTestsPassed = false;
    }
    
    // Check EDI configs
    $stmt = $conn->query("SELECT COUNT(*) FROM customer_edi_configs");
    $configCount = $stmt->fetchColumn();
    
    if ($configCount > 0) {
        echo "    EDI configurations present ($configCount configs)\n";
    } else {
        echo "    No EDI configurations found\n";
        $allTestsPassed = false;
    }
    
} catch (Exception $e) {
    echo "    Default data check error: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

// Test 4: Directory Structure
echo "\n4. Validating directory structure...\n";
$requiredDirs = [
    'data/inbox',
    'data/outbox', 
    'data/processed',
    'data/archive',
    'data/error',
    'logs'
];

foreach ($requiredDirs as $dir) {
    if (is_dir($dir) && is_writable($dir)) {
        echo "    Directory $dir exists and is writable\n";
    } else {
        echo "    Directory $dir missing or not writable\n";
        $allTestsPassed = false;
    }
}

// Test 5: Configuration Files
echo "\n5. Checking configuration files...\n";
$configFiles = [
    'src/config/database.php',
    'src/config/app.php'
];

foreach ($configFiles as $file) {
    if (file_exists($file) && is_readable($file)) {
        echo "    Configuration file $file exists\n";
    } else {
        echo "    Configuration file $file missing\n";
        $allTestsPassed = false;
    }
}

// Test 6: Application Classes  
echo "\n6. Testing application classes...\n";
$classFiles = [
    'src/classes/EDI862Parser.php',
    'src/classes/PartMaster.php',
    'src/classes/DeliveryMatrix.php'
];

foreach ($classFiles as $file) {
    if (file_exists($file)) {
        echo "    Class file $file exists\n";
    } else {
        echo "    Class file $file missing\n";
        $allTestsPassed = false;
    }
}

// Final Summary
echo "\n=== Migration Validation Summary ===\n";
if ($allTestsPassed) {
    echo " ALL TESTS PASSED - Migration completed successfully!\n";
    echo "\nYour EDI processing environment is ready for development.\n";
    echo "\nNext steps:\n";
    echo "- Access web interface: http://localhost/edimodule/src/public/\n";
    echo "- Test SFTP integration\n";
    echo "- Import delivery schedule data\n";
    echo "- Configure part master data\n";
} else {
    echo " SOME TESTS FAILED - Please review the issues above.\n";
}

echo "\n";
?>