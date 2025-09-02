<?php
require_once 'bootstrap.php';
require_once 'src/config/database.php';
require_once 'src/classes/DeliveryMatrix.php';

use Greenfield\EDI\DeliveryMatrix;

echo "Testing All Export Templates\n";
echo "===========================\n\n";

try {
    $db = DatabaseConfig::getInstance();
    $deliveryMatrix = new DeliveryMatrix($db);
    
    // Get test data
    $data = $deliveryMatrix->getDeliveryMatrix(['status' => 'active']);
    $testData = array_slice($data, 0, 10); // Limit for testing
    
    echo "Testing with " . count($testData) . " records\n\n";
    
    $templates = [
        'delivery_matrix' => 'Complete Delivery Matrix',
        'daily_production' => 'Daily Production Plan',
        'weekly_planning' => 'Weekly Planning Report', 
        'location_specific' => 'Location-Specific Report',
        'po_specific' => 'PO-Specific Report'
    ];
    
    $filters = ['location_code' => 'SLB', 'po_number' => '1067045'];
    
    foreach ($templates as $templateType => $templateName) {
        echo "Testing: {$templateName} ({$templateType})\n";
        
        try {
            $filepath = $deliveryMatrix->exportToExcel($testData, $templateType, $filters);
            
            if (file_exists($filepath)) {
                $filesize = filesize($filepath);
                echo "   ✅ Export successful\n";
                echo "   - File size: " . number_format($filesize) . " bytes\n";
                
                // Test if file opens
                try {
                    $testSpreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
                    $sheet = $testSpreadsheet->getActiveSheet();
                    echo "   - Sheet title: '" . $sheet->getTitle() . "'\n";
                    echo "   - Dimensions: " . $sheet->getHighestRow() . " rows x " . $sheet->getHighestColumn() . " columns\n";
                } catch (Exception $e) {
                    echo "   ❌ Error opening file: " . $e->getMessage() . "\n";
                }
                
                unlink($filepath);
            } else {
                echo "   ❌ Export file not created\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ Export failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    echo "🎉 All export template tests completed!\n";
    
} catch (Exception $e) {
    echo "❌ Test setup failed: " . $e->getMessage() . "\n";
}
?>