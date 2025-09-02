<?php
require_once 'src/config/app.php';

echo "EDI Processing Application - Test Import\n";
echo "==========================================\n\n";

$sampleFile = __DIR__ . '/sample_data.tsv';

if (!file_exists($sampleFile)) {
    echo "ERROR: Sample data file not found at: $sampleFile\n";
    exit(1);
}

echo "✓ Sample data file found\n";

$handle = fopen($sampleFile, 'r');
if (!$handle) {
    echo "ERROR: Cannot open sample data file\n";
    exit(1);
}

$headers = fgetcsv($handle, 0, "\t");
echo "✓ Headers read successfully:\n";
foreach ($headers as $i => $header) {
    echo "  [$i] " . trim($header) . "\n";
}

$requiredHeaders = [
    'PO Number',
    'Supplier Item', 
    'Item Description',
    'Quantity Ordered',
    'Promised Date',
    'Ship-To Location'
];

echo "\nValidating headers:\n";
foreach ($requiredHeaders as $required) {
    $found = in_array($required, $headers);
    echo ($found ? "✓" : "✗") . " $required" . ($found ? "" : " (MISSING)") . "\n";
}

echo "\nReading sample rows:\n";
$rowCount = 0;
while (($row = fgetcsv($handle, 0, "\t")) !== FALSE && $rowCount < 5) {
    $rowCount++;
    echo "Row $rowCount:\n";
    if (count($row) === count($headers)) {
        $data = array_combine($headers, $row);
        echo "  PO Number: " . $data['PO Number'] . "\n";
        echo "  Supplier Item: " . $data['Supplier Item'] . "\n";
        echo "  Quantity Ordered: " . $data['Quantity Ordered'] . "\n";
        echo "  Promised Date: " . $data['Promised Date'] . "\n";
        echo "  Ship-To Location: " . $data['Ship-To Location'] . "\n";
    } else {
        echo "  ERROR: Column count mismatch\n";
    }
    echo "\n";
}

fclose($handle);

echo "Test completed successfully!\n";
echo "Application structure is ready for database setup and web interface testing.\n";
?>