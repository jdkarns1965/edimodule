# SFTP Integration Requirements for EDI Application

## Immediate Implementation Goals

### 1. Composer Setup
- Create composer.json with phpseclib/phpseclib dependency
- Add autoloader integration to existing application
- Ensure compatibility with existing codebase

### 2. SFTPClient Class (src/classes/SFTPClient.php)
**Core Methods Needed:**
```php
class SFTPClient {
    public function __construct($config);           // Initialize with .env config
    public function connect();                      // Connect using phpseclib
    public function disconnect();                   // Clean disconnect
    public function downloadFiles($remotePath, $localPath, $pattern = '*.edi');
    public function uploadFile($localFile, $remoteFile);
    public function listFiles($remotePath, $pattern = '*.edi');
    public function moveFile($source, $destination);
    public function deleteFile($filePath);
    public function testConnection();               // Connection health check
}
```

### 3. File Monitor Service (src/classes/EDIFileMonitor.php)
**Automated Processing:**
- Monitor inbox directory for new 862 files
- Process files using existing EDI parsing logic
- Move processed files to appropriate directories
- Handle errors and logging
- Prevent concurrent processing of same file

### 4. Integration with Existing Code
**Connect SFTP to Current Application:**
- Integrate with existing EDI862Parser class
- Use current database schema (delivery_schedules, edi_transactions)
- Generate 856 files using existing shipment data
- Update web interface to show SFTP status

### 5. Configuration Management
**Environment Variables (.env):**
```
SFTP_ENABLED=true
SFTP_LIBRARY=phpseclib
SFTP_HOST=localhost
SFTP_PORT=22
SFTP_USERNAME=jdkarns
SFTP_PASSWORD=your_password
SFTP_TIMEOUT=30
SFTP_INBOX_PATH=/var/www/html/edimodule/data/inbox
SFTP_OUTBOX_PATH=/var/www/html/edimodule/data/outbox
SFTP_PROCESSED_PATH=/var/www/html/edimodule/data/processed
SFTP_ERROR_PATH=/var/www/html/edimodule/data/error
```

### 6. Web Interface Updates
**Dashboard Enhancements:**
- SFTP connection status indicator
- Recent file processing log
- Manual "Check for Files" button
- File processing statistics
- Connection test interface

## Testing Requirements

### Automated Tests
- Test SFTP connection to localhost
- Test file download from inbox
- Test file upload to outbox
- Test error handling for connection failures
- Test file processing workflow end-to-end

### Integration Tests
- Process actual test_862.edi file from inbox
- Generate and place 856 response in outbox
- Verify database updates after file processing
- Test web interface SFTP controls

## Error Handling
- Connection timeout handling
- File permission errors
- Duplicate file processing prevention
- Comprehensive logging of all operations
- Email notifications for critical errors (optional)

## Security Considerations
- Secure credential storage in .env
- File validation before processing
- Directory traversal prevention
- Connection encryption verification

## Production Deployment Notes
- Configuration changes for GreenGeeks hosting
- Path adjustments for production environment
- Nifco SFTP credential integration
- Performance monitoring and optimization

## Current Status
- Local SFTP server tested and working
- Directory structure created and verified
- Test EDI file available: /var/www/html/edimodule/data/inbox/test_862.edi
- GreenGeeks hosting compatibility confirmed
- Ready for implementation with Claude Code