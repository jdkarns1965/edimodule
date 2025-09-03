#!/bin/bash
# File Synchronization Script for EDI Module Migration
# Usage: ./sync_files.sh <source_env> <target_env>

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SOURCE_ENV=$1
TARGET_ENV=$2

echo -e "${YELLOW}EDI Module File Sync Utility${NC}"
echo "----------------------------------------"

if [ -z "$SOURCE_ENV" ] || [ -z "$TARGET_ENV" ]; then
    echo -e "${RED}Error: Source and target environments required${NC}"
    echo "Usage: $0 <source_env> <target_env>"
    echo "Example: $0 work home"
    exit 1
fi

# Define what to sync and what to exclude
SYNC_ITEMS=(
    "src/"
    "data/processed/"
    "data/archive/"
    "sql/"
    "migration/"
    "logs/"
    "composer.json"
    "composer.lock"
    "bootstrap.php"
    "*.php"
    "*.sql"
    "*.md"
)

EXCLUDE_ITEMS=(
    "vendor/"
    "data/inbox/*"
    "data/outbox/*" 
    "data/error/*"
    ".env"
    ".env.*"
    "logs/*.log"
    "backups/"
    ".git/"
    "*.tmp"
    "*.bak"
)

echo "Source Environment: ${SOURCE_ENV}"
echo "Target Environment: ${TARGET_ENV}"
echo ""

# Create sync manifest
SYNC_MANIFEST="./migration/sync_manifest_${SOURCE_ENV}_to_${TARGET_ENV}_$(date +%Y%m%d_%H%M%S).txt"

echo -e "${YELLOW}Creating sync manifest...${NC}"
cat > "${SYNC_MANIFEST}" << EOF
EDI Module File Sync Manifest
==============================
Source: ${SOURCE_ENV}
Target: ${TARGET_ENV}
Date: $(date)
Created by: $(whoami)

Files to sync:
EOF

# List files to be synced
for item in "${SYNC_ITEMS[@]}"; do
    if [[ "$item" == *"/" ]]; then
        find "$item" -type f 2>/dev/null | head -20 >> "${SYNC_MANIFEST}" || true
        echo "  Directory: $item (contents listed above)" >> "${SYNC_MANIFEST}"
    else
        ls -la $item 2>/dev/null >> "${SYNC_MANIFEST}" || echo "  File: $item (not found)" >> "${SYNC_MANIFEST}"
    fi
done

echo "" >> "${SYNC_MANIFEST}"
echo "Files to exclude:" >> "${SYNC_MANIFEST}"
for item in "${EXCLUDE_ITEMS[@]}"; do
    echo "  - $item" >> "${SYNC_MANIFEST}"
done

echo -e "${GREEN}✓ Sync manifest created: ${SYNC_MANIFEST}${NC}"

# Create tar archive excluding specified items
ARCHIVE_NAME="edi_sync_${SOURCE_ENV}_to_${TARGET_ENV}_$(date +%Y%m%d_%H%M%S).tar.gz"

echo -e "${YELLOW}Creating sync archive...${NC}"

# Build tar exclude parameters
EXCLUDE_PARAMS=""
for item in "${EXCLUDE_ITEMS[@]}"; do
    EXCLUDE_PARAMS="$EXCLUDE_PARAMS --exclude=$item"
done

tar -czf "$ARCHIVE_NAME" $EXCLUDE_PARAMS \
    --exclude="$ARCHIVE_NAME" \
    --exclude="*.tar.gz" \
    .

if [ $? -eq 0 ]; then
    ARCHIVE_SIZE=$(du -h "$ARCHIVE_NAME" | cut -f1)
    echo -e "${GREEN}✓ Sync archive created successfully${NC}"
    echo "  Archive: $ARCHIVE_NAME"
    echo "  Size: $ARCHIVE_SIZE"
else
    echo -e "${RED}✗ Archive creation failed${NC}"
    exit 1
fi

# Create extraction script
EXTRACT_SCRIPT="extract_sync_${TARGET_ENV}.sh"
cat > "$EXTRACT_SCRIPT" << 'EOF'
#!/bin/bash
# Auto-generated extraction script

set -e

YELLOW='\033[1;33m'
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

ARCHIVE_FILE=$1

if [ -z "$ARCHIVE_FILE" ]; then
    echo -e "${RED}Error: Archive file required${NC}"
    echo "Usage: $0 <archive_file>"
    exit 1
fi

if [ ! -f "$ARCHIVE_FILE" ]; then
    echo -e "${RED}Error: Archive file '$ARCHIVE_FILE' not found${NC}"
    exit 1
fi

echo -e "${YELLOW}Extracting EDI Module sync archive...${NC}"
echo "Archive: $ARCHIVE_FILE"
echo "Target: $(pwd)"
echo ""

read -p "Extract to current directory? (yes/no): " -r
if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "Extraction cancelled."
    exit 0
fi

# Create backup of current .env if it exists
if [ -f ".env" ]; then
    cp ".env" ".env.backup.$(date +%Y%m%d_%H%M%S)"
    echo -e "${GREEN}✓ Current .env backed up${NC}"
fi

# Extract archive
tar -xzf "$ARCHIVE_FILE"

echo -e "${GREEN}✓ Extraction completed${NC}"
echo ""
echo "Next steps:"
echo "1. Review and update .env configuration"
echo "2. Run composer install to update dependencies"
echo "3. Restore database backup if needed"
echo "4. Test application functionality"
EOF

chmod +x "$EXTRACT_SCRIPT"

echo ""
echo "----------------------------------------"
echo -e "${GREEN}File sync package created successfully!${NC}"
echo ""
echo "Package contents:"
echo "  - $ARCHIVE_NAME (sync archive)"
echo "  - $EXTRACT_SCRIPT (extraction script)"  
echo "  - $SYNC_MANIFEST (sync manifest)"
echo ""
echo "Transfer these files to your target environment and run:"
echo "  ./$EXTRACT_SCRIPT $ARCHIVE_NAME"