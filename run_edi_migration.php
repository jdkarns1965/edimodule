<?php
require_once 'src/config/database.php';

echo "EDI Configuration Migration - Adding multi-customer support\n";
echo "===========================================================\n\n";

try {
    $conn = DatabaseConfig::getInstance()->getConnection();
    
    // Read the migration SQL
    $sqlFile = __DIR__ . '/sql/add_edi_configuration_fields.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    if ($sql === false) {
        throw new Exception("Could not read migration file");
    }
    
    echo " Migration file loaded\n";
    
    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    echo " Found " . count($statements) . " SQL statements\n\n";
    
    $successCount = 0;
    $conn->beginTransaction();
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty lines and comments
        if (empty($statement) || substr($statement, 0, 2) === '--') {
            continue;
        }
        
        try {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            $conn->exec($statement);
            $successCount++;
            echo "   Success\n";
            
        } catch (Exception $e) {
            // Check if error is just "column already exists" which is OK
            if (strpos($e->getMessage(), 'Duplicate column name') !== false ||
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "    Column already exists (skipping)\n";
                continue;
            }
            
            echo "  L Error: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    $conn->commit();
    
    echo "\n Migration completed successfully!\n";
    echo "   Executed $successCount statements\n\n";
    
    // Verify the results
    echo "Verifying trading partners configuration:\n";
    echo "-----------------------------------------\n";
    
    $stmt = $conn->query("
        SELECT 
            partner_code,
            name,
            edi_standard,
            edi_version,
            date_format,
            po_number_format,
            default_organization,
            JSON_EXTRACT(field_mappings, '$.po_number_field') as po_field,
            JSON_EXTRACT(business_rules, '$.po_parsing_rule') as po_rule,
            JSON_EXTRACT(communication_config, '$.protocol') as protocol
        FROM trading_partners 
        ORDER BY partner_code
    ");
    
    $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($partners as $partner) {
        echo "Partner: {$partner['partner_code']} ({$partner['name']})\n";
        echo "  EDI Standard: {$partner['edi_standard']} v{$partner['edi_version']}\n";
        echo "  Date Format: {$partner['date_format']}\n";
        echo "  PO Format: {$partner['po_number_format']}\n";
        echo "  Organization: {$partner['default_organization']}\n";
        echo "  Protocol: {$partner['protocol']}\n";
        echo "  PO Field: {$partner['po_field']}\n";
        echo "  PO Rule: {$partner['po_rule']}\n";
        echo "\n";
    }
    
    echo "<‰ Multi-customer EDI configuration is now active!\n";
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo "\nL Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>