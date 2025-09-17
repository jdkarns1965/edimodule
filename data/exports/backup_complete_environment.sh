#!/bin/bash

# Complete EDI Environment Backup Script
# Creates a comprehensive backup of database, files, and configuration

echo "=== EDI Processing Complete Environment Backup ==="
echo "Creating comprehensive backup including:"
echo "- Database dump with all data and structure"
echo "- Configuration files"
echo "- Data directory (processed files, logs)"
echo "- Source code (optional)"
echo ""

# Create timestamp for backup
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="edi_backup_$TIMESTAMP"

echo "Creating backup directory: $BACKUP_DIR"
mkdir -p "$BACKUP_DIR"

# 1. Database backup
echo "1. Creating database backup..."
mysqldump -u root -ppassgas1989 --single-transaction --routines --triggers --events --add-drop-database --databases edi_processing > "$BACKUP_DIR/edi_processing_database.sql" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "   ✅ Database backup created"
else
    echo "   ❌ Database backup failed"
    exit 1
fi

# 2. Configuration files
echo "2. Backing up configuration files..."
cp /var/www/html/edimodule/.env "$BACKUP_DIR/.env" 2>/dev/null
cp /var/www/html/edimodule/.env.production.template "$BACKUP_DIR/.env.production.template" 2>/dev/null
cp /var/www/html/edimodule/composer.json "$BACKUP_DIR/composer.json" 2>/dev/null
cp /var/www/html/edimodule/CLAUDE.md "$BACKUP_DIR/CLAUDE.md" 2>/dev/null
echo "   ✅ Configuration files backed up"

# 3. Data directory (excluding exports to avoid recursion)
echo "3. Backing up data directory..."
cp -r /var/www/html/edimodule/data "$BACKUP_DIR/" 2>/dev/null
rm -rf "$BACKUP_DIR/data/exports" 2>/dev/null  # Remove exports to avoid recursion
echo "   ✅ Data directory backed up"

# 4. Copy restore script
echo "4. Adding restore script..."
cp /var/www/html/edimodule/data/exports/restore_database.sh "$BACKUP_DIR/"
chmod +x "$BACKUP_DIR/restore_database.sh"

# 5. Create environment setup guide
cat > "$BACKUP_DIR/RESTORE_INSTRUCTIONS.md" << 'EOF'
# EDI Processing Environment Restore Instructions

## What's Included
- `edi_processing_database.sql` - Complete database backup
- `.env` - Development environment configuration
- `.env.production.template` - Production configuration template
- `data/` - All EDI processing data and logs
- `restore_database.sh` - Automated database restore script

## Quick Restore Steps

### 1. Restore Database
```bash
cd edi_backup_YYYYMMDD_HHMMSS/
./restore_database.sh
```

### 2. Set Up New Environment
```bash
# Copy configuration
cp .env /path/to/new/edimodule/.env

# Copy data directory
cp -r data/ /path/to/new/edimodule/

# Update paths in .env file for your new environment
```

### 3. Install Dependencies
```bash
cd /path/to/new/edimodule/
composer install
```

### 4. Update Configuration
Edit `.env` file to match your new environment:
- Update file paths
- Update SFTP credentials if needed
- Update database connection details

### 5. Test Installation
Navigate to your web interface and verify:
- Database connection works
- SFTP configuration loads
- File processing directories are accessible

## Database Schema
The backup includes these tables:
- delivery_schedules
- edi_transactions  
- part_location_mapping
- part_master
- ship_to_locations
- shipment_items
- shipments
- trading_partners

## Important Notes
- All passwords in .env are from development environment
- Update SFTP_PASSWORD for your new environment
- Ensure proper directory permissions on data/ folder
- Check file paths match your new server structure
EOF

echo "   ✅ Restore instructions created"

# 6. Create archive
echo "5. Creating compressed archive..."
tar -czf "${BACKUP_DIR}.tar.gz" "$BACKUP_DIR/"
echo "   ✅ Compressed archive created: ${BACKUP_DIR}.tar.gz"

# Show summary
echo ""
echo "=== Backup Complete ==="
echo "Backup location: $(pwd)/${BACKUP_DIR}.tar.gz"
echo "Size: $(du -h ${BACKUP_DIR}.tar.gz | cut -f1)"
echo ""
echo "Contents:"
echo "- Database: $(wc -l < $BACKUP_DIR/edi_processing_database.sql) lines"
echo "- Data files: $(find $BACKUP_DIR/data -type f | wc -l) files"
echo "- Configuration: .env, composer.json, CLAUDE.md"
echo "- Restore script: restore_database.sh"
echo ""
echo "To restore on your home environment:"
echo "1. Extract: tar -xzf ${BACKUP_DIR}.tar.gz"
echo "2. Follow: RESTORE_INSTRUCTIONS.md"
echo ""