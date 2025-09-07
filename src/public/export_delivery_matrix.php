<?php
// Dedicated delivery matrix export handler
require_once '../../bootstrap.php';
require_once '../classes/DeliveryMatrix.php';
require_once '../config/database.php';

use Greenfield\EDI\DeliveryMatrix;

// Check if this is an export request
if (!isset($_GET['export']) || $_GET['export'] !== 'delivery_matrix') {
    http_response_code(400);
    echo "Invalid export request";
    exit;
}

try {
    $db = DatabaseConfig::getInstance();
    $deliveryMatrix = new DeliveryMatrix($db);
    
    // Get filters from GET parameters
    $filters = [
        'location_code' => $_GET['location_code'] ?? '',
        'part_number' => $_GET['part_number'] ?? '',
        'po_number' => $_GET['po_number'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'status' => $_GET['status'] ?? '',
        'product_family' => $_GET['product_family'] ?? ''
    ];
    
    $data = $deliveryMatrix->getDeliveryMatrix($filters);
    $templateType = $_GET['export_template'] ?? 'delivery_matrix';
    
    // Get the download URL and extract filename
    $downloadUrl = $deliveryMatrix->exportToExcel($data, $templateType, $filters);
    preg_match('/file=([^&]+)/', $downloadUrl, $matches);
    $filename = $matches[1];
    $filepath = dirname(__DIR__, 2) . '/data/exports/' . $filename;
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo "Export file not found";
        exit;
    }
    
    // Set download headers
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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
    error_log('Delivery matrix export error: ' . $e->getMessage());
}
?>