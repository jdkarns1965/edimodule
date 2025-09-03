#!/bin/bash
# Migration Validation Script
# Validates database schema and data integrity after migration

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DB_NAME=${1:-"edi_processing"}

echo -e "${YELLOW}EDI Module Migration Validation${NC}"
echo "Database: ${DB_NAME}"
echo "----------------------------------------"

VALIDATION_LOG="migration_validation_$(date +%Y%m%d_%H%M%S).log"

# Function to log validation results
log_result() {
    local status=$1
    local message=$2
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    echo "[$timestamp] [$status] $message" >> "$VALIDATION_LOG"
    
    if [ "$status" = "PASS" ]; then
        echo -e "  ${GREEN}✓ $message${NC}"
    elif [ "$status" = "FAIL" ]; then
        echo -e "  ${RED}✗ $message${NC}"
    elif [ "$status" = "WARN" ]; then
        echo -e "  ${YELLOW}! $message${NC}"
    else
        echo -e "  ${BLUE}ℹ $message${NC}"
    fi
}

# Initialize validation log
echo "EDI Module Migration Validation Report" > "$VALIDATION_LOG"
echo "Generated: $(date)" >> "$VALIDATION_LOG"
echo "Database: $DB_NAME" >> "$VALIDATION_LOG"
echo "Host: $(hostname)" >> "$VALIDATION_LOG" 
echo "User: $(whoami)" >> "$VALIDATION_LOG"
echo "=====================================+============================" >> "$VALIDATION_LOG"
echo "" >> "$VALIDATION_LOG"

PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

# Test 1: Database Connection
echo -e "${BLUE}Testing database connectivity...${NC}"
if mysql -e "USE $DB_NAME; SELECT 1;" >/dev/null 2>&1; then
    log_result "PASS" "Database connection successful"
    ((PASS_COUNT++))
else
    log_result "FAIL" "Cannot connect to database '$DB_NAME'"
    ((FAIL_COUNT++))
    echo -e "${RED}Critical error: Cannot proceed with validation${NC}"
    exit 1
fi

# Test 2: Required Tables Exist
echo -e "${BLUE}Validating database schema...${NC}"
REQUIRED_TABLES=(
    "trading_partners"
    "ship_to_locations"
    "edi_transactions"
    "delivery_schedules"
    "shipments"
    "shipment_items"
    "customer_edi_configs"
    "customer_connections"
)

for table in "${REQUIRED_TABLES[@]}"; do
    if mysql -e "USE $DB_NAME; DESCRIBE $table;" >/dev/null 2>&1; then
        log_result "PASS" "Table '$table' exists"
        ((PASS_COUNT++))
    else
        log_result "FAIL" "Table '$table' missing"
        ((FAIL_COUNT++))
    fi
done

# Test 3: Foreign Key Constraints
echo -e "${BLUE}Checking foreign key constraints...${NC}"
FK_CHECK=$(mysql -e "
USE $DB_NAME;
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE 
    CONSTRAINT_SCHEMA = '$DB_NAME' 
    AND REFERENCED_TABLE_NAME IS NOT NULL;
" 2>/dev/null | wc -l)

if [ "$FK_CHECK" -gt 1 ]; then
    log_result "PASS" "Foreign key constraints present ($((FK_CHECK-1)) found)"
    ((PASS_COUNT++))
else
    log_result "WARN" "No foreign key constraints found"
    ((WARN_COUNT++))
fi

# Test 4: Default Data Validation
echo -e "${BLUE}Validating default data...${NC}"

# Check for Nifco trading partner
NIFCO_COUNT=$(mysql -e "USE $DB_NAME; SELECT COUNT(*) FROM trading_partners WHERE partner_code='NIFCO';" 2>/dev/null | tail -1)
if [ "$NIFCO_COUNT" -eq 1 ]; then
    log_result "PASS" "Nifco trading partner exists"
    ((PASS_COUNT++))
else
    log_result "WARN" "Nifco trading partner missing or duplicated ($NIFCO_COUNT found)"
    ((WARN_COUNT++))
fi

# Check for ship-to locations
LOCATION_COUNT=$(mysql -e "USE $DB_NAME; SELECT COUNT(*) FROM ship_to_locations;" 2>/dev/null | tail -1)
if [ "$LOCATION_COUNT" -gt 0 ]; then
    log_result "PASS" "Ship-to locations present ($LOCATION_COUNT found)"
    ((PASS_COUNT++))
else
    log_result "WARN" "No ship-to locations found"
    ((WARN_COUNT++))
fi

# Test 5: Index Validation
echo -e "${BLUE}Checking database indexes...${NC}"
INDEX_COUNT=$(mysql -e "
USE $DB_NAME;
SELECT COUNT(*) 
FROM information_schema.STATISTICS 
WHERE table_schema = '$DB_NAME' 
AND index_name != 'PRIMARY';
" 2>/dev/null | tail -1)

if [ "$INDEX_COUNT" -gt 10 ]; then
    log_result "PASS" "Database indexes present ($INDEX_COUNT found)"
    ((PASS_COUNT++))
else
    log_result "WARN" "Limited database indexes ($INDEX_COUNT found)"
    ((WARN_COUNT++))
fi

# Test 6: Data Integrity Checks
echo -e "${BLUE}Running data integrity checks...${NC}"

# Check for orphaned records
ORPHANED_SCHEDULES=$(mysql -e "
USE $DB_NAME;
SELECT COUNT(*) 
FROM delivery_schedules ds 
LEFT JOIN trading_partners tp ON ds.partner_id = tp.id 
WHERE tp.id IS NULL;
" 2>/dev/null | tail -1)

if [ "$ORPHANED_SCHEDULES" -eq 0 ]; then
    log_result "PASS" "No orphaned delivery schedules"
    ((PASS_COUNT++))
else
    log_result "FAIL" "Found $ORPHANED_SCHEDULES orphaned delivery schedules"
    ((FAIL_COUNT++))
fi

# Test 7: File System Validation
echo -e "${BLUE}Validating file system structure...${NC}"

REQUIRED_DIRS=(
    "data/inbox"
    "data/outbox" 
    "data/processed"
    "data/error"
    "data/archive"
    "logs"
    "src"
    "migration"
)

for dir in "${REQUIRED_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        log_result "PASS" "Directory '$dir' exists"
        ((PASS_COUNT++))
    else
        log_result "WARN" "Directory '$dir' missing"
        ((WARN_COUNT++))
    fi
done

# Test 8: Configuration Validation
echo -e "${BLUE}Checking configuration files...${NC}"

if [ -f "bootstrap.php" ]; then
    log_result "PASS" "Bootstrap file exists"
    ((PASS_COUNT++))
else
    log_result "FAIL" "Bootstrap file missing"
    ((FAIL_COUNT++))
fi

if [ -f "composer.json" ]; then
    log_result "PASS" "Composer configuration exists"
    ((PASS_COUNT++))
else
    log_result "WARN" "Composer configuration missing"
    ((WARN_COUNT++))
fi

if [ -f ".env" ]; then
    log_result "PASS" "Environment configuration exists"
    ((PASS_COUNT++))
else
    log_result "WARN" "Environment configuration (.env) missing"
    ((WARN_COUNT++))
fi

# Test 9: Dependencies Check
echo -e "${BLUE}Checking dependencies...${NC}"

if [ -d "vendor" ]; then
    log_result "PASS" "Vendor dependencies directory exists"
    ((PASS_COUNT++))
    
    # Check for key dependencies
    if [ -d "vendor/phpseclib" ]; then
        log_result "PASS" "phpseclib dependency installed"
        ((PASS_COUNT++))
    else
        log_result "FAIL" "phpseclib dependency missing"
        ((FAIL_COUNT++))
    fi
else
    log_result "FAIL" "Vendor dependencies missing - run 'composer install'"
    ((FAIL_COUNT++))
fi

# Test 10: Permission Check
echo -e "${BLUE}Checking file permissions...${NC}"

for dir in "${REQUIRED_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        PERMS=$(stat -c "%a" "$dir" 2>/dev/null || echo "000")
        if [[ "$PERMS" =~ ^(755|775|777)$ ]]; then
            log_result "PASS" "Directory '$dir' has correct permissions ($PERMS)"
            ((PASS_COUNT++))
        else
            log_result "WARN" "Directory '$dir' permissions may need adjustment ($PERMS)"
            ((WARN_COUNT++))
        fi
    fi
done

# Generate Summary
echo ""
echo "----------------------------------------"
echo -e "${BLUE}VALIDATION SUMMARY${NC}"

echo "" >> "$VALIDATION_LOG"
echo "VALIDATION SUMMARY" >> "$VALIDATION_LOG"
echo "==================" >> "$VALIDATION_LOG"

TOTAL_TESTS=$((PASS_COUNT + FAIL_COUNT + WARN_COUNT))

echo "Total Tests: $TOTAL_TESTS" | tee -a "$VALIDATION_LOG"
echo -e "Passed: ${GREEN}$PASS_COUNT${NC}" | tee -a "$VALIDATION_LOG"
echo -e "Failed: ${RED}$FAIL_COUNT${NC}" | tee -a "$VALIDATION_LOG"  
echo -e "Warnings: ${YELLOW}$WARN_COUNT${NC}" | tee -a "$VALIDATION_LOG"

if [ $FAIL_COUNT -eq 0 ]; then
    if [ $WARN_COUNT -eq 0 ]; then
        echo -e "\n${GREEN}✓ MIGRATION VALIDATION PASSED${NC}" | tee -a "$VALIDATION_LOG"
        echo "Your EDI module migration appears to be successful!" | tee -a "$VALIDATION_LOG"
    else
        echo -e "\n${YELLOW}⚠ MIGRATION VALIDATION PASSED WITH WARNINGS${NC}" | tee -a "$VALIDATION_LOG"
        echo "Migration successful but please address the warnings above." | tee -a "$VALIDATION_LOG"
    fi
else
    echo -e "\n${RED}✗ MIGRATION VALIDATION FAILED${NC}" | tee -a "$VALIDATION_LOG"
    echo "Please resolve the failed tests before using the application." | tee -a "$VALIDATION_LOG"
fi

echo "" | tee -a "$VALIDATION_LOG"
echo "Detailed validation log saved to: $VALIDATION_LOG" | tee -a "$VALIDATION_LOG"

exit $FAIL_COUNT