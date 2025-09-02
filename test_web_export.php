<?php
require_once 'bootstrap.php';
require_once 'src/config/database.php';
require_once 'src/classes/DeliveryMatrix.php';

use Greenfield\EDI\DeliveryMatrix;

// Simulate POST request to test web export functionality
$_POST = [
    'action' => 'export',
    'export_template' => 'delivery_matrix',
    'location_code' => '',
    'part_number' => '',
    'po_number' => '',
    'date_from' => '',
    'date_to' => '',
    'status' => 'active',
    'product_family' => ''
];

try {

    $db = DatabaseConfig::getInstance();
    $deliveryMatrix = new DeliveryMatrix($db);

    echo "Testing Web Export Functionality\n";
    echo "===============================\n\n";

    // Simulate the export process from delivery_matrix.php
    $filters = [
        'location_code' => $_POST['location_code'] ?? '',
        'part_number' => $_POST['part_number'] ?? '',
        'po_number' => $_POST['po_number'] ?? '',
        'date_from' => $_POST['date_from'] ?? '',
        'date_to' => $_POST['date_to'] ?? '',
        'status' => $_POST['status'] ?? '',
        'product_family' => $_POST['product_family'] ?? ''
    ];

    $data = $deliveryMatrix->getDeliveryMatrix($filters);
    $templateType = $_POST['export_template'] ?? 'delivery_matrix';

    echo "1. Data retrieval:\n";
    echo "   - Found " . count($data) . " records matching filters\n";
    echo "   - Template type: " . $templateType . "\n\n";

    echo "2. Export generation:\n";
    $filepath = $deliveryMatrix->exportToExcel($data, $templateType, $filters);

    // Verify file was created and is readable
    if (!file_exists($filepath) || !is_readable($filepath)) {
        throw new Exception('Export file was not created or is not readable');
    }

    $filesize = filesize($filepath);
    if ($filesize === false || $filesize === 0) {
        throw new Exception('Export file is empty or corrupted');
    }

    echo "   ✅ Export file created successfully\n";
    echo "   - File path: " . $filepath . "\n";
    echo "   - File size: " . number_format($filesize) . " bytes\n";

    // Test file integrity
    $handle = fopen($filepath, 'rb');
    $header = fread($handle, 4);
    fclose($handle);

    if (substr($header, 0, 2) === 'PK') {
        echo "   ✅ File has correct Excel signature\n";
    } else {
        throw new Exception('File has incorrect signature');
    }

    // Test if file can be opened by PhpSpreadsheet
    try {
        $testSpreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
        $properties = $testSpreadsheet->getProperties();
        
        echo "\n3. File validation:\n";
        echo "   ✅ File opens correctly in PhpSpreadsheet\n";
        echo "   - Title: " . $properties->getTitle() . "\n";
        echo "   - Creator: " . $properties->getCreator() . "\n";
        echo "   - Description: " . $properties->getDescription() . "\n";
        
        $sheet = $testSpreadsheet->getActiveSheet();
        echo "   - Sheet title: " . $sheet->getTitle() . "\n";
        echo "   - Dimensions: " . $sheet->getHighestRow() . " rows x " . $sheet->getHighestColumn() . " columns\n";
        
        // Check some data
        $headerRow = $sheet->rangeToArray('A1:O1')[0];
        echo "   - Headers: " . implode(', ', array_slice($headerRow, 0, 5)) . "...\n";
        
    } catch (Exception $e) {
        throw new Exception('File validation failed: ' . $e->getMessage());
    }

    // Simulate download headers (but don't actually send them)
    echo "\n4. Download headers simulation:\n";
    echo "   - Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\n";
    echo "   - Content-Disposition: attachment; filename=\"" . basename($filepath) . "\"\n";
    echo "   - Content-Length: " . $filesize . "\n";
    echo "   - Cache-Control: no-cache, must-revalidate\n";

    // Clean up
    unlink($filepath);
    echo "\n5. Test file cleaned up\n";
    
    echo "\n🎉 Web export test completed successfully!\n";
    echo "The Excel export should now work correctly in the web interface.\n";

} catch (Exception $e) {
    echo "❌ Web export test failed: " . $e->getMessage() . "\n";
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }
}

// Clear output buffer
ob_end_clean();
?>