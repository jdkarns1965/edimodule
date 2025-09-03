#!/bin/bash
# Database Restore Script for EDI Module Migration
# Usage: ./restore_database.sh <backup_file> [target_db_name]

set -e  # Exit on any error

# Configuration
BACKUP_FILE=$1
TARGET_DB=${2:-"edi_processing"}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${YELLOW}EDI Module Database Restore Utility${NC}"
echo "----------------------------------------"

# Validate arguments
if [ -z "${BACKUP_FILE}" ]; then
    echo -e "${RED}Error: Backup file path required${NC}"
    echo "Usage: $0 <backup_file> [target_db_name]"
    echo "Example: $0 ./backups/edi_backup_work_20240903_143000.sql"
    exit 1
fi

if [ ! -f "${BACKUP_FILE}" ]; then
    echo -e "${RED}Error: Backup file '${BACKUP_FILE}' not found!${NC}"
    exit 1
fi

# Check if metadata file exists and display info
META_FILE="${BACKUP_FILE}.meta"
if [ -f "${META_FILE}" ]; then
    echo -e "${BLUE}Backup Information:${NC}"
    head -20 "${META_FILE}" | grep -E "(Database|Environment|Timestamp|File Size):" | sed 's/^/  /'
    echo ""
fi

# Confirm before proceeding
echo -e "${YELLOW}WARNING: This will completely replace the '${TARGET_DB}' database!${NC}"
echo "Backup file: ${BACKUP_FILE}"
echo "Target database: ${TARGET_DB}"
echo ""
read -p "Are you sure you want to continue? (yes/no): " -r
if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "Restore cancelled."
    exit 0
fi

echo ""
echo -e "${YELLOW}Starting database restore...${NC}"

# Check MySQL connection
if ! mysql -e "SELECT 1;" >/dev/null 2>&1; then
    echo -e "${RED}Error: Cannot connect to MySQL server!${NC}"
    echo "Please check your MySQL connection and credentials."
    exit 1
fi

# Create a pre-restore backup if target database exists
if mysql -e "USE ${TARGET_DB};" 2>/dev/null; then
    PRE_BACKUP="./backups/pre_restore_backup_$(date +%Y%m%d_%H%M%S).sql"
    mkdir -p ./backups
    echo -e "${YELLOW}Creating pre-restore backup: ${PRE_BACKUP}${NC}"
    mysqldump --single-transaction --databases "${TARGET_DB}" > "${PRE_BACKUP}"
    echo -e "${GREEN}✓ Pre-restore backup created${NC}"
fi

# Restore the database
echo -e "${YELLOW}Restoring database from backup...${NC}"
mysql < "${BACKUP_FILE}"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database restore completed successfully${NC}"
else
    echo -e "${RED}✗ Database restore failed!${NC}"
    if [ -n "${PRE_BACKUP}" ]; then
        echo "You can restore your previous database using:"
        echo "mysql < ${PRE_BACKUP}"
    fi
    exit 1
fi

# Verify the restore
echo -e "${YELLOW}Verifying restored database...${NC}"

# Check if database exists and has tables
TABLE_COUNT=$(mysql -e "USE ${TARGET_DB}; SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${TARGET_DB}';" | tail -n 1)

if [ "${TABLE_COUNT}" -gt 0 ]; then
    echo -e "${GREEN}✓ Database verification successful${NC}"
    echo "  Tables restored: ${TABLE_COUNT}"
    
    # Show table counts
    echo ""
    echo -e "${BLUE}Record counts after restore:${NC}"
    mysql -e "
    USE ${TARGET_DB};
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
    " | column -t | sed 's/^/  /'
else
    echo -e "${RED}✗ Database verification failed - no tables found${NC}"
    exit 1
fi

echo ""
echo "----------------------------------------"
echo -e "${GREEN}Database restore completed successfully!${NC}"
echo ""
echo "Next steps:"
echo "1. Update your environment configuration (.env file)"
echo "2. Test database connectivity with your application"
echo "3. Verify SFTP settings for your environment"