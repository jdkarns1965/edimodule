<?php
// Secure file download handler
$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    echo "No file specified";
    exit;
}

// Sanitize the filename
$file = basename($file);
$filepath = dirname(__DIR__, 2) . '/data/exports/' . $file;

// Check if file exists and is in the allowed directory
if (!file_exists($filepath)) {
    http_response_code(404);
    echo "File not found";
    exit;
}

// Security check - ensure file is in the exports directory
$realFilePath = realpath($filepath);
$allowedDir = realpath(dirname(__DIR__, 2) . '/data/exports/');

if (strpos($realFilePath, $allowedDir) !== 0) {
    http_response_code(403);
    echo "Access denied";
    exit;
}

// Determine content type
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$contentTypes = [
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'csv' => 'text/csv',
    'pdf' => 'application/pdf'
];

$contentType = $contentTypes[$extension] ?? 'application/octet-stream';

// Send headers
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Output the file
readfile($filepath);

// Clean up temporary file
unlink($filepath);
exit;
?>