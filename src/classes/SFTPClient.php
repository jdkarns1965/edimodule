<?php

namespace Greenfield\EDI;

use phpseclib3\Net\SFTP;
use phpseclib3\Exception\UnableToConnectException;

class SFTPClient {
    private $sftp;
    private $config;
    private $connected = false;
    private $lastError = '';
    
    public function __construct($config = null) {
        if ($config === null) {
            $this->loadEnvironmentConfig();
        } else {
            $this->config = $config;
        }
    }
    
    private function loadEnvironmentConfig() {
        $this->config = [
            'host' => $_ENV['SFTP_HOST'] ?? 'localhost',
            'port' => (int)($_ENV['SFTP_PORT'] ?? 22),
            'username' => $_ENV['SFTP_USERNAME'] ?? '',
            'password' => $_ENV['SFTP_PASSWORD'] ?? '',
            'timeout' => (int)($_ENV['SFTP_TIMEOUT'] ?? 30),
            'inbox_path' => $_ENV['SFTP_INBOX_PATH'] ?? '/var/www/html/edimodule/data/inbox',
            'outbox_path' => $_ENV['SFTP_OUTBOX_PATH'] ?? '/var/www/html/edimodule/data/outbox',
            'processed_path' => $_ENV['SFTP_PROCESSED_PATH'] ?? '/var/www/html/edimodule/data/processed',
            'error_path' => $_ENV['SFTP_ERROR_PATH'] ?? '/var/www/html/edimodule/data/error',
            'archive_path' => $_ENV['SFTP_ARCHIVE_PATH'] ?? '/var/www/html/edimodule/data/archive',
            'remote_inbox' => $_ENV['SFTP_REMOTE_INBOX'] ?? '/inbox',
            'remote_outbox' => $_ENV['SFTP_REMOTE_OUTBOX'] ?? '/outbox'
        ];
    }
    
    public function connect() {
        try {
            $this->sftp = new SFTP($this->config['host'], $this->config['port']);
            $this->sftp->setTimeout($this->config['timeout']);
            
            if (!$this->sftp->login($this->config['username'], $this->config['password'])) {
                $this->lastError = 'SFTP login failed';
                return false;
            }
            
            $this->connected = true;
            $this->lastError = '';
            return true;
            
        } catch (UnableToConnectException $e) {
            $this->lastError = 'Unable to connect to SFTP server: ' . $e->getMessage();
            return false;
        } catch (\Exception $e) {
            $this->lastError = 'SFTP connection error: ' . $e->getMessage();
            return false;
        }
    }
    
    public function disconnect() {
        if ($this->connected && $this->sftp) {
            $this->sftp->disconnect();
            $this->connected = false;
        }
    }
    
    public function testConnection() {
        if (!$this->connect()) {
            return ['success' => false, 'error' => $this->lastError];
        }
        
        try {
            $pwd = $this->sftp->pwd();
            $this->disconnect();
            
            return [
                'success' => true,
                'message' => 'Connection successful',
                'current_directory' => $pwd,
                'server' => $this->config['host'] . ':' . $this->config['port'],
                'username' => $this->config['username']
            ];
            
        } catch (\Exception $e) {
            $this->disconnect();
            return ['success' => false, 'error' => 'Connection test failed: ' . $e->getMessage()];
        }
    }
    
    public function downloadFiles($remotePath = null, $localPath = null, $pattern = '*.edi') {
        if (!$this->connected && !$this->connect()) {
            return ['success' => false, 'error' => $this->lastError];
        }
        
        $remotePath = $remotePath ?? $this->config['remote_inbox'];
        $localPath = $localPath ?? $this->config['inbox_path'];
        
        try {
            $files = $this->sftp->nlist($remotePath);
            if ($files === false) {
                return ['success' => false, 'error' => 'Could not list remote directory: ' . $remotePath];
            }
            
            $downloadedFiles = [];
            $errors = [];
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                if ($this->matchesPattern($file, $pattern)) {
                    $remoteFile = $remotePath . '/' . $file;
                    $localFile = $localPath . '/' . $file;
                    
                    if ($this->sftp->get($remoteFile, $localFile)) {
                        $downloadedFiles[] = $file;
                        
                        if (file_exists($localFile)) {
                            chmod($localFile, 0644);
                        }
                    } else {
                        $errors[] = "Failed to download: $file";
                    }
                }
            }
            
            return [
                'success' => true,
                'downloaded' => $downloadedFiles,
                'count' => count($downloadedFiles),
                'errors' => $errors
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Download error: ' . $e->getMessage()];
        }
    }
    
    public function uploadFile($localFile, $remoteFile = null) {
        if (!$this->connected && !$this->connect()) {
            return ['success' => false, 'error' => $this->lastError];
        }
        
        if (!file_exists($localFile)) {
            return ['success' => false, 'error' => 'Local file not found: ' . $localFile];
        }
        
        if ($remoteFile === null) {
            $fileName = basename($localFile);
            $remoteFile = $this->config['remote_outbox'] . '/' . $fileName;
        }
        
        try {
            $result = $this->sftp->put($remoteFile, $localFile, SFTP::SOURCE_LOCAL_FILE);
            
            if ($result) {
                return [
                    'success' => true,
                    'local_file' => $localFile,
                    'remote_file' => $remoteFile,
                    'size' => filesize($localFile)
                ];
            } else {
                return ['success' => false, 'error' => 'Upload failed'];
            }
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Upload error: ' . $e->getMessage()];
        }
    }
    
    public function listFiles($remotePath = null, $pattern = '*.edi') {
        if (!$this->connected && !$this->connect()) {
            return ['success' => false, 'error' => $this->lastError];
        }
        
        $remotePath = $remotePath ?? $this->config['remote_inbox'];
        
        try {
            $files = $this->sftp->nlist($remotePath);
            if ($files === false) {
                return ['success' => false, 'error' => 'Could not list directory: ' . $remotePath];
            }
            
            $matchingFiles = [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                if ($this->matchesPattern($file, $pattern)) {
                    $stat = $this->sftp->stat($remotePath . '/' . $file);
                    $matchingFiles[] = [
                        'name' => $file,
                        'size' => $stat['size'] ?? 0,
                        'modified' => isset($stat['mtime']) ? date('Y-m-d H:i:s', $stat['mtime']) : 'Unknown'
                    ];
                }
            }
            
            return [
                'success' => true,
                'files' => $matchingFiles,
                'count' => count($matchingFiles),
                'path' => $remotePath
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'List files error: ' . $e->getMessage()];
        }
    }
    
    public function moveFile($source, $destination) {
        if (!$this->connected && !$this->connect()) {
            return ['success' => false, 'error' => $this->lastError];
        }
        
        try {
            $result = $this->sftp->rename($source, $destination);
            return [
                'success' => $result,
                'error' => $result ? null : 'Move operation failed'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Move error: ' . $e->getMessage()];
        }
    }
    
    public function deleteFile($filePath) {
        if (!$this->connected && !$this->connect()) {
            return ['success' => false, 'error' => $this->lastError];
        }
        
        try {
            $result = $this->sftp->delete($filePath);
            return [
                'success' => $result,
                'error' => $result ? null : 'Delete operation failed'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Delete error: ' . $e->getMessage()];
        }
    }
    
    private function matchesPattern($filename, $pattern) {
        $regex = str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/'));
        return preg_match('/^' . $regex . '$/i', $filename);
    }
    
    public function getLastError() {
        return $this->lastError;
    }
    
    public function isConnected() {
        return $this->connected;
    }
    
    public function getConfig() {
        $config = $this->config;
        $config['password'] = '***MASKED***';
        return $config;
    }
    
    public function __destruct() {
        $this->disconnect();
    }
}