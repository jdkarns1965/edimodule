<?php

// Disable composer platform checks to prevent blocking dashboard
putenv('COMPOSER_DISABLE_PLATFORM_CHECK=1');

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$requiredEnvVars = [
    'SFTP_HOST',
    'SFTP_USERNAME',
    'SFTP_PASSWORD'
];

foreach ($requiredEnvVars as $var) {
    if (!isset($_ENV[$var])) {
        error_log("Warning: Required environment variable $var is not set");
    }
}

function initializeSFTPServices() {
    if (!isset($_ENV['SFTP_ENABLED']) || $_ENV['SFTP_ENABLED'] !== 'true') {
        return false;
    }
    
    return true;
}

function logSFTPActivity($message, $level = 'INFO') {
    $logFile = $_ENV['LOG_FILE_PATH'] ?? '/var/www/html/edimodule/logs/sftp.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] [$level] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

spl_autoload_register(function ($class) {
    if (strpos($class, 'Greenfield\\EDI\\') === 0) {
        $file = __DIR__ . '/src/classes/' . str_replace('Greenfield\\EDI\\', '', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    return false;
});

if (initializeSFTPServices()) {
    logSFTPActivity('SFTP services initialized');
} else {
    logSFTPActivity('SFTP services disabled', 'WARNING');
}