#!/usr/bin/env php
<?php

/**
 * EDI File Monitor for Cron Execution
 * Designed for GreenGeeks shared hosting (15-minute minimum cron interval)
 * Optimized for single execution rather than continuous daemon
 */

require_once __DIR__ . '/bootstrap.php';

use Greenfield\EDI\EDIFileMonitor;

// Ensure we're running from command line or cron
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line or cron.\n");
}

// Lock file to prevent multiple instances
$lockFile = '/tmp/edi_cron_monitor.lock';
$lockHandle = fopen($lockFile, 'w');

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    // Another instance is running
    echo "Another monitor instance is already running. Exiting.\n";
    exit(0);
}

// Write PID to lock file
fwrite($lockHandle, getmypid());

// Register cleanup function
register_shutdown_function(function() use ($lockHandle, $lockFile) {
    if ($lockHandle) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

// Enhanced logging for cron execution
function cronLog($message, $level = 'INFO') {
    $logFile = $_ENV['LOG_FILE_PATH'] ?? '/var/www/html/edimodule/logs/cron_monitor.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] [$level] [CRON] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    
    // Also output to stdout for cron email notifications (if enabled)
    echo $logLine;
}

try {
    cronLog('Starting EDI cron monitor cycle');
    
    // Create monitor instance
    $monitor = new EDIFileMonitor();
    
    // Check if monitoring is enabled
    $status = $monitor->getStatus();
    if (!$status['config']['enabled']) {
        cronLog('File monitoring is disabled. Exiting.', 'INFO');
        exit(0);
    }
    
    // Run monitoring cycle with enhanced error handling
    $startTime = microtime(true);
    $result = $monitor->runSingleCycle();
    $duration = round(microtime(true) - $startTime, 2);
    
    if ($result['success']) {
        $downloadResult = $result['download_result'];
        $processResult = $result['process_result'];
        
        $downloaded = $downloadResult['count'] ?? 0;
        $processed = $processResult['processed'] ?? 0;
        
        cronLog("Cycle completed in {$duration}s - Downloaded: $downloaded, Processed: $processed");
        
        // Log download details if files were found
        if ($downloaded > 0) {
            $fileList = implode(', ', $downloadResult['downloaded']);
            cronLog("Downloaded files: $fileList");
        }
        
        // Log any download errors
        if (!empty($downloadResult['errors'])) {
            foreach ($downloadResult['errors'] as $error) {
                cronLog("Download warning: $error", 'WARNING');
            }
        }
        
        // Log any processing errors
        if (!empty($processResult['errors'])) {
            foreach ($processResult['errors'] as $error) {
                cronLog("Processing error: $error", 'ERROR');
            }
        }
        
        // Test SFTP connection health periodically
        $connectionTest = $monitor->testSFTPConnection();
        if (!$connectionTest['success']) {
            cronLog("SFTP connection test failed: {$connectionTest['error']}", 'WARNING');
        }
        
    } else {
        cronLog("Cycle failed: {$result['error']}", 'ERROR');
        exit(1);
    }
    
} catch (Exception $e) {
    cronLog("Monitor exception: " . $e->getMessage(), 'ERROR');
    cronLog("Stack trace: " . $e->getTraceAsString(), 'DEBUG');
    exit(1);
}

cronLog('EDI cron monitor cycle completed successfully');
exit(0);