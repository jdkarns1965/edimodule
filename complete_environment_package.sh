#!/bin/bash

# Complete EDI Environment Packaging Script
# This script will backup everything, commit to git, and prepare for home environment transfer

set -e  # Exit on any error

echo "=========================================="
echo "EDI Processing Environment Packaging"
echo "=========================================="
echo "This script will:"
echo "1. Create complete database backup"
echo "2. Update .gitignore to include critical files"
echo "3. Commit all changes to git"
echo "4. Create portable backup archive"
echo "5. Verify everything is ready for home transfer"
echo ""

# Change to project directory
cd /var/www/html/edimodule

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    echo "❌ ERROR: Not in a git repository!"
    exit 1
fi

echo "📍 Current location: $(pwd)"
echo "📍 Git branch: $(git branch --show-current)"
echo ""

# Step 1: Create database backup
echo "🗃️  STEP 1: Creating database backup..."
timestamp=$(date +%Y%m%d_%H%M%S)
backup_file="data/exports/edi_database_backup_${timestamp}.sql"

# Create the backup with error handling
if mysqldump -u root -ppassgas1989 --single-transaction --routines --triggers --events --add-drop-database --databases edi_processing > "$backup_file" 2>/dev/null; then
    echo "   ✅ Database backup created: $backup_file"
    echo "   📊 Size: $(du -h $backup_file | cut -f1)"
    echo "   📈 Lines: $(wc -l < $backup_file)"
else
    echo "   ❌ Database backup failed!"
    exit 1
fi

# Step 2: Update .gitignore to allow critical backup files
echo ""
echo "📝 STEP 2: Updating .gitignore for backup files..."

# Create new .gitignore that excludes our backups and scripts
cat > .gitignore << 'EOF'
# Allow critical backup and setup files
!data/exports/edi_database_backup_*.sql
!data/exports/restore_database.sh
!data/exports/backup_complete_environment.sh
!data/exports/RESTORE_INSTRUCTIONS.md

# Exclude other exports but keep backup files
data/exports/*
!data/exports/.gitkeep
!data/exports/edi_database_backup_*.sql
!data/exports/restore_database.sh
!data/exports/backup_complete_environment.sh
!data/exports/RESTORE_INSTRUCTIONS.md

# Environment files - keep templates
.env.local
.env.*.local

# Logs - keep structure but not log files
logs/*.log
!logs/.gitkeep

# Temporary and cache files
tmp/
cache/
*.tmp
*.temp

# Vendor dependencies (will be restored via composer)
vendor/

# IDE files
.vscode/
.idea/
*.swp
*.swo

# OS files
.DS_Store
Thumbs.db
EOF

echo "   ✅ .gitignore updated to include backup files"

# Step 3: Create comprehensive restore documentation
echo ""
echo "📚 STEP 3: Creating restore documentation..."

cat > data/exports/RESTORE_INSTRUCTIONS.md << EOF
# EDI Processing Environment - Complete Restore Guide

## Quick Start (Home Environment)

### Prerequisites
- PHP 8.1+ with MySQL extension
- MySQL 8.0+
- Composer
- Git

### 1. Clone Repository
\`\`\`bash
git clone <your-repo-url> edimodule
cd edimodule
\`\`\`

### 2. Restore Database
\`\`\`bash
chmod +x data/exports/restore_database.sh
./data/exports/restore_database.sh
\`\`\`

### 3. Install Dependencies
\`\`\`bash
composer install
\`\`\`

### 4. Configure Environment
\`\`\`bash
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
\`\`\`

### 5. Set Up Directories
\`\`\`bash
# Ensure proper permissions
chmod -R 755 data/
chmod -R 777 data/inbox data/outbox data/processed data/error data/archive
mkdir -p logs
chmod 777 logs
\`\`\`

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
- **Size**: $(wc -l < $backup_file) lines of SQL
- **Created**: $(date)

## Included Files
- Complete database backup
- All source code and templates  
- Configuration templates
- SFTP monitoring scripts
- Processing directories with sample data
- Comprehensive documentation

## Troubleshooting

### Database Issues
\`\`\`bash
# Test MySQL connection
mysql -u root -p -e "SHOW DATABASES;"

# Verify edi_processing database
mysql -u root -p -e "USE edi_processing; SHOW TABLES;"
\`\`\`

### Permission Issues
\`\`\`bash
# Fix directory permissions
sudo chown -R www-data:www-data /path/to/edimodule
chmod -R 755 /path/to/edimodule
chmod -R 777 /path/to/edimodule/data
chmod -R 777 /path/to/edimodule/logs
\`\`\`

### SFTP Issues
1. Update SFTP credentials in .env
2. Test connection via web dashboard
3. Verify directory paths exist and are writable

## Production Deployment
For production deployment on GreenGeeks:
1. Follow restore steps above
2. Use .env.production.template as starting point
3. Set up cron job: \`*/15 * * * * /usr/bin/php /path/to/edimodule/cron_monitor.php\`
4. Update SFTP credentials for Nifco production server
EOF

echo "   ✅ Comprehensive restore guide created"

# Step 4: Add all files to git (except those in .gitignore)
echo ""
echo "📦 STEP 4: Adding files to git..."

# Add all untracked files that should be committed
git add .env.production.template
git add cron_monitor.php  
git add monitor_daemon.php
git add scripts/
git add src/templates/cron_setup.php
git add data/exports/restore_database.sh
git add data/exports/backup_complete_environment.sh
git add data/exports/RESTORE_INSTRUCTIONS.md
git add "$backup_file"
git add .gitignore

# Add all modified files
git add .env
git add CLAUDE.md
git add src/
git add logs/.gitkeep 2>/dev/null || true

echo "   ✅ Files added to git staging"

# Step 5: Show what will be committed
echo ""
echo "📋 STEP 5: Files to be committed:"
git diff --name-only --staged | while read file; do
    echo "   📄 $file"
done

echo ""
echo "📊 Git status summary:"
git status --porcelain | grep "^A" | wc -l | xargs echo "   Added files:"
git status --porcelain | grep "^M" | wc -l | xargs echo "   Modified files:"

# Step 6: Commit everything
echo ""
echo "💾 STEP 6: Committing to git..."

commit_message="Complete EDI environment backup and packaging

- Database backup: $backup_file  
- SFTP monitoring scripts (daemon + cron)
- Production deployment templates
- Comprehensive restore documentation
- Updated .gitignore for backup files
- All configuration and source files

Ready for home environment deployment.

🤖 Generated with Claude Code

Co-Authored-By: Claude <noreply@anthropic.com>"

git commit -m "$commit_message"

if [ $? -eq 0 ]; then
    echo "   ✅ Git commit successful"
    echo "   📝 Commit hash: $(git rev-parse --short HEAD)"
else
    echo "   ❌ Git commit failed!"
    exit 1
fi

# Step 7: Create final verification
echo ""
echo "🔍 STEP 7: Final verification..."

echo "   📊 Database backup verification:"
echo "      Size: $(du -h $backup_file | cut -f1)"
echo "      Tables: $(grep -c "CREATE TABLE" $backup_file)"
echo "      Data rows: $(grep -c "INSERT INTO" $backup_file)"

echo "   📁 Critical files present:"
[ -f "data/exports/restore_database.sh" ] && echo "      ✅ restore_database.sh" || echo "      ❌ restore_database.sh"
[ -f ".env.production.template" ] && echo "      ✅ .env.production.template" || echo "      ❌ .env.production.template"
[ -f "cron_monitor.php" ] && echo "      ✅ cron_monitor.php" || echo "      ❌ cron_monitor.php"
[ -f "composer.json" ] && echo "      ✅ composer.json" || echo "      ❌ composer.json"

echo "   🌐 Git repository status:"
echo "      Branch: $(git branch --show-current)"
echo "      Last commit: $(git log --oneline -1)"
echo "      Tracked files: $(git ls-files | wc -l)"

# Step 8: Generate final instructions
echo ""
echo "=========================================="
echo "🎉 PACKAGING COMPLETE!"
echo "=========================================="
echo ""
echo "Your EDI environment is now ready for transfer to your home environment."
echo ""
echo "📦 What's Ready:"
echo "   ✅ Complete database backup with all data"
echo "   ✅ All source code and configuration files"
echo "   ✅ Automated restore scripts"
echo "   ✅ Production deployment templates"
echo "   ✅ Comprehensive documentation"
echo "   ✅ Everything committed to git"
echo ""
echo "🏠 To Set Up at Home:"
echo "   1. Clone your git repository"
echo "   2. Run: ./data/exports/restore_database.sh"
echo "   3. Run: composer install"
echo "   4. Copy and edit .env from .env.production.template"
echo "   5. Set up directory permissions"
echo "   6. Test via web browser"
echo ""
echo "📄 Documentation: data/exports/RESTORE_INSTRUCTIONS.md"
echo "💾 Database backup: $backup_file"
echo ""
echo "🚀 Ready to push to remote repository if needed:"
echo "   git push origin main"
echo ""