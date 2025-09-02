<?php

require_once 'bootstrap.php';

use Greenfield\EDI\SFTPClient;
use Greenfield\EDI\EDIFileMonitor;

echo "SFTP Integration Test\n";
echo "====================\n\n";

try {
    echo "1. Testing environment configuration...\n";
    $requiredVars = ['SFTP_HOST', 'SFTP_USERNAME', 'SFTP_PASSWORD'];
    foreach ($requiredVars as $var) {
        $value = $_ENV[$var] ?? 'NOT SET';
        if ($var === 'SFTP_PASSWORD') {
            $value = strlen($value) > 0 ? '***SET***' : 'NOT SET';
        }
        echo "   $var: $value\n";
    }
    
    echo "\n2. Testing SFTPClient instantiation...\n";
    $sftpClient = new SFTPClient();
    echo "   ✓ SFTPClient created successfully\n";
    
    echo "\n3. Testing SFTP connection...\n";
    $connectionResult = $sftpClient->testConnection();
    if ($connectionResult['success']) {
        echo "   ✓ Connection successful!\n";
        echo "   Server: " . $connectionResult['server'] . "\n";
        echo "   Username: " . $connectionResult['username'] . "\n";
        echo "   Current Directory: " . $connectionResult['current_directory'] . "\n";
    } else {
        echo "   ✗ Connection failed: " . $connectionResult['error'] . "\n";
        echo "   Note: This is expected if SFTP credentials are not configured\n";
    }
    
    echo "\n4. Testing file listing...\n";
    $listResult = $sftpClient->listFiles();
    if ($listResult['success']) {
        echo "   ✓ File listing successful\n";
        echo "   Files found: " . $listResult['count'] . "\n";
        if ($listResult['count'] > 0) {
            foreach ($listResult['files'] as $file) {
                echo "     - " . $file['name'] . " (" . number_format($file['size']) . " bytes)\n";
            }
        }
    } else {
        echo "   ✗ File listing failed: " . $listResult['error'] . "\n";
    }
    
    echo "\n5. Testing EDIFileMonitor instantiation...\n";
    $fileMonitor = new EDIFileMonitor();
    echo "   ✓ EDIFileMonitor created successfully\n";
    
    echo "\n6. Testing file monitor status...\n";
    $status = $fileMonitor->getStatus();
    echo "   Monitor enabled: " . ($status['config']['enabled'] ? 'Yes' : 'No') . "\n";
    echo "   Check interval: " . $status['config']['interval'] . " seconds\n";
    echo "   SFTP connection status: " . ($status['sftp_status']['success'] ? 'Connected' : 'Failed') . "\n";
    
    echo "\n7. Testing inbox file listing...\n";
    $inboxFiles = $fileMonitor->listInboxFiles();
    echo "   Local inbox files: " . count($inboxFiles['local']) . "\n";
    foreach ($inboxFiles['local'] as $file) {
        echo "     - $file\n";
    }
    
    if ($inboxFiles['remote']['success']) {
        echo "   Remote inbox files: " . $inboxFiles['remote']['count'] . "\n";
        if (!empty($inboxFiles['remote']['files'])) {
            foreach ($inboxFiles['remote']['files'] as $file) {
                echo "     - " . $file['name'] . " (" . number_format($file['size']) . " bytes)\n";
            }
        }
    } else {
        echo "   Remote file listing failed: " . $inboxFiles['remote']['error'] . "\n";
    }
    
    echo "\n8. Testing single processing cycle (if files exist)...\n";
    if (!empty($inboxFiles['local']) || ($inboxFiles['remote']['success'] && $inboxFiles['remote']['count'] > 0)) {
        $cycleResult = $fileMonitor->runSingleCycle();
        if ($cycleResult['success']) {
            echo "   ✓ Processing cycle completed successfully\n";
            $downloaded = $cycleResult['download_result']['count'] ?? 0;
            $processed = $cycleResult['process_result']['processed'] ?? 0;
            echo "   Files downloaded: $downloaded\n";
            echo "   Files processed: $processed\n";
            
            if (!empty($cycleResult['process_result']['errors'])) {
                echo "   Processing errors:\n";
                foreach ($cycleResult['process_result']['errors'] as $error) {
                    echo "     - $error\n";
                }
            }
        } else {
            echo "   ✗ Processing cycle failed: " . $cycleResult['error'] . "\n";
        }
    } else {
        echo "   Skipped - no files to process\n";
    }
    
    echo "\n9. Testing EDI862Parser (if test file exists)...\n";
    $testEDIFile = '/var/www/html/edimodule/data/inbox/test_862.edi';
    if (file_exists($testEDIFile)) {
        require_once 'src/classes/EDI862Parser.php';
        $parser = new EDI862Parser();
        $content = file_get_contents($testEDIFile);
        
        echo "   Testing with file: " . basename($testEDIFile) . "\n";
        echo "   File size: " . number_format(strlen($content)) . " bytes\n";
        
        try {
            $result = $parser->parseEDI($content);
            if ($result['success']) {
                echo "   ✓ EDI parsing successful\n";
                echo "   Records processed: " . $result['records_processed'] . "\n";
            } else {
                echo "   ✗ EDI parsing failed\n";
            }
        } catch (Exception $e) {
            echo "   ✗ EDI parsing error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   Skipped - test EDI file not found at $testEDIFile\n";
    }
    
    echo "\n=== SFTP Integration Test Complete ===\n";
    echo "Summary:\n";
    echo "- SFTPClient: ✓ Working\n";
    echo "- EDIFileMonitor: ✓ Working\n";
    echo "- SFTP Connection: " . ($connectionResult['success'] ? '✓ Connected' : '✗ Failed (check credentials)') . "\n";
    echo "- File Operations: " . ($listResult['success'] ? '✓ Working' : '✗ Failed') . "\n";
    echo "\nNext steps:\n";
    echo "1. Configure proper SFTP credentials in .env file\n";
    echo "2. Test with actual EDI files\n";
    echo "3. Set up automated monitoring via cron job\n";
    echo "4. Test web interface at http://localhost/edimodule/src/public/?page=sftp\n";
    
} catch (Exception $e) {
    echo "Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}