#!/usr/bin/env php
<?php

/**
 * EDI File Monitor Daemon
 * Continuously monitors SFTP for new EDI files and processes them
 */

require_once __DIR__ . '/bootstrap.php';

use Greenfield\EDI\EDIFileMonitor;

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Handle signals for graceful shutdown
declare(ticks = 1);

$running = true;
$monitor = null;

function signalHandler($signal) {
    global $running, $monitor;
    
    switch ($signal) {
        case SIGTERM:
        case SIGINT:
            echo "\nReceived shutdown signal. Stopping monitor...\n";
            $running = false;
            if ($monitor) {
                $monitor->stopMonitoring();
            }
            break;
        case SIGHUP:
            echo "\nReceived reload signal. Restarting monitor...\n";
            if ($monitor) {
                $monitor->stopMonitoring();
            }
            // Monitor will restart in main loop
            break;
    }
}

// Install signal handlers
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, 'signalHandler');
    pcntl_signal(SIGINT, 'signalHandler');
    pcntl_signal(SIGHUP, 'signalHandler');
}

// Parse command line options
$options = getopt("d:h", ["daemon", "help", "test", "single"]);

if (isset($options['h']) || isset($options['help'])) {
    echo "EDI File Monitor Daemon\n";
    echo "Usage: php monitor_daemon.php [options]\n\n";
    echo "Options:\n";
    echo "  -d, --daemon    Run as daemon (background process)\n";
    echo "      --test      Test SFTP connection and exit\n";
    echo "      --single    Run single monitoring cycle and exit\n";
    echo "  -h, --help      Show this help message\n\n";
    exit(0);
}

// Create monitor instance
try {
    $monitor = new EDIFileMonitor();
    
    // Test mode - just check connection
    if (isset($options['test'])) {
        echo "Testing SFTP connection...\n";
        $result = $monitor->testSFTPConnection();
        
        if ($result['success']) {
            echo "✓ SFTP connection successful\n";
            echo "  Server: {$result['server']}\n";
            echo "  Username: {$result['username']}\n";
            echo "  Current Directory: {$result['current_directory']}\n";
            exit(0);
        } else {
            echo "✗ SFTP connection failed: {$result['error']}\n";
            exit(1);
        }
    }
    
    // Single cycle mode
    if (isset($options['single'])) {
        echo "Running single monitoring cycle...\n";
        $result = $monitor->runSingleCycle();
        
        if ($result['success']) {
            $downloaded = $result['download_result']['count'] ?? 0;
            $processed = $result['process_result']['processed'] ?? 0;
            echo "✓ Cycle completed successfully\n";
            echo "  Downloaded: $downloaded files\n";
            echo "  Processed: $processed files\n";
            
            if (!empty($result['process_result']['errors'])) {
                echo "  Errors: " . implode(', ', $result['process_result']['errors']) . "\n";
            }
        } else {
            echo "✗ Cycle failed: {$result['error']}\n";
            exit(1);
        }
        exit(0);
    }
    
    // Daemon mode setup
    $isDaemon = isset($options['d']) || isset($options['daemon']);
    
    if ($isDaemon) {
        echo "Starting EDI File Monitor in daemon mode...\n";
        
        // Create PID file
        $pidFile = '/var/run/edi_monitor.pid';
        if (file_exists($pidFile)) {
            $existingPid = trim(file_get_contents($pidFile));
            if ($existingPid && posix_kill($existingPid, 0)) {
                die("Monitor already running with PID $existingPid\n");
            }
        }
        
        // Fork process for daemon mode (if supported)
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();
            
            if ($pid == -1) {
                die("Could not fork process\n");
            } elseif ($pid) {
                // Parent process - write PID and exit
                file_put_contents($pidFile, getmypid());
                echo "Monitor started with PID " . getmypid() . "\n";
                exit(0);
            }
            
            // Child process continues as daemon
            posix_setsid();
            chdir('/');
            umask(0);
            
            // Close standard file descriptors
            fclose(STDIN);
            fclose(STDOUT);
            fclose(STDERR);
        }
        
        // Write PID file
        file_put_contents($pidFile, getmypid());
        
        // Register shutdown function to clean up PID file
        register_shutdown_function(function() use ($pidFile) {
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
        });
    } else {
        echo "Starting EDI File Monitor in foreground mode...\n";
        echo "Press Ctrl+C to stop.\n\n";
    }
    
    // Main monitoring loop
    while ($running) {
        try {
            $result = $monitor->runSingleCycle();
            
            if (!$isDaemon) {
                if ($result['success']) {
                    $downloaded = $result['download_result']['count'] ?? 0;
                    $processed = $result['process_result']['processed'] ?? 0;
                    echo "[" . date('Y-m-d H:i:s') . "] Cycle: Downloaded $downloaded, Processed $processed\n";
                    
                    if (!empty($result['process_result']['errors'])) {
                        echo "  Errors: " . implode(', ', $result['process_result']['errors']) . "\n";
                    }
                } else {
                    echo "[" . date('Y-m-d H:i:s') . "] Cycle failed: {$result['error']}\n";
                }
            }
            
            // Wait for next cycle
            $interval = (int)($_ENV['FILE_MONITOR_INTERVAL'] ?? 60);
            sleep($interval);
            
        } catch (Exception $e) {
            $error = "Monitor error: " . $e->getMessage();
            if (!$isDaemon) {
                echo "[$error]\n";
            }
            error_log($error);
            
            // Wait before retrying
            sleep(30);
        }
    }
    
} catch (Exception $e) {
    $error = "Failed to start monitor: " . $e->getMessage();
    echo "$error\n";
    error_log($error);
    exit(1);
}

echo "EDI File Monitor stopped.\n";
exit(0);