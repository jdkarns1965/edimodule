#!/bin/bash

# Home Environment Setup Verification Script
# Run this script after setting up the EDI environment at home to verify everything works

echo "=========================================="
echo "EDI Processing Home Environment Verification"
echo "=========================================="
echo ""

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

SUCCESS=0
WARNINGS=0
ERRORS=0

# Function to check and report status
check_status() {
    local description="$1"
    local command="$2"
    local critical="$3"  # "true" if this is critical
    
    echo -n "Checking $description... "
    
    if eval "$command" >/dev/null 2>&1; then
        echo -e "${GREEN}✅ PASS${NC}"
        ((SUCCESS++))
        return 0
    else
        if [ "$critical" = "true" ]; then
            echo -e "${RED}❌ FAIL (Critical)${NC}"
            ((ERRORS++))
        else
            echo -e "${YELLOW}⚠️  WARN${NC}"
            ((WARNINGS++))
        fi
        return 1
    fi
}

# Function to check file exists and is readable
check_file() {
    local description="$1"
    local file_path="$2"
    local critical="$3"
    
    check_status "$description" "[ -r '$file_path' ]" "$critical"
}

# Function to check directory exists and is writable
check_directory() {
    local description="$1"
    local dir_path="$2"
    local critical="$3"
    
    check_status "$description" "[ -d '$dir_path' ] && [ -w '$dir_path' ]" "$critical"
}

echo "🔍 Starting verification checks..."
echo ""

# 1. Basic Environment Checks
echo "📋 1. BASIC ENVIRONMENT"
check_status "PHP version (8.1+)" "php -v | grep -E 'PHP [8-9]\.[1-9]'" "true"
check_status "MySQL/MariaDB available" "which mysql" "true"
check_status "Composer available" "which composer" "true"
check_status "Current directory is EDI module" "[ -f 'composer.json' ] && [ -f 'CLAUDE.md' ]" "true"

echo ""

# 2. Database Checks
echo "📊 2. DATABASE CONNECTION"
if [ -f ".env" ]; then
    # Source .env file to get database credentials
    export $(grep -v '^#' .env | xargs)
    
    check_status "Database connection" "mysql -h${DB_HOST:-localhost} -u${DB_USERNAME:-root} -p${DB_PASSWORD} -e 'SELECT 1;'" "true"
    check_status "edi_processing database exists" "mysql -h${DB_HOST:-localhost} -u${DB_USERNAME:-root} -p${DB_PASSWORD} -e 'USE edi_processing; SELECT 1;'" "true"
    
    # Check specific tables
    if mysql -h${DB_HOST:-localhost} -u${DB_USERNAME:-root} -p${DB_PASSWORD} -e 'USE edi_processing; SELECT 1;' >/dev/null 2>&1; then
        TABLES=("delivery_schedules" "edi_transactions" "part_master" "trading_partners" "ship_to_locations")
        for table in "${TABLES[@]}"; do
            check_status "Table: $table" "mysql -h${DB_HOST:-localhost} -u${DB_USERNAME:-root} -p${DB_PASSWORD} -e 'USE edi_processing; SELECT 1 FROM $table LIMIT 1;'" "true"
        done
    fi
else
    echo -e "${RED}❌ .env file not found - cannot test database${NC}"
    ((ERRORS++))
fi

echo ""

# 3. File Structure Checks
echo "📁 3. FILE STRUCTURE"
check_file "Main index file" "src/public/index.php" "true"
check_file "Bootstrap file" "bootstrap.php" "true"
check_file "Environment template" ".env.production.template" "false"
check_file "Database restore script" "data/exports/restore_database.sh" "false"

echo ""

# 4. Directory Permissions
echo "📂 4. DIRECTORY PERMISSIONS"
check_directory "Data inbox" "data/inbox" "true"
check_directory "Data outbox" "data/outbox" "true"
check_directory "Data processed" "data/processed" "true"
check_directory "Data error" "data/error" "true"
check_directory "Data archive" "data/archive" "true"
check_directory "Logs directory" "logs" "true"

echo ""

# 5. Composer Dependencies
echo "📦 5. DEPENDENCIES"
check_status "Vendor directory exists" "[ -d 'vendor' ]" "true"
check_status "Composer autoload works" "php -r 'require \"vendor/autoload.php\"; echo \"OK\";'" "true"

if [ -f "composer.json" ]; then
    # Check key dependencies
    check_status "phpseclib/phpseclib installed" "php -r 'require \"vendor/autoload.php\"; class_exists(\"phpseclib3\\\\Net\\\\SFTP\");'" "true"
    check_status "vlucas/phpdotenv installed" "php -r 'require \"vendor/autoload.php\"; class_exists(\"Dotenv\\\\Dotenv\");'" "true"
fi

echo ""

# 6. SFTP Configuration (if enabled)
echo "🔐 6. SFTP CONFIGURATION"
if [ -f ".env" ] && grep -q "SFTP_ENABLED=true" .env; then
    export $(grep -v '^#' .env | xargs)
    
    check_file "SFTP inbox path exists" "${SFTP_INBOX_PATH:-data/inbox}" "true"
    check_file "SFTP outbox path exists" "${SFTP_OUTBOX_PATH:-data/outbox}" "true"
    
    # Test SFTP connection (non-critical since it depends on external server)
    echo -n "Testing SFTP connection to ${SFTP_HOST:-localhost}... "
    if timeout 10 php -r "
        require 'vendor/autoload.php';
        require 'bootstrap.php';
        require 'src/classes/SFTPClient.php';
        \$client = new Greenfield\EDI\SFTPClient();
        if (\$client->connect()) {
            echo 'OK';
            \$client->disconnect();
            exit(0);
        } else {
            exit(1);
        }
    " >/dev/null 2>&1; then
        echo -e "${GREEN}✅ PASS${NC}"
        ((SUCCESS++))
    else
        echo -e "${YELLOW}⚠️  WARN (Check SFTP credentials)${NC}"
        ((WARNINGS++))
    fi
else
    echo -e "${YELLOW}⚠️  SFTP not enabled or .env missing${NC}"
    ((WARNINGS++))
fi

echo ""

# 7. Web Interface Test
echo "🌐 7. WEB INTERFACE"
echo -n "Testing web interface bootstrap... "
if php -r "
    require 'vendor/autoload.php';
    require 'bootstrap.php';
    echo 'OK';
" >/dev/null 2>&1; then
    echo -e "${GREEN}✅ PASS${NC}"
    ((SUCCESS++))
else
    echo -e "${RED}❌ FAIL${NC}"
    ((ERRORS++))
fi

# Check if web server is running (common configurations)
check_status "Web server (Apache/Nginx) running" "pgrep -x apache2 || pgrep -x nginx || pgrep -x httpd" "false"

echo ""

# 8. Sample Data Verification
echo "📋 8. SAMPLE DATA"
if mysql -h${DB_HOST:-localhost} -u${DB_USERNAME:-root} -p${DB_PASSWORD} -e 'USE edi_processing; SELECT COUNT(*) FROM delivery_schedules;' >/dev/null 2>&1; then
    RECORD_COUNT=$(mysql -h${DB_HOST:-localhost} -u${DB_USERNAME:-root} -p${DB_PASSWORD} -e 'USE edi_processing; SELECT COUNT(*) FROM delivery_schedules;' 2>/dev/null | tail -n 1)
    if [ "$RECORD_COUNT" -gt 0 ]; then
        echo -e "Sample delivery schedules: ${GREEN}✅ $RECORD_COUNT records${NC}"
        ((SUCCESS++))
    else
        echo -e "Sample delivery schedules: ${YELLOW}⚠️  No records found${NC}"
        ((WARNINGS++))
    fi
else
    echo -e "Sample delivery schedules: ${RED}❌ Cannot check${NC}"
    ((ERRORS++))
fi

echo ""
echo "=========================================="
echo "🎯 VERIFICATION SUMMARY"
echo "=========================================="
echo -e "✅ Successful checks: ${GREEN}$SUCCESS${NC}"
echo -e "⚠️  Warnings: ${YELLOW}$WARNINGS${NC}"
echo -e "❌ Errors: ${RED}$ERRORS${NC}"
echo ""

if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}🎉 SUCCESS: Your EDI environment is ready!${NC}"
    echo ""
    echo "🚀 Next Steps:"
    echo "   1. Access web interface: http://localhost/path/to/edimodule/src/public/"
    echo "   2. Test SFTP dashboard functionality"
    echo "   3. Verify file processing works"
    echo "   4. Import sample data if needed"
    echo ""
    echo "📚 Documentation: data/exports/RESTORE_INSTRUCTIONS.md"
    exit 0
elif [ $ERRORS -lt 3 ] && [ $SUCCESS -gt 10 ]; then
    echo -e "${YELLOW}⚠️  MOSTLY READY: Minor issues found${NC}"
    echo ""
    echo "🔧 Recommended Actions:"
    [ $WARNINGS -gt 0 ] && echo "   - Review warnings above"
    [ -f ".env" ] || echo "   - Create .env file from .env.production.template"
    echo "   - Check file permissions: chmod -R 755 data/ logs/"
    echo "   - Verify database credentials"
    echo ""
    exit 1
else
    echo -e "${RED}❌ SETUP INCOMPLETE: Critical issues found${NC}"
    echo ""
    echo "🔧 Required Actions:"
    echo "   - Fix database connection issues"
    echo "   - Install missing dependencies: composer install"
    echo "   - Create and configure .env file"
    echo "   - Set proper directory permissions"
    echo ""
    echo "📚 See: data/exports/RESTORE_INSTRUCTIONS.md"
    exit 2
fi