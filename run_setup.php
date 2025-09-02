<?php
echo "EDI Database Setup\n";
echo "==================\n\n";

$host = 'localhost';
$username = 'root';
$password = 'passgas1989';
$database = 'edi_processing';

try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=$host", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✓ Connected to MySQL\n";
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $database");
    
    echo "✓ Database '$database' created/selected\n";
    
    // Read and execute schema file
    $schemaFile = __DIR__ . '/database_schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found");
    }
    
    $sql = file_get_contents($schemaFile);
    
    // Split into individual statements and execute
    $statements = explode(';', $sql);
    $executed = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
            try {
                $pdo->exec($statement);
                $executed++;
                echo ".";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "\nError executing statement: " . $e->getMessage() . "\n";
                    echo "Statement: " . substr($statement, 0, 100) . "...\n";
                }
            }
        }
    }
    
    echo "\n✓ Executed $executed SQL statements\n";
    
    // Verify tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Tables created: " . implode(', ', $tables) . "\n";
    
    // Check for Nifco data
    $nifcoCount = $pdo->query("SELECT COUNT(*) FROM trading_partners WHERE partner_code = 'NIFCO'")->fetchColumn();
    if ($nifcoCount > 0) {
        echo "✓ Nifco trading partner configured\n";
    } else {
        echo "⚠ Nifco trading partner not found\n";
    }
    
    // Check ship-to locations
    $locationCount = $pdo->query("SELECT COUNT(*) FROM ship_to_locations")->fetchColumn();
    echo "✓ $locationCount ship-to locations configured\n";
    
    echo "\n🎉 Database setup completed successfully!\n";
    echo "\nYou can now:\n";
    echo "1. Access the web interface at /src/public/\n";
    echo "2. Import the sample TSV data\n";
    echo "3. View delivery schedules and transactions\n";
    
} catch (Exception $e) {
    echo "\n❌ Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>