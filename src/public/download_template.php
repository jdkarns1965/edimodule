<?php
// Simple template download handler
$templateFile = dirname(dirname(__DIR__)) . '/data/templates/delivery_schedule_import_template.csv';

if (file_exists($templateFile)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="delivery_schedule_template.csv"');
    header('Content-Length: ' . filesize($templateFile));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    readfile($templateFile);
    exit;
} else {
    http_response_code(404);
    echo "Template file not found.";
}
?>