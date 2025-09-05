<?php
// Simple Excel test to isolate the issue
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

echo "Creating simple Excel file...\n";

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Simple data
    $sheet->setCellValue('A1', 'Name');
    $sheet->setCellValue('B1', 'Email');
    $sheet->setCellValue('A2', 'Test Customer');
    $sheet->setCellValue('B2', 'test@example.com');
    
    $writer = new Xlsx($spreadsheet);
    $filepath = '/tmp/simple_test.xlsx';
    $writer->save($filepath);
    
    echo "File created: $filepath\n";
    echo "File size: " . filesize($filepath) . " bytes\n";
    echo "File type: " . mime_content_type($filepath) . "\n";
    
    // Check if file is valid
    if (file_exists($filepath) && filesize($filepath) > 0) {
        echo "SUCCESS: File exists and has content\n";
    } else {
        echo "ERROR: File is empty or doesn't exist\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>