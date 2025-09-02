<?php

namespace Greenfield\EDI;

require_once __DIR__ . '/SFTPClient.php';

class EDIFileMonitor {
    private $sftpClient;
    private $config;
    private $logFile;
    private $isRunning = false;
    private $processedFiles = [];
    
    public function __construct($config = null) {
        $this->loadConfig($config);
        $this->sftpClient = new SFTPClient();
        $this->logFile = $this->config['log_file'] ?? '/var/www/html/edimodule/logs/file_monitor.log';
        $this->ensureLogDirectory();
    }
    
    private function loadConfig($config) {
        $this->config = $config ?? [
            'enabled' => $_ENV['FILE_MONITOR_ENABLED'] ?? true,
            'interval' => (int)($_ENV['FILE_MONITOR_INTERVAL'] ?? 60),
            'max_concurrent' => (int)($_ENV['MAX_CONCURRENT_PROCESSES'] ?? 3),
            'inbox_path' => $_ENV['SFTP_INBOX_PATH'] ?? '/var/www/html/edimodule/data/inbox',
            'processed_path' => $_ENV['SFTP_PROCESSED_PATH'] ?? '/var/www/html/edimodule/data/processed',
            'error_path' => $_ENV['SFTP_ERROR_PATH'] ?? '/var/www/html/edimodule/data/error',
            'log_file' => $_ENV['LOG_FILE_PATH'] ?? '/var/www/html/edimodule/logs/file_monitor.log'
        ];
    }
    
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public function startMonitoring() {
        if (!$this->config['enabled']) {
            $this->log('File monitoring is disabled');
            return false;
        }
        
        $this->log('Starting EDI file monitoring service');
        $this->isRunning = true;
        
        while ($this->isRunning) {
            try {
                $this->log('Checking for new SFTP files...');
                
                $this->downloadNewFiles();
                
                $this->processLocalFiles();
                
                $this->log('Monitor cycle completed. Sleeping for ' . $this->config['interval'] . ' seconds');
                sleep($this->config['interval']);
                
            } catch (\Exception $e) {
                $this->log('Monitor error: ' . $e->getMessage(), 'ERROR');
                sleep($this->config['interval']);
            }
        }
        
        $this->log('File monitoring stopped');
    }
    
    public function stopMonitoring() {
        $this->isRunning = false;
        $this->log('Stop signal received');
    }
    
    public function runSingleCycle() {
        $this->log('Running single monitoring cycle');
        
        try {
            $downloadResult = $this->downloadNewFiles();
            $processResult = $this->processLocalFiles();
            
            return [
                'success' => true,
                'download_result' => $downloadResult,
                'process_result' => $processResult,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            $this->log('Single cycle error: ' . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    private function downloadNewFiles() {
        $result = $this->sftpClient->downloadFiles();
        
        if ($result['success']) {
            if ($result['count'] > 0) {
                $this->log("Downloaded {$result['count']} files: " . implode(', ', $result['downloaded']));
            } else {
                $this->log('No new files found on SFTP server');
            }
            
            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $this->log($error, 'WARNING');
                }
            }
        } else {
            $this->log('SFTP download failed: ' . $result['error'], 'ERROR');
        }
        
        return $result;
    }
    
    private function processLocalFiles() {
        $inboxPath = $this->config['inbox_path'];
        $files = glob($inboxPath . '/*.edi');
        
        if (empty($files)) {
            $this->log('No EDI files found in inbox');
            return ['success' => true, 'processed' => 0];
        }
        
        $processedCount = 0;
        $errors = [];
        
        foreach ($files as $file) {
            $fileName = basename($file);
            
            if (in_array($fileName, $this->processedFiles)) {
                continue;
            }
            
            try {
                $this->log("Processing file: $fileName");
                
                if ($this->processEDIFile($file)) {
                    $this->moveToProcessed($file);
                    $this->processedFiles[] = $fileName;
                    $processedCount++;
                    $this->log("Successfully processed: $fileName");
                } else {
                    $this->moveToError($file);
                    $errors[] = "Failed to process: $fileName";
                    $this->log("Failed to process: $fileName", 'ERROR');
                }
                
            } catch (\Exception $e) {
                $this->moveToError($file);
                $errors[] = "Error processing $fileName: " . $e->getMessage();
                $this->log("Error processing $fileName: " . $e->getMessage(), 'ERROR');
            }
        }
        
        return [
            'success' => true,
            'processed' => $processedCount,
            'errors' => $errors
        ];
    }
    
    private function processEDIFile($filePath) {
        if (!class_exists('EDI862Parser')) {
            include_once __DIR__ . '/EDI862Parser.php';
        }
        
        try {
            $parser = new \EDI862Parser();
            $content = file_get_contents($filePath);
            
            if ($content === false) {
                throw new \Exception('Could not read file: ' . $filePath);
            }
            
            $result = $parser->parseEDI($content);
            
            if (!$result || !isset($result['success']) || !$result['success']) {
                $error = isset($result['error']) ? $result['error'] : 'Unknown parsing error';
                throw new \Exception('EDI parsing failed: ' . $error);
            }
            
            $this->logTransactionToDatabase($filePath, $result);
            
            return true;
            
        } catch (\Exception $e) {
            $this->log("EDI processing error for $filePath: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    private function logTransactionToDatabase($filePath, $result) {
        try {
            require_once __DIR__ . '/../config/database.php';
            
            $db = DatabaseConfig::getInstance();
            $pdo = $db->getConnection();
            
            $stmt = $pdo->prepare("
                INSERT INTO edi_transactions (
                    filename,
                    file_path,
                    transaction_type,
                    trading_partner,
                    status,
                    records_processed,
                    processing_notes,
                    processed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                basename($filePath),
                $filePath,
                '862',
                'NIFCO',
                'SUCCESS',
                $result['records_processed'] ?? 0,
                json_encode($result)
            ]);
            
            $this->log("Transaction logged to database for: " . basename($filePath));
            
        } catch (\Exception $e) {
            $this->log("Database logging error: " . $e->getMessage(), 'WARNING');
        }
    }
    
    private function moveToProcessed($filePath) {
        $fileName = basename($filePath);
        $processedPath = $this->config['processed_path'] . '/' . $fileName;
        
        if (rename($filePath, $processedPath)) {
            $this->log("Moved to processed: $fileName");
        } else {
            $this->log("Failed to move to processed: $fileName", 'WARNING');
        }
    }
    
    private function moveToError($filePath) {
        $fileName = basename($filePath);
        $errorPath = $this->config['error_path'] . '/' . $fileName;
        
        if (rename($filePath, $errorPath)) {
            $this->log("Moved to error folder: $fileName");
        } else {
            $this->log("Failed to move to error folder: $fileName", 'WARNING');
        }
    }
    
    public function getStatus() {
        return [
            'running' => $this->isRunning,
            'config' => [
                'enabled' => $this->config['enabled'],
                'interval' => $this->config['interval'],
                'inbox_path' => $this->config['inbox_path']
            ],
            'sftp_status' => $this->sftpClient->testConnection(),
            'processed_files_count' => count($this->processedFiles),
            'last_check' => date('Y-m-d H:i:s')
        ];
    }
    
    public function getRecentLogs($lines = 50) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $logs = [];
        $file = new \SplFileObject($this->logFile);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);
        
        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $logs[] = $line;
            }
            $file->next();
        }
        
        return $logs;
    }
    
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        if (php_sapi_name() === 'cli') {
            echo $logLine;
        }
    }
    
    public function testSFTPConnection() {
        return $this->sftpClient->testConnection();
    }
    
    public function listInboxFiles() {
        $localFiles = glob($this->config['inbox_path'] . '/*.edi');
        $remoteFiles = $this->sftpClient->listFiles();
        
        return [
            'local' => array_map('basename', $localFiles),
            'remote' => $remoteFiles
        ];
    }
}