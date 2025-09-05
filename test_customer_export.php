<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'src/config/database.php';
require_once 'src/classes/ExcelExportService.php';

echo "Testing Customer Export...\n";

try {
    $db = DatabaseConfig::getInstance();
    echo "Database connection: OK\n";
    
    $exportService = new ExcelExportService();
    echo "ExcelExportService created: OK\n";
    
    // Test database query
    $sql = "SELECT COUNT(*) as count FROM trading_partners";
    $stmt = $db->getConnection()->query($sql);
    $result = $stmt->fetch();
    echo "Customers in database: " . $result['count'] . "\n";
    
    // Test export
    echo "Attempting Excel export...\n";
    $result = $exportService->exportCustomers('xlsx');
    
    if ($result['success']) {
        echo "Export successful!\n";
        echo "Filename: " . $result['filename'] . "\n";
        echo "Filepath: " . $result['filepath'] . "\n";
        echo "Download URL: " . $result['download_url'] . "\n";
        echo "File exists: " . (file_exists($result['filepath']) ? 'YES' : 'NO') . "\n";
    } else {
        echo "Export failed\n";
        print_r($result);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>