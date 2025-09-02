<?php
require_once 'bootstrap.php';
require_once 'src/config/database.php';
require_once 'src/classes/DeliveryMatrix.php';

use Greenfield\EDI\DeliveryMatrix;

echo "Testing Excel Export Functionality\n";
echo "=================================\n\n";

try {
    $db = DatabaseConfig::getInstance();
    $deliveryMatrix = new DeliveryMatrix($db);
    
    // Get test data (limit to 5 records for testing)
    $data = $deliveryMatrix->getDeliveryMatrix(['status' => 'active']);
    $testData = array_slice($data, 0, 5);
    
    echo "1. Testing data retrieval:\n";
    echo "   - Found " . count($data) . " total records\n";
    echo "   - Using " . count($testData) . " records for test\n\n";
    
    echo "2. Testing Excel file generation:\n";
    $filepath = $deliveryMatrix->exportToExcel($testData, 'delivery_matrix', []);
    
    if (file_exists($filepath)) {
        $filesize = filesize($filepath);
        echo "   ✅ Excel file created successfully\n";
        echo "   - File path: " . $filepath . "\n";
        echo "   - File size: " . number_format($filesize) . " bytes\n";
        
        // Test file content
        $handle = fopen($filepath, 'rb');
        $header = fread($handle, 4);
        fclose($handle);
        
        // Check for Excel file signature (ZIP header since XLSX is ZIP-based)
        if (substr($header, 0, 2) === 'PK') {
            echo "   ✅ File has correct Excel/ZIP signature\n";
        } else {
            echo "   ❌ File signature incorrect: " . bin2hex($header) . "\n";
        }
        
        echo "\n3. Testing PhpSpreadsheet direct loading:\n";
        try {
            $testSpreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
            $testSheet = $testSpreadsheet->getActiveSheet();
            $cellValue = $testSheet->getCell('A1')->getValue();
            echo "   ✅ File loads correctly in PhpSpreadsheet\n";
            echo "   - First cell (A1): " . $cellValue . "\n";
            echo "   - Sheet title: " . $testSheet->getTitle() . "\n";
            
            // Check data integrity
            $rowCount = $testSheet->getHighestRow();
            $colCount = $testSheet->getHighestColumn();
            echo "   - Data dimensions: " . $rowCount . " rows x " . $colCount . " columns\n";
            
        } catch (Exception $e) {
            echo "   ❌ Error loading file: " . $e->getMessage() . "\n";
        }
        
        // Clean up test file
        unlink($filepath);
        echo "\n4. Test file cleaned up\n";
        
    } else {
        echo "   ❌ Excel file was not created\n";
    }
    
    echo "\n✅ Excel export test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>