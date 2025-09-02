<?php
require_once 'src/classes/TSVImporter.php';

echo "Testing TSV Import with Real Database\n";
echo "====================================\n\n";

try {
    $importer = new TSVImporter();
    $sampleFile = __DIR__ . '/sample_data.tsv';
    
    if (!file_exists($sampleFile)) {
        throw new Exception("Sample file not found: $sampleFile");
    }
    
    echo "Starting import of sample_data.tsv...\n";
    $stats = $importer->importFromFile($sampleFile);
    
    echo "\n✅ Import Results:\n";
    echo "- Processed: " . $stats['processed'] . " records\n";
    echo "- Inserted: " . $stats['inserted'] . " new records\n";
    echo "- Updated: " . $stats['updated'] . " existing records\n"; 
    echo "- Skipped: " . $stats['skipped'] . " records\n";
    echo "- Duration: " . $stats['duration'] . " seconds\n";
    
    if (!empty($stats['errors'])) {
        echo "\n⚠️  Errors encountered:\n";
        foreach ($stats['errors'] as $error) {
            echo "- $error\n";
        }
    }
    
    // Query results
    $db = DatabaseConfig::getInstance()->getConnection();
    $totalSchedules = $db->query("SELECT COUNT(*) as count FROM delivery_schedules")->fetch()['count'];
    $activeSchedules = $db->query("SELECT COUNT(*) as count FROM delivery_schedules WHERE status = 'active'")->fetch()['count'];
    
    echo "\n📊 Database Status:\n";
    echo "- Total delivery schedules: $totalSchedules\n";
    echo "- Active schedules: $activeSchedules\n";
    
    // Show sample records
    $sampleRecords = $db->query("
        SELECT ds.po_line, ds.supplier_item, ds.quantity_ordered, ds.promised_date, sl.location_code
        FROM delivery_schedules ds
        LEFT JOIN ship_to_locations sl ON ds.ship_to_location_id = sl.id
        ORDER BY ds.created_at DESC
        LIMIT 5
    ")->fetchAll();
    
    echo "\n📋 Sample Records:\n";
    foreach ($sampleRecords as $record) {
        echo "- PO: {$record['po_line']}, Item: {$record['supplier_item']}, Qty: {$record['quantity_ordered']}, Date: {$record['promised_date']}, Location: {$record['location_code']}\n";
    }
    
    echo "\n🎉 TSV import test completed successfully!\n";
    echo "The web interface is ready to use.\n";
    
} catch (Exception $e) {
    echo "\n❌ Import test failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>