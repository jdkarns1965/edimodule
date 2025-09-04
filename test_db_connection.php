<?php
require_once 'src/config/database.php';

try {
    $db = DatabaseConfig::getInstance();
    
    echo "Testing database connection...\n";
    
    if ($db->testConnection()) {
        echo " Database connection successful!\n";
        
        // Test creating a simple table
        $conn = $db->getConnection();
        
        // Drop existing tables first
        $dropTables = [
            'shipment_items',
            'shipments', 
            'delivery_schedules',
            'customer_connections',
            'customer_edi_configs',
            'edi_transactions',
            'ship_to_locations',
            'trading_partners',
            'part_master',
            'part_location_mapping'
        ];
        
        foreach ($dropTables as $table) {
            $conn->exec("DROP TABLE IF EXISTS `$table`");
        }
        echo " Dropped existing tables\n";
        
        // Create trading_partners table
        $sql = "
        CREATE TABLE trading_partners (
            id INT PRIMARY KEY AUTO_INCREMENT,
            partner_code VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            edi_id VARCHAR(15) NOT NULL,
            connection_type ENUM('AS2','SFTP','FTP','VAN','API') DEFAULT 'SFTP',
            status ENUM('active','inactive','testing') DEFAULT 'testing',
            contact_email VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_partner_code (partner_code),
            INDEX idx_edi_id (edi_id)
        )";
        
        $conn->exec($sql);
        echo " Created trading_partners table\n";
        
        // Check if table exists
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Current tables: " . implode(', ', $tables) . "\n";
        
    } else {
        echo " Database connection failed!\n";
    }
    
} catch (Exception $e) {
    echo " Error: " . $e->getMessage() . "\n";
}
?>