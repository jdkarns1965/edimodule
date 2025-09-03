#!/bin/bash
# Environment Configuration Template Generator
# Creates environment-specific .env templates

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

ENV_NAME=$1

echo -e "${YELLOW}EDI Module Environment Template Generator${NC}"
echo "----------------------------------------"

if [ -z "$ENV_NAME" ]; then
    echo -e "${RED}Error: Environment name required${NC}"
    echo "Usage: $0 <environment_name>"
    echo "Examples: work, home, production, testing"
    exit 1
fi

TEMPLATE_FILE=".env.${ENV_NAME}.template"

echo "Creating template for environment: ${ENV_NAME}"
echo "Template file: ${TEMPLATE_FILE}"
echo ""

# Create environment template
cat > "$TEMPLATE_FILE" << EOF
# EDI Module Environment Configuration - ${ENV_NAME^^}
# Generated on: $(date)
# Copy this file to .env and update values for your environment

# =============================================================================
# DATABASE CONFIGURATION
# =============================================================================
DB_HOST=localhost
DB_NAME=edi_processing
DB_USER=your_username
DB_PASSWORD=your_password
DB_PORT=3306

# =============================================================================
# SFTP CONFIGURATION
# =============================================================================
SFTP_ENABLED=true
SFTP_LIBRARY=phpseclib
SFTP_TIMEOUT=30

# SFTP Connection Settings
EOF

# Add environment-specific SFTP settings
case "$ENV_NAME" in
    "work")
        cat >> "$TEMPLATE_FILE" << EOF
SFTP_HOST=localhost
SFTP_PORT=22  
SFTP_USERNAME=jdkarns
SFTP_PASSWORD=your_work_password
EOF
        ;;
    "home")
        cat >> "$TEMPLATE_FILE" << EOF
SFTP_HOST=localhost
SFTP_PORT=2022
SFTP_USERNAME=homeuser
SFTP_PASSWORD=your_home_password
EOF
        ;;
    "production")
        cat >> "$TEMPLATE_FILE" << EOF
SFTP_HOST=edi.nifco.com
SFTP_PORT=22
SFTP_USERNAME=greenfield_plastics_edi
SFTP_PASSWORD=provided_by_nifco
EOF
        ;;
    *)
        cat >> "$TEMPLATE_FILE" << EOF
SFTP_HOST=localhost
SFTP_PORT=22
SFTP_USERNAME=your_username
SFTP_PASSWORD=your_password
EOF
        ;;
esac

# Add common SFTP paths
cat >> "$TEMPLATE_FILE" << EOF

# SFTP Directory Paths (adjust for your environment)
SFTP_INBOX_PATH=/var/www/html/edimodule/data/inbox
SFTP_OUTBOX_PATH=/var/www/html/edimodule/data/outbox  
SFTP_PROCESSED_PATH=/var/www/html/edimodule/data/processed
SFTP_ERROR_PATH=/var/www/html/edimodule/data/error

# Remote SFTP paths (for production)
SFTP_REMOTE_INBOX=/incoming
SFTP_REMOTE_OUTBOX=/outgoing
SFTP_REMOTE_ARCHIVE=/archive

# =============================================================================
# APPLICATION CONFIGURATION  
# =============================================================================
APP_ENV=${ENV_NAME}
APP_DEBUG=true
APP_TIMEZONE=America/New_York

# =============================================================================
# LOGGING CONFIGURATION
# =============================================================================
LOG_FILE_PATH=/var/www/html/edimodule/logs/edi.log
LOG_LEVEL=INFO
LOG_MAX_SIZE=10MB
LOG_MAX_FILES=5

# =============================================================================  
# SECURITY CONFIGURATION
# =============================================================================
ENCRYPTION_KEY=generate_random_32_character_key_here
SESSION_TIMEOUT=3600
CSRF_TOKEN_EXPIRY=1800

# =============================================================================
# EDI PROCESSING CONFIGURATION
# =============================================================================
EDI_BATCH_SIZE=100
EDI_MAX_FILE_SIZE=50MB
EDI_RETRY_ATTEMPTS=3
EDI_RETRY_DELAY=300

# File Processing Settings
PROCESS_INTERVAL=300
AUTO_PROCESS_ENABLED=true
BACKUP_RETENTION_DAYS=30

# =============================================================================
# NOTIFICATION CONFIGURATION
# =============================================================================
MAIL_ENABLED=false
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_FROM_ADDRESS=noreply@yourcompany.com
MAIL_FROM_NAME="EDI Processing System"

# Error Notification Recipients (comma-separated)
ERROR_NOTIFY_EMAILS=admin@yourcompany.com,it@yourcompany.com

# =============================================================================
# DEVELOPMENT SETTINGS (remove for production)
# =============================================================================
EOF

if [ "$ENV_NAME" != "production" ]; then
    cat >> "$TEMPLATE_FILE" << EOF
# Development-only settings
DEV_MODE=true
SHOW_ERRORS=true
ENABLE_QUERY_LOG=true
MOCK_SFTP=false
TEST_DATA_ENABLED=true
EOF
fi

# Create setup instructions
SETUP_FILE="setup_${ENV_NAME}_environment.md"

cat > "$SETUP_FILE" << EOF
# ${ENV_NAME^^} Environment Setup Instructions

## Prerequisites
- MySQL 8.0+ installed and running
- PHP 8.1+ with required extensions
- Composer installed
- SFTP server access (if using real SFTP)

## Setup Steps

### 1. Database Setup
\`\`\`bash
# Create database
mysql -e "CREATE DATABASE IF NOT EXISTS edi_processing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Create database user (optional)  
mysql -e "CREATE USER IF NOT EXISTS 'edi_user'@'localhost' IDENTIFIED BY 'secure_password';"
mysql -e "GRANT ALL PRIVILEGES ON edi_processing.* TO 'edi_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
\`\`\`

### 2. Environment Configuration
\`\`\`bash
# Copy template to .env
cp .env.${ENV_NAME}.template .env

# Edit .env with your specific settings
nano .env
\`\`\`

### 3. Install Dependencies
\`\`\`bash
composer install --no-dev
\`\`\`

### 4. Database Migration
\`\`\`bash
# If restoring from backup:
./migration/restore_database.sh backups/your_backup.sql

# If creating fresh database:
mysql edi_processing < database_schema.sql
\`\`\`

### 5. Directory Setup
\`\`\`bash
# Create required directories
mkdir -p data/{inbox,outbox,processed,error,archive}
mkdir -p logs
mkdir -p backups

# Set permissions
chmod 755 data/
chmod 755 logs/
chmod 755 backups/
\`\`\`

### 6. SFTP Configuration
EOF

case "$ENV_NAME" in
    "work")
        cat >> "$SETUP_FILE" << EOF
- SFTP server should be running on localhost:22
- User 'jdkarns' should have access
- Test connection: \`ssh jdkarns@localhost\`
EOF
        ;;
    "home")
        cat >> "$SETUP_FILE" << EOF
- Set up local SFTP server on port 2022 (to avoid conflicts)
- Create 'homeuser' account with appropriate permissions
- Test connection: \`ssh -p 2022 homeuser@localhost\`
EOF
        ;;
    "production")
        cat >> "$SETUP_FILE" << EOF
- Use credentials provided by Nifco
- Test connection carefully in test mode first
- Ensure firewall allows outbound connections to edi.nifco.com:22
EOF
        ;;
esac

cat >> "$SETUP_FILE" << EOF

### 7. Testing
\`\`\`bash
# Test database connection
php test_schedules_direct.php

# Test SFTP connection  
php test_sftp_integration.php

# Test web interface
php test_web_interface.php
\`\`\`

## Environment-Specific Notes

### Database Settings
- Host: Update based on your MySQL setup
- Credentials: Use environment-specific database users
- Port: Standard 3306 or custom port

### SFTP Settings
- Adjust paths to match your directory structure
- Update credentials for your environment
- Test connectivity before enabling auto-processing

### Logging
- Ensure log directory is writable
- Adjust log levels for your needs (DEBUG for dev, INFO for prod)
- Monitor disk space for log files

## Troubleshooting

### Common Issues
1. **Permission Denied**: Check directory permissions (755 for dirs, 644 for files)
2. **SFTP Connection Failed**: Verify credentials and network connectivity  
3. **Database Connection**: Check MySQL service and credentials
4. **Composer Issues**: Run \`composer clear-cache\` and retry

### Log Files
- Application logs: \`logs/edi.log\`
- SFTP logs: \`logs/sftp.log\`  
- PHP errors: Check Apache/Nginx error logs

## Security Checklist
- [ ] Change default passwords
- [ ] Generate unique encryption key
- [ ] Restrict file permissions
- [ ] Enable HTTPS in production
- [ ] Configure firewall rules
- [ ] Set up log rotation
- [ ] Enable database backups
EOF

echo -e "${GREEN}✓ Environment template created: ${TEMPLATE_FILE}${NC}"
echo -e "${GREEN}✓ Setup instructions created: ${SETUP_FILE}${NC}"

echo ""
echo "Next steps:"
echo "1. Review and customize ${TEMPLATE_FILE}"
echo "2. Copy to .env and update with your settings"  
echo "3. Follow instructions in ${SETUP_FILE}"