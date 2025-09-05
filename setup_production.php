<?php
/**
 * Production Setup Script
 * Run this after deployment to configure the database and environment
 */

echo "=== EDI Module Production Setup ===\n";

// Check if .env file exists
if (!file_exists('.env')) {
    echo "L .env file not found. Please copy .env.production to .env and configure it.\n";
    echo "   Then re-run this script.\n";
    exit(1);
}

// Load environment variables
$envLines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($envLines as $line) {
    if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value, '"\'');
    }
}

// Test database connection
echo "= Testing database connection...\n";
try {
    $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4";
    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo " Database connection successful!\n";
} catch (PDOException $e) {
    echo "L Database connection failed: " . $e->getMessage() . "\n";
    echo "   Please check your .env database configuration.\n";
    exit(1);
}

// Check if tables exist
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($tables)) {
    echo "=' Database is empty. You need to:\n";
    echo "   1. Import database_schema.sql via phpMyAdmin\n";
    echo "   2. Or run: mysql -u {$_ENV['DB_USERNAME']} -p {$_ENV['DB_DATABASE']} < database_schema.sql\n";
} else {
    echo " Database tables found: " . count($tables) . " tables\n";
}

// Check required directories
echo "=Á Checking data directories...\n";
$dirs = ['data/inbox', 'data/outbox', 'data/processed', 'data/error', 'data/archive'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
        echo " Created directory: $dir\n";
    } else {
        echo " Directory exists: $dir\n";
    }
}

// Check file permissions
echo "= Checking file permissions...\n";
foreach ($dirs as $dir) {
    if (is_writable($dir)) {
        echo " $dir is writable\n";
    } else {
        echo "L $dir is not writable - check permissions\n";
    }
}

// Check Composer autoload
echo "=æ Checking Composer autoload...\n";
if (file_exists('vendor/autoload.php')) {
    echo " Composer autoload found\n";
} else {
    echo "L Composer autoload missing - run: composer install\n";
}

echo "\n=== Setup Complete ===\n";
echo "<‰ EDI Module is ready for production use!\n";
echo "\nNext steps:\n";
echo "1. Configure your .env file with actual production values\n";
echo "2. Import database schema if not done already\n";
echo "3. Test the web interface\n";
echo "4. Configure SFTP credentials for Nifco integration\n";
?>