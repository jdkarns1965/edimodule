#!/bin/bash

# EDI Processing Database Restore Script
# This script will restore the complete database backup to your home environment

echo "=== EDI Processing Database Restore Script ==="
echo "This script will restore the complete database backup including:"
echo "- Database structure (tables, indexes, constraints)"
echo "- All data (delivery schedules, EDI transactions, etc.)"
echo "- Stored procedures, triggers, and events"
echo ""

# Find the most recent backup file
BACKUP_FILE=$(ls -t edi_processing_complete_backup_*.sql 2>/dev/null | head -n1)

if [ -z "$BACKUP_FILE" ]; then
    echo "ERROR: No backup file found! Expected pattern: edi_processing_complete_backup_*.sql"
    echo "Please ensure the backup file is in this directory."
    exit 1
fi

echo "Found backup file: $BACKUP_FILE"
echo ""

# Get database connection details
read -p "Enter MySQL host [localhost]: " DB_HOST
DB_HOST=${DB_HOST:-localhost}

read -p "Enter MySQL port [3306]: " DB_PORT
DB_PORT=${DB_PORT:-3306}

read -p "Enter MySQL username [root]: " DB_USER
DB_USER=${DB_USER:-root}

read -s -p "Enter MySQL password: " DB_PASS
echo ""

# Test connection
echo "Testing database connection..."
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1;" >/dev/null 2>&1

if [ $? -ne 0 ]; then
    echo "ERROR: Cannot connect to MySQL server!"
    echo "Please check your connection details and try again."
    exit 1
fi

echo "Connection successful!"
echo ""

# Check if database exists and warn user
DB_EXISTS=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "SHOW DATABASES LIKE 'edi_processing';" 2>/dev/null | grep edi_processing)

if [ ! -z "$DB_EXISTS" ]; then
    echo "WARNING: Database 'edi_processing' already exists!"
    read -p "Do you want to DROP and recreate it? (y/N): " CONFIRM
    if [[ ! $CONFIRM =~ ^[Yy]$ ]]; then
        echo "Restore cancelled."
        exit 0
    fi
fi

echo "Restoring database from: $BACKUP_FILE"
echo "This may take a few minutes..."

# Restore the database
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" < "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ SUCCESS: Database restored successfully!"
    echo ""
    echo "Database: edi_processing"
    echo "Tables restored:"
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "USE edi_processing; SHOW TABLES;" 2>/dev/null
    echo ""
    echo "Next steps:"
    echo "1. Update your .env file with the database connection details:"
    echo "   DB_HOST=$DB_HOST"
    echo "   DB_PORT=$DB_PORT"
    echo "   DB_DATABASE=edi_processing"
    echo "   DB_USERNAME=$DB_USER"
    echo "   DB_PASSWORD=your_password"
    echo ""
    echo "2. Copy the entire /var/www/html/edimodule/data/ directory to preserve:"
    echo "   - SFTP file processing directories"
    echo "   - Processed EDI files"
    echo "   - Log files"
    echo ""
    echo "3. Update SFTP paths in .env for your home environment"
else
    echo ""
    echo "❌ ERROR: Database restore failed!"
    echo "Please check the error messages above."
    exit 1
fi