<?php
require_once 'src/config/database.php';

try {
    $db = DatabaseConfig::getInstance();
    $conn = $db->getConnection();
    
    echo "Creating database schema manually...\n";
    
    // Enable MySQL error reporting
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Create trading_partners table
    $sql = "CREATE TABLE IF NOT EXISTS trading_partners (
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
    echo " Created trading_partners\n";
    
    // 2. Create ship_to_locations table  
    $sql = "CREATE TABLE IF NOT EXISTS ship_to_locations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        partner_id INT NOT NULL,
        location_description VARCHAR(100) NOT NULL,
        location_code VARCHAR(10) NOT NULL,
        address VARCHAR(200),
        city VARCHAR(100),
        state VARCHAR(50),
        postal_code VARCHAR(20),
        country VARCHAR(50) DEFAULT 'US',
        active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (partner_id) REFERENCES trading_partners(id),
        INDEX idx_location_code (location_code),
        INDEX idx_partner_location (partner_id, location_code)
    )";
    
    $conn->exec($sql);
    echo " Created ship_to_locations\n";
    
    // Insert default data
    $sql = "INSERT IGNORE INTO trading_partners (partner_code, name, edi_id, connection_type, status, contact_email) 
            VALUES ('NIFCO', 'Nifco Inc.', '6148363808', 'SFTP', 'active', 'fieldsc@us.nifco.com')";
    
    $conn->exec($sql);
    
    // Get the Nifco ID
    $stmt = $conn->query("SELECT id FROM trading_partners WHERE partner_code = 'NIFCO'");
    $nifcoId = $stmt->fetchColumn();
    
    if ($nifcoId) {
        // Insert ship-to locations
        $locations = [
            ['SHELBYVILLE KENTUCKY', 'SLB', 'Shelbyville Manufacturing Plant', 'Shelbyville', 'KY'],
            ['Canal Pointe Warehouse', 'CWH', 'Canal Pointe Warehouse', 'Newark', 'OH'],
            ['Canal Pointe Manufacturing', 'CNL', 'Canal Pointe Manufacturing', 'Newark', 'OH']
        ];
        
        foreach ($locations as $loc) {
            $sql = "INSERT IGNORE INTO ship_to_locations (partner_id, location_description, location_code, address, city, state) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nifcoId, $loc[0], $loc[1], $loc[2], $loc[3], $loc[4]]);
        }
        echo " Inserted default data\n";
    }
    
    // Check results
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\nCreated tables:\n";
    foreach ($tables as $table) {
        $stmt = $conn->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
        echo "  - $table ($count records)\n";
    }
    
} catch (Exception $e) {
    echo " Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>