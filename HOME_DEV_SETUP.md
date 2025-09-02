# EDI Module - Home Development Environment Setup

## Complete Project Backup - Ready for Home Development

This package contains everything needed to set up the EDI processing module in your home development environment.

## What's Included

### 1. Complete Codebase
- All PHP source files and classes
- Web interface templates
- Configuration files
- Composer dependencies (vendor/ folder)

### 2. Database Backup
- **File**: `edimodule_complete_backup.sql`
- **Database Name**: `edi_processing` 
- **Contains**: Full schema + all data including:
  - delivery_schedules table with sample data
  - part_master table with QPC data
  - edi_transactions log
  - trading_partners configuration
  - ship_to_locations mappings

### 3. Sample Data Files
- `sample_data.tsv` - Original TSV delivery schedule data
- `data/processed/test_862_complete.edi` - Processed EDI file
- `data/outbox/response_856.edi` - Generated ASN response

## Home Setup Instructions

### Prerequisites
- LAMP stack (Linux/Windows + Apache + MySQL 8+ + PHP 8.1+)
- Composer for PHP dependencies
- SSH server for SFTP testing (optional)

### Step 1: Database Setup
```bash
# Create database and import data
mysql -u root -p
CREATE DATABASE edi_processing;
exit

# Import the complete backup
mysql -u root -p edi_processing < edimodule_complete_backup.sql
```

### Step 2: Update Database Configuration
Edit `src/config/database.php` with your local credentials:
```php
private $host = 'localhost';
private $database = 'edi_processing';
private $username = 'your_mysql_user';
private $password = 'your_mysql_password';
```

### Step 3: Install Dependencies
```bash
# If you have composer globally installed:
composer install

# Or use the included composer.phar:
php composer.phar install
```

### Step 4: Set Permissions
```bash
# Make data directories writable
chmod -R 755 data/
chmod -R 755 logs/
```

### Step 5: Web Server Setup
- Point your web server document root to the project directory
- Or create a virtual host pointing to `/path/to/edimodule/src/public/`
- Access via: `http://localhost/edimodule/src/public/` or your configured URL

### Step 6: Test Installation
1. Navigate to the web interface
2. Check dashboard loads properly
3. Verify database connection
4. Test basic functionality

## Key Features Working

### Web Interface
- **Dashboard**: Overview with stats and recent activity
- **Delivery Matrix** (?page=delivery_matrix): Interactive schedule viewing/filtering
- **Part Master** (?page=part_master): Part management with QPC data
- **Import System** (?page=import): TSV and EDI file processing
- **SFTP Status** (?page=sftp): File transfer monitoring

### Core Functionality
- EDI 862 parsing and database storage
- TSV file import and processing
- Delivery schedule matrix with filtering
- Part master management with search
- Excel/CSV/PDF export capabilities
- Container quantity calculations using QPC

### SFTP Integration (Development Ready)
- SFTP client using phpseclib library
- File monitoring and automated processing
- Inbox/Outbox/Archive directory structure
- Error handling and logging

## Development Database Schema

### Main Tables
- **delivery_schedules**: Core delivery data from EDI/TSV
- **part_master**: Parts with QPC and description data
- **edi_transactions**: Processing audit trail
- **trading_partners**: Customer configurations
- **ship_to_locations**: Location code mappings

## Production Deployment Notes
- Ready for GreenGeeks shared hosting
- Uses phpseclib (no SSH2 extension required)
- Environment-configurable for dev/production
- Nifco SFTP credentials to be added in production

## Current Status
- ✅ Core EDI processing working
- ✅ Web interface functional
- ✅ Database schema complete
- ✅ SFTP integration coded and tested locally
- ⏳ Part Master interface enhancements
- ⏳ Advanced reporting features

## Directory Structure
```
edimodule/
├── src/
│   ├── classes/          # Core PHP classes
│   ├── config/           # Database and app configuration  
│   ├── public/           # Web entry point
│   └── templates/        # Web interface templates
├── data/                 # SFTP file directories
├── vendor/               # Composer dependencies
├── logs/                 # Application logs
└── edimodule_complete_backup.sql  # Database backup
```

This is a complete, working EDI processing system ready for continued development in your home environment.