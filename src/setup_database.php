<?php
require_once 'config/database.php';
require_once 'config/app.php';

class DatabaseSetup {
    
    private $pdo;
    
    public function __construct() {
        try {
            $dsn = "mysql:host=localhost;charset=utf8mb4";
            $this->pdo = new PDO($dsn, 'root', 'passgas1989', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public function setup() {
        echo "Starting EDI Processing Database Setup...\n";
        
        $this->createDatabase();
        $this->createTables();
        
        echo "Database setup completed successfully!\n";
    }
    
    private function createDatabase() {
        echo "Creating database 'edi_processing'...\n";
        
        $this->pdo->exec("CREATE DATABASE IF NOT EXISTS edi_processing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->pdo->exec("USE edi_processing");
        
        echo "Database created/selected.\n";
    }
    
    private function createTables() {
        echo "Creating tables...\n";
        
        $schemaFile = dirname(__DIR__) . '/database_schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found: $schemaFile");
        }
        
        $schema = file_get_contents($schemaFile);
        
        $statements = array_filter(
            array_map('trim', explode(';', $schema)),
            function($statement) {
                return !empty($statement) && 
                       !preg_match('/^\s*--/', $statement) && 
                       !preg_match('/^\s*\/\*/', $statement);
            }
        );
        
        foreach ($statements as $statement) {
            if (trim($statement)) {
                try {
                    $this->pdo->exec($statement);
                    echo ".";
                } catch (PDOException $e) {
                    echo "\nError executing statement: " . $e->getMessage() . "\n";
                    echo "Statement: " . substr($statement, 0, 100) . "...\n";
                    throw $e;
                }
            }
        }
        
        echo "\nTables created successfully.\n";
        $this->verifyTables();
    }
    
    private function verifyTables() {
        echo "Verifying table structure...\n";
        
        $expectedTables = [
            'trading_partners',
            'ship_to_locations', 
            'edi_transactions',
            'delivery_schedules',
            'shipments',
            'shipment_items',
            'customer_edi_configs',
            'customer_connections'
        ];
        
        $stmt = $this->pdo->query("SHOW TABLES");
        $actualTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($expectedTables as $table) {
            if (in_array($table, $actualTables)) {
                echo "✓ Table '$table' exists\n";
            } else {
                echo "✗ Table '$table' missing\n";
            }
        }
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM trading_partners WHERE partner_code = 'NIFCO'");
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            echo "✓ Nifco trading partner data loaded\n";
        } else {
            echo "✗ No Nifco trading partner found\n";
        }
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM ship_to_locations");
        $result = $stmt->fetch();
        
        echo "✓ Found {$result['count']} ship-to locations\n";
    }
}

if (php_sapi_name() === 'cli') {
    try {
        $setup = new DatabaseSetup();
        $setup->setup();
    } catch (Exception $e) {
        echo "Setup failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>