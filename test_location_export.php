<?php
require_once 'bootstrap.php';
require_once 'src/config/database.php';
require_once 'src/classes/DeliveryMatrix.php';

use Greenfield\EDI\DeliveryMatrix;

echo "Testing Location-Specific Export Fix\n";
echo "===================================\n\n";

try {
    $db = DatabaseConfig::getInstance();
    $deliveryMatrix = new DeliveryMatrix($db);
    
    // Test with SLB location (which should have data)
    $filters = ['location_code' => 'SLB'];
    $data = $deliveryMatrix->getDeliveryMatrix($filters);
    
    echo "1. Testing SLB location export:\n";
    echo "   - Found " . count($data) . " records for SLB\n";
    
    if (count($data) > 0) {
        // Test the export
        $filepath = $deliveryMatrix->exportToExcel($data, 'location_specific', $filters);
        
        if (file_exists($filepath)) {
            $filesize = filesize($filepath);
            echo "   ✅ Location-specific export created successfully\n";
            echo "   - File path: " . $filepath . "\n";
            echo "   - File size: " . number_format($filesize) . " bytes\n";
            
            // Test if file can be opened by PhpSpreadsheet
            try {
                $testSpreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
                $sheet = $testSpreadsheet->getActiveSheet();
                
                echo "   ✅ File opens correctly\n";
                echo "   - Sheet title: '" . $sheet->getTitle() . "'\n";
                echo "   - Dimensions: " . $sheet->getHighestRow() . " rows x " . $sheet->getHighestColumn() . " columns\n";
                
                // Test some data
                $firstHeader = $sheet->getCell('A1')->getValue();
                $firstData = $sheet->getCell('A2')->getValue();
                echo "   - First header (A1): " . $firstHeader . "\n";
                echo "   - First data (A2): " . $firstData . "\n";
                
            } catch (Exception $e) {
                echo "   ❌ Error opening file: " . $e->getMessage() . "\n";
            }
            
            // Clean up
            unlink($filepath);
            echo "   - Test file cleaned up\n";
            
        } else {
            echo "   ❌ Export file was not created\n";
        }
    } else {
        echo "   ⚠️  No data found for SLB location\n";
    }
    
    echo "\n2. Testing sheet title sanitization:\n";
    
    // Test various problematic titles
    $testTitles = [
        'Location: SLB',
        'Location*Test',
        'Location/Test',
        'Location\\Test',
        'Location?Test',
        'Location[Test]',
        'This is a very long location title that exceeds thirty one characters'
    ];
    
    $deliveryMatrixReflection = new ReflectionClass($deliveryMatrix);
    $sanitizeMethod = $deliveryMatrixReflection->getMethod('sanitizeSheetTitle');
    $sanitizeMethod->setAccessible(true);
    
    foreach ($testTitles as $title) {
        $sanitized = $sanitizeMethod->invoke($deliveryMatrix, $title);
        echo "   - Input: '{$title}'\n";
        echo "     Output: '{$sanitized}' (length: " . strlen($sanitized) . ")\n";
    }
    
    echo "\n✅ Location-specific export test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>