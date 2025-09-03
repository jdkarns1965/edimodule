<?php
/**
 * Comprehensive test script for Excel export functionality
 * Tests all export features of the hybrid ERP system
 */

require_once 'bootstrap.php';
require_once 'src/config/database.php';
require_once 'src/classes/ExcelExportService.php';

echo "<h1>Excel Export System Test</h1>\n";
echo "<pre>\n";

$exportService = new ExcelExportService();
$db = DatabaseConfig::getInstance();

// Test 1: Customer Export
echo "=== Test 1: Customer Export ===\n";
try {
    $result = $exportService->exportCustomers('xlsx');
    if ($result['success']) {
        echo "✓ Customer export (Excel) successful: " . $result['filename'] . "\n";
    } else {
        echo "✗ Customer export failed\n";
    }
    
    $csvResult = $exportService->exportCustomers('csv');
    if ($csvResult['success']) {
        echo "✓ Customer export (CSV) successful: " . $csvResult['filename'] . "\n";
    } else {
        echo "✗ Customer CSV export failed\n";
    }
} catch (Exception $e) {
    echo "✗ Customer export error: " . $e->getMessage() . "\n";
}

// Test 2: Inventory Report Export
echo "\n=== Test 2: Inventory Report Export ===\n";
try {
    $dateRange = [
        'start' => date('Y-m-d'),
        'end' => date('Y-m-d', strtotime('+30 days'))
    ];
    
    $result = $exportService->exportInventoryReport($dateRange, null, 'xlsx');
    if ($result['success']) {
        echo "✓ Inventory report (Excel) successful: " . $result['filename'] . "\n";
    } else {
        echo "✗ Inventory report failed\n";
    }
    
    $csvResult = $exportService->exportInventoryReport($dateRange, null, 'csv');
    if ($csvResult['success']) {
        echo "✓ Inventory report (CSV) successful: " . $csvResult['filename'] . "\n";
    } else {
        echo "✗ Inventory report CSV failed\n";
    }
} catch (Exception $e) {
    echo "✗ Inventory export error: " . $e->getMessage() . "\n";
}

// Test 3: Delivery Schedule Export
echo "\n=== Test 3: Delivery Schedule Export ===\n";
try {
    $result = $exportService->exportDeliverySchedule(null, null, 'xlsx');
    if ($result['success']) {
        echo "✓ Delivery schedule (Excel) successful: " . $result['filename'] . "\n";
    } else {
        echo "✗ Delivery schedule failed\n";
    }
    
    $csvResult = $exportService->exportDeliverySchedule(null, null, 'csv');
    if ($csvResult['success']) {
        echo "✓ Delivery schedule (CSV) successful: " . $csvResult['filename'] . "\n";
    } else {
        echo "✗ Delivery schedule CSV failed\n";
    }
} catch (Exception $e) {
    echo "✗ Delivery schedule export error: " . $e->getMessage() . "\n";
}

// Test 4: Create Test Shipment Data
echo "\n=== Test 4: Creating Test Shipment ===\n";
try {
    // Get first trading partner
    $partnerStmt = $db->getConnection()->query("SELECT id FROM trading_partners LIMIT 1");
    $partner = $partnerStmt->fetch();
    
    if ($partner) {
        // Create test shipment
        $shipmentSql = "INSERT INTO shipments (shipment_number, partner_id, po_number, ship_date, 
                                              carrier_name, carrier_scac, total_weight, status, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'planned', 'Test Script')";
        
        $shipmentNumber = 'TEST-' . date('YmdHis');
        $stmt = $db->getConnection()->prepare($shipmentSql);
        $stmt->execute([
            $shipmentNumber,
            $partner['id'],
            'TEST-PO-001',
            date('Y-m-d'),
            'TEST CARRIER',
            'TEST',
            100.00
        ]);
        
        $shipmentId = $db->getConnection()->lastInsertId();
        echo "✓ Test shipment created: ID " . $shipmentId . "\n";
        
        // Add test shipment item
        $itemSql = "INSERT INTO shipment_items (shipment_id, supplier_item, item_description, 
                                               po_line, quantity_shipped, container_count, lot_number) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
        $itemStmt = $db->getConnection()->prepare($itemSql);
        $itemStmt->execute([
            $shipmentId,
            'TEST-PART-001',
            'Test Part Description',
            'TEST-LINE-001',
            1000,
            10,
            'LOT-TEST-001'
        ]);
        echo "✓ Test shipment item added\n";
        
        // Test 5: Shipment Export Templates
        echo "\n=== Test 5: Shipment Export Templates ===\n";
        
        // Test Packing List
        $packingResult = $exportService->exportShipmentForExcel($shipmentId, 'packing_list', 'xlsx');
        if ($packingResult['success']) {
            echo "✓ Packing list export successful: " . $packingResult['filename'] . "\n";
        } else {
            echo "✗ Packing list export failed\n";
        }
        
        // Test Pick List
        $pickResult = $exportService->exportShipmentForExcel($shipmentId, 'pick_list', 'xlsx');
        if ($pickResult['success']) {
            echo "✓ Pick list export successful: " . $pickResult['filename'] . "\n";
        } else {
            echo "✗ Pick list export failed\n";
        }
        
        // Test BOL
        $bolResult = $exportService->exportShipmentForExcel($shipmentId, 'bol', 'xlsx');
        if ($bolResult['success']) {
            echo "✓ BOL export successful: " . $bolResult['filename'] . "\n";
        } else {
            echo "✗ BOL export failed\n";
        }
        
        // Test CSV versions
        $packingCsvResult = $exportService->exportShipmentForExcel($shipmentId, 'packing_list', 'csv');
        if ($packingCsvResult['success']) {
            echo "✓ Packing list CSV export successful: " . $packingCsvResult['filename'] . "\n";
        } else {
            echo "✗ Packing list CSV export failed\n";
        }
        
        // Clean up test data
        echo "\n=== Cleaning Up Test Data ===\n";
        $db->getConnection()->prepare("DELETE FROM shipment_items WHERE shipment_id = ?")->execute([$shipmentId]);
        $db->getConnection()->prepare("DELETE FROM shipments WHERE id = ?")->execute([$shipmentId]);
        echo "✓ Test shipment data cleaned up\n";
        
    } else {
        echo "✗ No trading partners found for shipment test\n";
    }
    
} catch (Exception $e) {
    echo "✗ Shipment test error: " . $e->getMessage() . "\n";
}

// Test 6: CSV Export Function
echo "\n=== Test 6: CSV Export Function ===\n";
try {
    $testData = [
        ['Column 1', 'Column 2', 'Column 3'],
        ['Row 1 Data 1', 'Row 1 Data 2', 'Row 1 Data 3'],
        ['Row 2 Data 1', 'Row 2 Data 2', 'Row 2 Data 3']
    ];
    
    $headers = ['Header 1', 'Header 2', 'Header 3'];
    $result = $exportService->exportToCSV($testData, $headers, 'test_csv_function');
    
    if ($result['success']) {
        echo "✓ CSV export function test successful: " . $result['filename'] . "\n";
    } else {
        echo "✗ CSV export function test failed\n";
    }
} catch (Exception $e) {
    echo "✗ CSV export function error: " . $e->getMessage() . "\n";
}

// Test 7: Directory Permissions and File Access
echo "\n=== Test 7: Directory and File Permissions ===\n";
$exportDir = '/var/www/html/edimodule/data/exports/';

if (is_dir($exportDir)) {
    echo "✓ Export directory exists: " . $exportDir . "\n";
    
    if (is_writable($exportDir)) {
        echo "✓ Export directory is writable\n";
    } else {
        echo "✗ Export directory is not writable\n";
    }
    
    // List recent export files
    $files = glob($exportDir . '*.{xlsx,csv}', GLOB_BRACE);
    if (!empty($files)) {
        echo "✓ Export files found: " . count($files) . " files\n";
        
        // Show most recent files
        $recentFiles = array_slice(array_map('basename', $files), -3);
        foreach ($recentFiles as $file) {
            echo "  - " . $file . "\n";
        }
    } else {
        echo "! No export files found (this might be expected on first run)\n";
    }
} else {
    echo "✗ Export directory does not exist: " . $exportDir . "\n";
}

// Test 8: PhpSpreadsheet Version Check
echo "\n=== Test 8: PhpSpreadsheet Library Check ===\n";
try {
    $reflection = new ReflectionClass('PhpOffice\PhpSpreadsheet\Spreadsheet');
    echo "✓ PhpSpreadsheet library loaded successfully\n";
    echo "  Location: " . dirname($reflection->getFileName()) . "\n";
    
    // Test basic spreadsheet creation
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Test Cell');
    echo "✓ Basic spreadsheet operations working\n";
    
} catch (Exception $e) {
    echo "✗ PhpSpreadsheet library error: " . $e->getMessage() . "\n";
}

// Summary
echo "\n=== Test Summary ===\n";
echo "All tests completed. Check above for any failures (marked with ✗)\n";
echo "Export files are saved in: " . $exportDir . "\n";
echo "You can download these files to test with your M365 templates.\n";

echo "</pre>\n";

// Show web links to download files if running via web
if (isset($_SERVER['HTTP_HOST'])) {
    echo "<h2>Download Test Files</h2>\n";
    echo "<p>The following export files were generated during testing:</p>\n";
    echo "<ul>\n";
    
    $webPath = 'data/exports/';
    $files = glob($exportDir . '*.{xlsx,csv}', GLOB_BRACE);
    $recentFiles = array_slice($files, -10); // Show last 10 files
    
    foreach ($recentFiles as $file) {
        $filename = basename($file);
        $webUrl = $webPath . $filename;
        $filesize = round(filesize($file) / 1024, 1);
        echo "<li><a href=\"$webUrl\" download=\"$filename\">$filename</a> ({$filesize} KB)</li>\n";
    }
    
    echo "</ul>\n";
    echo "<p><strong>Note:</strong> These files are ready to use with your existing M365 templates!</p>\n";
}
?>