<?php
echo "Manual EDI Database Setup\n";
echo "=========================\n\n";

$host = 'localhost';
$username = 'root';
$password = 'passgas1989';
$database = 'edi_processing';

try {
    $pdo = new PDO("mysql:host=$host", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✓ Connected to MySQL\n";
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $database");
    
    echo "✓ Database created/selected\n";
    
    // Drop existing tables
    $dropTables = [
        "DROP TABLE IF EXISTS shipment_items",
        "DROP TABLE IF EXISTS shipments", 
        "DROP TABLE IF EXISTS delivery_schedules",
        "DROP TABLE IF EXISTS customer_connections",
        "DROP TABLE IF EXISTS customer_edi_configs",
        "DROP TABLE IF EXISTS edi_transactions",
        "DROP TABLE IF EXISTS ship_to_locations",
        "DROP TABLE IF EXISTS trading_partners"
    ];
    
    foreach ($dropTables as $sql) {
        $pdo->exec($sql);
        echo ".";
    }
    echo " Dropped existing tables\n";
    
    // Create trading_partners table
    $pdo->exec("
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
        )
    ");
    echo "✓ Created trading_partners table\n";
    
    // Create ship_to_locations table
    $pdo->exec("
        CREATE TABLE ship_to_locations (
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
        )
    ");
    echo "✓ Created ship_to_locations table\n";
    
    // Create edi_transactions table
    $pdo->exec("
        CREATE TABLE edi_transactions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            partner_id INT NOT NULL,
            transaction_type VARCHAR(3) NOT NULL,
            direction ENUM('inbound','outbound') NOT NULL,
            control_number VARCHAR(20),
            filename VARCHAR(255),
            file_size INT,
            raw_content LONGTEXT,
            parsed_content JSON,
            status ENUM('received','processing','processed','error','archived') DEFAULT 'received',
            error_message TEXT,
            processing_notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL,
            FOREIGN KEY (partner_id) REFERENCES trading_partners(id),
            INDEX idx_partner_type (partner_id, transaction_type),
            INDEX idx_control_number (control_number),
            INDEX idx_status_date (status, created_at),
            INDEX idx_filename (filename)
        )
    ");
    echo "✓ Created edi_transactions table\n";
    
    // Create delivery_schedules table
    $pdo->exec("
        CREATE TABLE delivery_schedules (
            id INT PRIMARY KEY AUTO_INCREMENT,
            partner_id INT NOT NULL,
            po_number VARCHAR(20) NOT NULL,
            release_number VARCHAR(10),
            po_line VARCHAR(30) NOT NULL,
            line_number INT DEFAULT 1,
            supplier_item VARCHAR(50) NOT NULL,
            customer_item VARCHAR(50),
            item_description VARCHAR(200),
            quantity_ordered INT NOT NULL,
            quantity_received INT DEFAULT 0,
            quantity_shipped INT DEFAULT 0,
            promised_date DATE NOT NULL,
            need_by_date DATE NOT NULL,
            ship_to_location_id INT,
            ship_to_description VARCHAR(100),
            uom VARCHAR(10) DEFAULT 'EACH',
            unit_price DECIMAL(10,4),
            organization VARCHAR(50) DEFAULT 'NIFCO',
            supplier VARCHAR(100) DEFAULT 'GREENFIELD PRECISION PLASTICS LLC',
            status ENUM('active','shipped','received','cancelled','closed') DEFAULT 'active',
            priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
            notes TEXT,
            edi_transaction_id INT NULL,
            erp_po_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (partner_id) REFERENCES trading_partners(id),
            FOREIGN KEY (ship_to_location_id) REFERENCES ship_to_locations(id),
            FOREIGN KEY (edi_transaction_id) REFERENCES edi_transactions(id),
            INDEX idx_po_release (po_number, release_number),
            INDEX idx_promised_date (promised_date),
            INDEX idx_supplier_item (supplier_item),
            INDEX idx_status_date (status, promised_date),
            INDEX idx_ship_to (ship_to_location_id),
            INDEX idx_erp_po (erp_po_id)
        )
    ");
    echo "✓ Created delivery_schedules table\n";
    
    // Insert Nifco partner data
    $pdo->exec("
        INSERT INTO trading_partners (partner_code, name, edi_id, connection_type, status, contact_email) 
        VALUES ('NIFCO', 'Nifco Inc.', '6148363808', 'SFTP', 'active', 'fieldsc@us.nifco.com')
    ");
    $nifco_id = $pdo->lastInsertId();
    echo "✓ Created Nifco trading partner (ID: $nifco_id)\n";
    
    // Insert ship-to locations
    $locations = [
        ['SHELBYVILLE KENTUCKY', 'SLB', 'Shelbyville Manufacturing Plant', 'Shelbyville', 'KY'],
        ['Canal Pointe Warehouse', 'CWH', 'Canal Pointe Warehouse', 'Newark', 'OH'],
        ['Canal Pointe Manufacturing', 'CNL', 'Canal Pointe Manufacturing', 'Newark', 'OH'],
        ['Lavern Manufacturing', 'LVG', 'Lavern Manufacturing Plant', 'LaVergne', 'TN'],
        ['Groveport Warehouse', 'GWH', 'Groveport Warehouse', 'Groveport', 'OH']
    ];
    
    foreach ($locations as $loc) {
        $pdo->prepare("
            INSERT INTO ship_to_locations (partner_id, location_description, location_code, address, city, state) 
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$nifco_id, $loc[0], $loc[1], $loc[2], $loc[3], $loc[4]]);
        echo ".";
    }
    echo " Created " . count($locations) . " ship-to locations\n";
    
    // Verify setup
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Tables: " . implode(', ', $tables) . "\n";
    
    $partnerCount = $pdo->query("SELECT COUNT(*) FROM trading_partners")->fetchColumn();
    $locationCount = $pdo->query("SELECT COUNT(*) FROM ship_to_locations")->fetchColumn();
    
    echo "✓ $partnerCount trading partners configured\n";
    echo "✓ $locationCount ship-to locations configured\n";
    
    echo "\n🎉 Database setup completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Set up web server to serve from src/public/\n";
    echo "2. Access the web interface\n";
    echo "3. Import sample TSV data\n";
    
} catch (Exception $e) {
    echo "\n❌ Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>