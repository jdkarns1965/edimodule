# EDI Processing Environment - Complete Restore Guide

## Quick Start (Home Environment)

### Prerequisites
- PHP 8.1+ with MySQL extension
- MySQL 8.0+
- Composer
- Git

### 1. Clone Repository
```bash
git clone <your-repo-url> edimodule
cd edimodule
```

### 2. Restore Database
```bash
chmod +x data/exports/restore_database.sh
./data/exports/restore_database.sh
```

### 3. Install Dependencies
```bash
composer install
```

### 4. Configure Environment
```bash
# Copy and edit environment file
cp .env.production.template .env

# Update these settings for your home environment:
# DB_HOST=localhost
# DB_USERNAME=your_mysql_user
# DB_PASSWORD=your_mysql_password
# SFTP_HOST=localhost (or your test SFTP server)
# SFTP_USERNAME=your_username
# SFTP_PASSWORD=your_password
# Update all file paths to match your home directory structure
```

### 5. Set Up Directories
```bash
# Ensure proper permissions
chmod -R 755 data/
chmod -R 777 data/inbox data/outbox data/processed data/error data/archive
mkdir -p logs
chmod 777 logs
```

### 6. Test Installation
Navigate to: http://localhost/edimodule/src/public/

Expected functionality:
- ✅ Database connection working
- ✅ SFTP dashboard accessible
- ✅ File processing directories writable
- ✅ EDI transaction history visible

## Database Contents
- **Tables**: 8 tables with complete structure
- **Data**: All delivery schedules, EDI transactions, part master data
- **Size**: 397 lines of SQL
- **Created**: Wed Sep 17 15:12:35 EDT 2025

## Included Files
- Complete database backup
- All source code and templates  
- Configuration templates
- SFTP monitoring scripts
- Processing directories with sample data
- Comprehensive documentation

## Troubleshooting

### Database Issues
```bash
# Test MySQL connection
mysql -u root -p -e "SHOW DATABASES;"

# Verify edi_processing database
mysql -u root -p -e "USE edi_processing; SHOW TABLES;"
```

### Permission Issues
```bash
# Fix directory permissions
sudo chown -R www-data:www-data /path/to/edimodule
chmod -R 755 /path/to/edimodule
chmod -R 777 /path/to/edimodule/data
chmod -R 777 /path/to/edimodule/logs
```

### SFTP Issues
1. Update SFTP credentials in .env
2. Test connection via web dashboard
3. Verify directory paths exist and are writable

## Production Deployment
For production deployment on GreenGeeks:
1. Follow restore steps above
2. Use .env.production.template as starting point
3. Set up cron job: `*/15 * * * * /usr/bin/php /path/to/edimodule/cron_monitor.php`
4. Update SFTP credentials for Nifco production server
