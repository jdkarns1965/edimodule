<?php
require_once 'src/config/database.php';

try {
    $db = DatabaseConfig::getInstance();
    $conn = $db->getConnection();
    
    echo "Setting up complete database schema...\n";
    
    // Read and execute the complete SQL file
    $sql = file_get_contents('database_schema.sql');
    
    // Execute the SQL file line by line to handle MySQL quirks
    $tempFile = tempnam(sys_get_temp_dir(), 'edi_schema_');
    file_put_contents($tempFile, $sql);
    
    $command = sprintf(
        'mysql -u %s -p%s %s < %s 2>&1',
        'root',
        'passgas1989', 
        'edi_processing',
        $tempFile
    );
    
    $output = shell_exec($command);
    unlink($tempFile);
    
    if ($output) {
        echo "Import output: $output\n";
    }
    
    // Verify tables were created
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Database tables after import:\n";
    foreach ($tables as $table) {
        $stmt = $conn->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
        echo "  - $table ($count records)\n";
    }
    
    // Also import part master if main schema worked
    if (count($tables) > 0) {
        echo "\nImporting part_master schema...\n";
        
        $partSql = file_get_contents('sql/part_master_table.sql');
        $partTempFile = tempnam(sys_get_temp_dir(), 'edi_part_');
        file_put_contents($partTempFile, $partSql);
        
        $partCommand = sprintf(
            'mysql -u %s -p%s %s < %s 2>&1',
            'root',
            'passgas1989',
            'edi_processing', 
            $partTempFile
        );
        
        $partOutput = shell_exec($partCommand);
        unlink($partTempFile);
        
        if ($partOutput) {
            echo "Part schema output: $partOutput\n";
        }
        
        // Final verification
        $stmt = $conn->query("SHOW TABLES");
        $finalTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "\nFinal database state:\n";
        foreach ($finalTables as $table) {
            $stmt = $conn->query("SELECT COUNT(*) FROM `$table`");
            $count = $stmt->fetchColumn();
            echo "  - $table ($count records)\n";
        }
        
        echo "\n Database migration completed successfully!\n";
        echo "Total tables created: " . count($finalTables) . "\n";
    } else {
        echo " Schema import failed - no tables created\n";
    }
    
} catch (Exception $e) {
    echo " Error: " . $e->getMessage() . "\n";
}
?>