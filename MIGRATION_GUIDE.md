# EDI Module Migration Guide

Complete migration plan for moving your EDI processing module between development environments (work ↔ home) and to production.

## Overview

This migration system provides:
- **Database backup and restore** with full schema and data
- **File synchronization** excluding environment-specific files
- **Environment-specific configuration** templates
- **Automated validation** to ensure migration success
- **Rollback capabilities** in case of issues

## Quick Start

### 1. Backup Current Environment (Source)
```bash
# Create database backup
cd /path/to/edimodule
chmod +x migration/*.sh
./migration/backup_database.sh work

# Create file sync package
./migration/sync_files.sh work home
```

### 2. Transfer to Target Environment
```bash
# Copy these files to your target environment:
scp edi_sync_work_to_home_*.tar.gz user@target-host:/path/to/edimodule/
scp extract_sync_home.sh user@target-host:/path/to/edimodule/
scp backups/edi_backup_work_*.sql* user@target-host:/path/to/edimodule/backups/
```

### 3. Setup Target Environment
```bash
# On target machine
cd /path/to/edimodule

# Generate environment template
./migration/create_env_template.sh home

# Extract files
./extract_sync_home.sh edi_sync_work_to_home_*.tar.gz

# Configure environment
cp .env.home.template .env
nano .env  # Update with your settings

# Install dependencies
composer install

# Restore database
./migration/restore_database.sh backups/edi_backup_work_*.sql

# Validate migration
./migration/validate_migration.sh
```

## Detailed Migration Process

### Phase 1: Pre-Migration Planning

1. **Assess Current State**
   ```bash
   # Check database size and record counts
   mysql -e "
   USE edi_processing;
   SELECT 
       table_name,
       table_rows,
       ROUND((data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
   FROM information_schema.tables 
   WHERE table_schema = 'edi_processing'
   ORDER BY (data_length + index_length) DESC;
   "
   ```

2. **Review Environment Differences**
   - Database connection details
   - SFTP server settings  
   - File system paths
   - PHP/MySQL versions
   - Available extensions

### Phase 2: Source Environment Backup

1. **Database Backup**
   ```bash
   ./migration/backup_database.sh [environment_name]
   ```
   
   Creates:
   - `backups/edi_backup_[env]_[timestamp].sql` - Complete database dump
   - `backups/edi_backup_[env]_[timestamp].sql.meta` - Backup metadata

2. **File System Sync**
   ```bash
   ./migration/sync_files.sh [source_env] [target_env]
   ```
   
   Creates:
   - `edi_sync_[source]_to_[target]_[timestamp].tar.gz` - Compressed archive
   - `extract_sync_[target].sh` - Extraction script
   - `migration/sync_manifest_*.txt` - File listing

### Phase 3: Target Environment Setup

1. **Environment Preparation**
   ```bash
   # Create environment template
   ./migration/create_env_template.sh [target_env]
   
   # This creates:
   # - .env.[target_env].template
   # - setup_[target_env]_environment.md
   ```

2. **Database Setup**
   ```bash
   # Create database if it doesn't exist
   mysql -e "CREATE DATABASE IF NOT EXISTS edi_processing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   
   # Restore from backup
   ./migration/restore_database.sh backups/your_backup.sql [target_db_name]
   ```

3. **File Extraction**
   ```bash
   # Extract synchronized files
   ./extract_sync_[target_env].sh your_sync_archive.tar.gz
   ```

4. **Configuration**
   ```bash
   # Setup environment configuration
   cp .env.[target_env].template .env
   
   # Edit for your specific environment
   nano .env
   
   # Install PHP dependencies
   composer install --no-dev
   ```

### Phase 4: Migration Validation

```bash
# Run comprehensive validation
./migration/validate_migration.sh [database_name]
```

The validation script checks:
- Database connectivity and schema integrity
- Required tables and foreign key constraints  
- Default data presence (Nifco partner, locations)
- File system structure and permissions
- Configuration files and dependencies
- Data integrity (orphaned records, etc.)

### Phase 5: Post-Migration Testing

1. **Database Tests**
   ```bash
   php test_schedules_direct.php
   ```

2. **SFTP Integration**
   ```bash
   php test_sftp_integration.php
   ```

3. **Web Interface**
   ```bash
   php test_web_interface.php
   ```

## Environment-Specific Configuration

### Work Environment
- SFTP: localhost:22 (jdkarns user)
- Database: Local MySQL on standard port
- File paths: /var/www/html/edimodule/...

### Home Environment  
- SFTP: localhost:2022 (homeuser)
- Database: Local MySQL (may be different port)
- File paths: Adjust based on your home setup

### Production Environment
- SFTP: edi.nifco.com:22 (greenfield_plastics_edi)
- Database: Production MySQL server
- Enhanced security and monitoring

## Key Files and Directories

### Migration Scripts
```
migration/
├── backup_database.sh          # Create database backups
├── restore_database.sh         # Restore database from backup
├── sync_files.sh              # Create file sync packages  
├── create_env_template.sh     # Generate environment templates
└── validate_migration.sh      # Validate migration success
```

### Synced Content
```
Files/Directories Included:
- src/                         # Application source code
- data/processed/              # Processed EDI files
- data/archive/               # Archived files  
- sql/                        # SQL scripts
- migration/                  # Migration tools
- logs/                       # Log files (structure only)
- *.php                       # PHP application files
- composer.json/lock          # Dependency management
- *.sql, *.md                 # SQL scripts and documentation

Files/Directories Excluded:
- vendor/                     # Composer dependencies (reinstalled)
- data/inbox/                 # Incoming files (environment-specific)
- data/outbox/                # Outgoing files (environment-specific)
- data/error/                 # Error files (environment-specific)
- .env*                       # Environment configuration
- logs/*.log                  # Log files (data)
- backups/                    # Backup files
- *.tmp, *.bak               # Temporary files
```

## Rollback Procedures

### Database Rollback
If database restore fails or causes issues:
```bash
# The restore script automatically creates pre-restore backups
mysql < backups/pre_restore_backup_[timestamp].sql
```

### File Rollback  
If file extraction causes issues:
```bash
# Environment configuration is automatically backed up
cp .env.backup.[timestamp] .env

# For complete rollback, restore from your previous backup
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Verify MySQL is running
   - Check credentials in .env
   - Ensure database exists
   - Test: `mysql -u username -p database_name`

2. **SFTP Connection Failed**  
   - Verify SFTP server is running
   - Check credentials and host/port
   - Test: `ssh -p port username@hostname`

3. **Permission Denied**
   - Fix directory permissions: `chmod -R 755 data/ logs/`
   - Ensure web server can write to directories
   - Check SELinux/AppArmor if applicable

4. **Composer Install Failed**
   - Clear composer cache: `composer clear-cache`
   - Update composer: `composer self-update`
   - Check PHP extensions: `php -m`

5. **File Sync Issues**
   - Verify tar/gzip availability
   - Check disk space on both environments
   - Ensure proper file permissions

### Getting Help

1. **Check Validation Log**
   ```bash
   cat migration_validation_[timestamp].log
   ```

2. **Review Application Logs**
   ```bash
   tail -f logs/edi.log
   tail -f logs/sftp.log
   ```

3. **Test Individual Components**
   ```bash
   php test_schedules_direct.php     # Database
   php test_sftp_integration.php     # SFTP
   php test_web_interface.php        # Web interface
   ```

## Best Practices

### Regular Backups
```bash
# Schedule daily backups
echo "0 2 * * * cd /path/to/edimodule && ./migration/backup_database.sh daily" | crontab -
```

### Security Considerations
- Use strong, unique passwords for each environment
- Limit SFTP user permissions to necessary directories only
- Enable database SSL connections in production
- Regular security updates for all components
- Monitor log files for suspicious activity

### Performance Optimization
- Regular database maintenance: `OPTIMIZE TABLE table_name;`
- Log rotation to prevent disk space issues
- Monitor and clean up old backup files
- Index optimization for large datasets

### Documentation Updates
- Keep environment-specific documentation current
- Document any custom configurations or modifications
- Maintain change logs for major updates
- Update contact information and credentials as needed

## Migration Checklist

### Pre-Migration
- [ ] Document current environment configuration
- [ ] Test all major functionality before migration
- [ ] Ensure target environment meets system requirements
- [ ] Create complete backup of source environment
- [ ] Verify network connectivity between environments

### During Migration
- [ ] Run database backup script
- [ ] Create file sync package
- [ ] Transfer files to target environment
- [ ] Generate environment template
- [ ] Configure target environment settings
- [ ] Install dependencies
- [ ] Restore database
- [ ] Run validation script

### Post-Migration
- [ ] Verify all validation tests pass
- [ ] Test database connectivity
- [ ] Test SFTP integration  
- [ ] Test web interface functionality
- [ ] Perform end-to-end EDI processing test
- [ ] Update documentation with new environment details
- [ ] Schedule regular backups
- [ ] Monitor system logs for issues

This comprehensive migration system ensures reliable, repeatable transfers between your development environments while maintaining data integrity and system functionality.