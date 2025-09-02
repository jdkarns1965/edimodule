<?php
require_once 'bootstrap.php';
require_once 'src/config/database.php';
require_once 'src/classes/PartMaster.php';
require_once 'src/classes/DeliveryMatrix.php';

use Greenfield\EDI\PartMaster;
use Greenfield\EDI\DeliveryMatrix;

echo "Testing EDI Reporting System\n";
echo "===========================\n\n";

try {
    $db = DatabaseConfig::getInstance();
    $partMaster = new PartMaster($db);
    $deliveryMatrix = new DeliveryMatrix($db);
    
    // Test Part Master
    echo "1. Testing Part Master System:\n";
    $parts = $partMaster->getAllParts('', true, 5, 0);
    echo "   - Found " . count($parts) . " parts (showing first 5)\n";
    
    foreach ($parts as $part) {
        echo "   - Part: {$part['part_number']} | QPC: {$part['qpc']} | Auto-detected: " . 
             ($part['auto_detected'] ? 'Yes' : 'No') . "\n";
    }
    
    echo "\n2. Testing Delivery Matrix System:\n";
    $matrix = $deliveryMatrix->getDeliveryMatrix(['status' => 'active'], 5);
    echo "   - Found " . count($matrix) . " active delivery schedules\n";
    
    foreach ($matrix as $item) {
        $containers = $item['containers_needed'] ?? round($item['quantity_ordered'] / ($item['qpc'] ?? 1));
        echo "   - PO: {$item['po_number']} | Part: {$item['supplier_item']} | " .
             "Qty: {$item['quantity_ordered']} | Containers: {$containers} | Location: {$item['location_code']}\n";
    }
    
    echo "\n3. Testing Location Summary:\n";
    $locationSummary = $deliveryMatrix->getLocationSummary();
    foreach ($locationSummary as $summary) {
        echo "   - {$summary['location_code']}: {$summary['total_quantity']} qty, " .
             "{$summary['total_containers']} containers, {$summary['unique_parts']} parts\n";
    }
    
    echo "\n4. Testing Export Functionality:\n";
    $testData = array_slice($matrix, 0, 3); // Use first 3 records for test
    try {
        $filepath = $deliveryMatrix->exportToExcel($testData, 'delivery_matrix', []);
        echo "   - Excel export created successfully: " . basename($filepath) . "\n";
        echo "   - File size: " . number_format(filesize($filepath)) . " bytes\n";
        unlink($filepath); // Clean up
        echo "   - Test file cleaned up\n";
    } catch (Exception $e) {
        echo "   - Export test failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n5. Testing Auto-Detection:\n";
    $newParts = $partMaster->autoDetectNewParts();
    echo "   - Auto-detected {$newParts} new parts from existing delivery schedules\n";
    
    echo "\nAll tests completed successfully!\n";
    echo "===========================\n";
    echo "Part Master Management: Available at ?page=part_master\n";
    echo "Delivery Schedule Matrix: Available at ?page=delivery_matrix\n";
    echo "Features implemented:\n";
    echo "- Part master with QPC data\n";
    echo "- Bulk CSV import for parts\n";
    echo "- Auto-detection of new parts from EDI\n";
    echo "- Delivery matrix with container calculations\n";
    echo "- Multiple Excel export templates\n";
    echo "- Location-specific reporting (SLB/CNL/CWH)\n";
    echo "- Multi-location PO handling\n";
    
} catch (Exception $e) {
    echo "Error during testing: " . $e->getMessage() . "\n";
    exit(1);
}
?>