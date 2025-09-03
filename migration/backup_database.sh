#!/bin/bash
# Database Backup Script for EDI Module Migration
# Usage: ./backup_database.sh [environment_name]

set -e  # Exit on any error

# Configuration
DB_NAME="edi_processing"
BACKUP_DIR="./backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
ENV_NAME=${1:-"dev"}
BACKUP_FILE="${BACKUP_DIR}/edi_backup_${ENV_NAME}_${TIMESTAMP}.sql"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}EDI Module Database Backup Utility${NC}"
echo "Environment: ${ENV_NAME}"
echo "Target: ${BACKUP_FILE}"
echo "----------------------------------------"

# Create backup directory if it doesn't exist
mkdir -p "${BACKUP_DIR}"

# Check if database exists
if ! mysql -e "USE ${DB_NAME};" 2>/dev/null; then
    echo -e "${RED}Error: Database '${DB_NAME}' not found!${NC}"
    echo "Please ensure the database exists before running backup."
    exit 1
fi

echo -e "${YELLOW}Starting database backup...${NC}"

# Create comprehensive backup with:
# - Complete database structure
# - All data including BLOB fields
# - Stored procedures and functions
# - Triggers
# - Views
# - User privileges specific to this database
mysqldump \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --hex-blob \
    --add-drop-database \
    --databases "${DB_NAME}" \
    > "${BACKUP_FILE}"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database backup completed successfully${NC}"
    echo "Backup file: ${BACKUP_FILE}"
    
    # Get file size
    BACKUP_SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
    echo "Backup size: ${BACKUP_SIZE}"
    
    # Create a metadata file
    cat > "${BACKUP_FILE}.meta" << EOF
EDI Module Database Backup Metadata
=====================================
Database: ${DB_NAME}
Environment: ${ENV_NAME}
Timestamp: ${TIMESTAMP}
Backup File: $(basename "${BACKUP_FILE}")
File Size: ${BACKUP_SIZE}
MySQL Version: $(mysql --version)
Host: $(hostname)
Created By: $(whoami)
Date: $(date)

Tables Included:
$(mysql -e "USE ${DB_NAME}; SHOW TABLES;" | tail -n +2 | sed 's/^/- /')

Record Counts:
$(mysql -e "
USE ${DB_NAME};
SELECT 'trading_partners' as table_name, COUNT(*) as record_count FROM trading_partners
UNION ALL
SELECT 'delivery_schedules' as table_name, COUNT(*) as record_count FROM delivery_schedules
UNION ALL
SELECT 'edi_transactions' as table_name, COUNT(*) as record_count FROM edi_transactions
UNION ALL
SELECT 'ship_to_locations' as table_name, COUNT(*) as record_count FROM ship_to_locations
UNION ALL
SELECT 'shipments' as table_name, COUNT(*) as record_count FROM shipments
UNION ALL
SELECT 'shipment_items' as table_name, COUNT(*) as record_count FROM shipment_items;
" | column -t)
EOF
    
    echo -e "${GREEN}✓ Metadata file created: ${BACKUP_FILE}.meta${NC}"
else
    echo -e "${RED}✗ Database backup failed!${NC}"
    exit 1
fi

echo "----------------------------------------"
echo -e "${GREEN}Backup process completed successfully!${NC}"
echo ""
echo "Next steps:"
echo "1. Copy both files to your target environment:"
echo "   - ${BACKUP_FILE}"
echo "   - ${BACKUP_FILE}.meta"
echo "2. Run restore_database.sh on the target system"