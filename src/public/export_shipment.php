<?php
// Dedicated shipment export handler
require_once '../../bootstrap.php';
require_once '../classes/ExcelExportService.php';

// Check if this is an export request
if (!isset($_GET['export']) || !isset($_GET['shipment_id'])) {
    http_response_code(400);
    echo "Invalid export request";
    exit;
}

try {
    $exportService = new ExcelExportService();
    
    $shipmentId = $_GET['shipment_id'];
    $templateType = $_GET['template'] ?? 'packing_list';
    $format = $_GET['format'] ?? 'xlsx';
    
    $result = $exportService->exportShipmentForExcel($shipmentId, $templateType, $format);
    
    if (!$result['success']) {
        http_response_code(500);
        echo 'Export failed: ' . ($result['error'] ?? 'Unknown error');
        exit;
    }
    
    // Extract filename from download URL
    preg_match('/file=([^&]+)/', $result['download_url'], $matches);
    $filename = $matches[1];
    $filepath = $result['filepath'];
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo "Export file not found";
        exit;
    }
    
    // Determine content type based on format
    $contentTypes = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'csv' => 'text/csv',
        'pdf' => 'application/pdf'
    ];
    $contentType = $contentTypes[$format] ?? 'application/octet-stream';
    
    // Set download headers
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    // Output the file
    readfile($filepath);
    
    // Clean up temporary file
    unlink($filepath);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Export failed: ' . $e->getMessage();
    error_log('Shipment export error: ' . $e->getMessage());
}
?>