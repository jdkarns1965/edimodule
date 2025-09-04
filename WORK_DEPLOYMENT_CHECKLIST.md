# Work Environment Deployment Checklist

## Pre-Deployment Preparation 

### Home Development Complete
- [x] All core features implemented and tested
- [x] Excel export permissions fixed
- [x] Database backup created: `migration/current_database_backup_20250903_221400.sql`
- [x] Migration documentation complete
- [x] Git repository ready for transfer

## Work Environment Setup

### 1. Server Prerequisites
- [ ] PHP 8.1+ installed and configured
- [ ] MySQL 8.0+ installed and accessible
- [ ] Apache/Nginx web server configured
- [ ] Composer installed globally
- [ ] Git access configured

### 2. File Transfer & Setup
- [ ] Clone/transfer repository to work server
- [ ] Set proper file permissions: `sudo chown -R www-data:www-data data/ logs/`
- [ ] Set directory permissions: `sudo chmod -R 775 data/ logs/`
- [ ] Install dependencies: `composer install --no-dev --optimize-autoloader`

### 3. Database Configuration
- [ ] Create database: `edi_processing`
- [ ] Create database user with proper privileges
- [ ] Import database backup: `mysql -u user -p edi_processing < migration/current_database_backup_20250903_221400.sql`
- [ ] Update `src/config/database.php` with work environment credentials

### 4. Environment Configuration
- [ ] Copy `.env.example` to `.env`
- [ ] Update SFTP settings for Nifco production (when ready)
- [ ] Verify all directory paths in .env are correct
- [ ] Set `.env` permissions: `chmod 600 .env`

### 5. Web Server Configuration
- [ ] Configure Apache/Nginx virtual host pointing to `src/public/`
- [ ] Enable mod_rewrite (Apache) or equivalent URL rewriting
- [ ] Test web interface accessibility
- [ ] Configure SSL certificate if needed

## Functional Testing

### 6. Core Functionality Tests
- [ ] Database connection: Run `php test_db_connection.php`
- [ ] Web interface loads: Access `/edimodule/src/public/`
- [ ] Login/authentication works
- [ ] Dashboard displays data correctly

### 7. Data Management Tests
- [ ] Delivery schedules page loads with data
- [ ] Search and filtering works
- [ ] Part master management accessible
- [ ] Trading partners and locations visible

### 8. Export System Tests
- [ ] Excel exports work (BOL, packing list, pick list)
- [ ] CSV exports function properly
- [ ] File permissions allow downloads
- [ ] Export files are created in `data/exports/`

### 9. Import System Tests
- [ ] TSV import functionality works
- [ ] CSV bulk import for parts works
- [ ] Data validation and error handling functions
- [ ] Import logs are generated properly

### 10. Shipment Management Tests
- [ ] Shipment builder loads and functions
- [ ] Item selection and quantity updates work
- [ ] Location-based filtering works
- [ ] Export options from shipments work

## Production Readiness

### 11. Security & Performance
- [ ] Remove all test/debug files
- [ ] Secure database passwords updated
- [ ] Log file rotation configured
- [ ] Error reporting appropriate for production
- [ ] File upload limits configured

### 12. SFTP Integration (Future)
- [ ] Test SFTP connection to development server first
- [ ] Configure Nifco production credentials when provided
- [ ] Test file transfer in both directions
- [ ] Verify EDI file processing pipeline

### 13. Monitoring & Maintenance
- [ ] Log monitoring setup
- [ ] Database backup schedule established
- [ ] File cleanup/archival process configured
- [ ] Health check endpoints working

## Rollback Plan

### Emergency Procedures
- [ ] Database backup restoration process tested
- [ ] Source code rollback procedure documented
- [ ] Configuration backup maintained
- [ ] Emergency contact procedures established

## Sign-off

### Development Team
- [ ] Home development testing complete
- [ ] All known issues resolved
- [ ] Documentation complete and accurate
- [ ] Migration package validated

### Work Environment Team
- [ ] Server environment meets requirements
- [ ] All prerequisites installed
- [ ] Configuration reviewed and approved
- [ ] Security requirements met

### Final Deployment
- [ ] All checklist items completed
- [ ] Functional testing passed
- [ ] Performance acceptable
- [ ] Ready for production use

---

## Quick Command Reference

```bash
# File permissions
sudo chown -R www-data:www-data data/ logs/
sudo chmod -R 775 data/ logs/
chmod 600 .env

# Database import
mysql -u edi_user -p edi_processing < migration/current_database_backup_20250903_221400.sql

# Dependencies
composer install --no-dev --optimize-autoloader

# Test connection
php test_db_connection.php
```

## Contact Information
- **Home Dev Environment**: Fully functional, ready for questions
- **Documentation**: All .md files in repository
- **Database**: Full backup with 66 delivery schedules + test data
- **Support Files**: Complete test suite available

**Status**: Ready for Work Environment Deployment 