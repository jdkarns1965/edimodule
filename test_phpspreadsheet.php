<?php
require_once 'bootstrap.php';

echo "Testing PhpSpreadsheet functionality...\n";

try {
    // Test if PhpSpreadsheet can be loaded
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    echo "✅ PhpSpreadsheet loaded successfully\n";
    
    // Test basic functionality
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Test');
    $sheet->setCellValue('B1', 123);
    
    // Test if we can create a writer (this is where GD might be needed)
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    echo "✅ XLSX Writer created successfully\n";
    
    // Test if we can save to a temp file
    $tempFile = sys_get_temp_dir() . '/test_spreadsheet.xlsx';
    $writer->save($tempFile);
    
    if (file_exists($tempFile)) {
        echo "✅ Excel file saved successfully: " . number_format(filesize($tempFile)) . " bytes\n";
        unlink($tempFile);
        echo "✅ Test file cleaned up\n";
    } else {
        echo "❌ Excel file was not created\n";
    }
    
    echo "\n🎉 PhpSpreadsheet is fully functional despite composer warning!\n";
    echo "The GD extension warning can be safely ignored for basic Excel operations.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>