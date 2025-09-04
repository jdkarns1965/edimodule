<?php
require_once 'src/config/database.php';

try {
    $db = DatabaseConfig::getInstance();
    $conn = $db->getConnection();
    
    echo "Importing database schema...\n";
    
    // Read the SQL file
    $sql = file_get_contents('database_schema.sql');
    if ($sql === false) {
        throw new Exception("Could not read database_schema.sql");
    }
    
    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || substr(trim($statement), 0, 2) === '--') {
            continue; // Skip empty lines and comments
        }
        
        try {
            $conn->exec($statement);
            $successCount++;
        } catch (PDOException $e) {
            echo "Error executing: " . substr($statement, 0, 50) . "...\n";
            echo "Error: " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }
    
    echo " Executed $successCount statements successfully\n";
    if ($errorCount > 0) {
        echo " $errorCount statements failed\n";
    }
    
    // Verify tables were created
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\nCreated tables:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    
    // Import part_master table
    echo "\nImporting part_master schema...\n";
    
    $partSql = file_get_contents('sql/part_master_table.sql');
    if ($partSql !== false) {
        $partStatements = array_filter(array_map('trim', explode(';', $partSql)));
        
        $partSuccessCount = 0;
        foreach ($partStatements as $statement) {
            if (empty($statement) || substr(trim($statement), 0, 2) === '--') {
                continue;
            }
            
            try {
                $conn->exec($statement);
                $partSuccessCount++;
            } catch (PDOException $e) {
                echo "Part schema error: " . $e->getMessage() . "\n";
            }
        }
        echo " Executed $partSuccessCount part_master statements\n";
    }
    
    // Final table count
    $stmt = $conn->query("SHOW TABLES");
    $finalTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "\nFinal table count: " . count($finalTables) . "\n";
    
} catch (Exception $e) {
    echo " Error: " . $e->getMessage() . "\n";
}
?>