# EDI Processing Application - Project Memory

## Project Overview
Building a LAMP-based EDI processing module for Greenfield Precision Plastics LLC to handle electronic data interchange with customer Nifco. This module will be part of a larger ERP system being developed.

## CURRENT PHASE: Production-Ready SFTP Integration

### ✅ COMPLETED (2025-09-17):
- **SFTP Integration**: Full phpseclib3 implementation with SFTPClient class
- **File Monitoring**: Automated monitoring service with two deployment modes
  - Development: Daemon mode for continuous monitoring (monitor_daemon.php)
  - Production: Cron-optimized for GreenGeeks 15-minute intervals (cron_monitor.php)
- **Web Interface**: Complete SFTP dashboard with test/download/process controls
- **Environment Management**: Development (.env) and production (.env.production.template) configurations
- **GreenGeeks Deployment**: Cron setup guide and production-ready configuration
- **EDI Processing**: 862 parsing and database storage working
- **Web Interface**: Bootstrap 5 interface with all core functionality

### IMMEDIATE REQUIREMENTS:
1. Part Master Management Interface (?page=part_master)
   - Add/Edit/Search parts with QPC data
   - Bulk import from Excel/CSV
   - Integration with EDI processing

2. Delivery Schedule Matrix (?page=delivery_matrix) 
   - Interactive filtering and sorting
   - Container calculations using QPC
   - Multiple export formats (Excel, CSV, PDF)

3. Location-Specific Reporting
   - SLB sequential release handling
   - CNL/CWH duplicate release management
   - Multi-location part tracking

### DATABASE ADDITIONS NEEDED:
- part_master table (may already exist)
- Enhanced delivery_schedules with location_code column
- Container calculation fields

### Technical Specifications:
- Library: phpseclib/phpseclib (confirmed compatible with GreenGeeks shared hosting)
- Configuration: Environment-based (.env) for dev/production flexibility
- Development: jdkarns@localhost:22 with existing directory structure
- Production: Will use Nifco SFTP credentials on GreenGeeks

### Directory Structure (Already Created):
```
/var/www/html/edimodule/data/
├── inbox/          # Nifco drops 862 files here via SFTP
├── outbox/         # Application places 856 files here for Nifco pickup  
├── processed/      # Archive successfully processed files
├── error/          # Files that failed processing
└── archive/        # Long-term storage
```

## Business Context
- **Company**: Greenfield Precision Plastics LLC (supplier)
- **Primary Customer**: Nifco (automotive parts manufacturer)
- **Current Process**: Manual delivery schedule tracking via TSV files
- **Goal**: Automate EDI 862 (shipping schedules) inbound and EDI 856 (advance ship notices) outbound

## Key Data Patterns (Tested)
- PO numbers format: 1067045-236 (PO-Release)
- Supplier part numbers: 23466, 28204, 27977, etc.
- Ship-to locations: "SHELBYVILLE KENTUCKY" (maps to EDI code SLB), "CWH"
- Sample 862 file created and tested in inbox: test_862.edi
- SFTP file operations confirmed working (get/put/ls commands tested)

## EDI Standards
- X12 Version 004 Release 001
- Transaction types: 862 (Shipping Schedule), 856 (Ship Notice/Manifest)
- Segment terminators: ~ (tilde)
- Element separators: * (asterisk)
- Nifco-specific location codes and implementation guidelines provided

## Technical Architecture
- **Stack**: LAMP (Linux, Apache, MySQL 8+, PHP 8.1+)
- **Frontend**: Bootstrap 5, jQuery for dynamic interfaces
- **SFTP**: phpseclib library (no SSH2 extension dependency)
- **Hosting**: GreenGeeks shared hosting (confirmed compatible)
- **Integration**: Designed as ERP module with shared authentication and database

## Database Schema (Implemented)
- `delivery_schedules` - Core delivery data from TSV/EDI imports
- `edi_transactions` - Transaction logging and audit trail
- `trading_partners` - Customer configuration (Nifco setup complete)
- `shipments` and `shipment_items` - Outbound ASN data
- `ship_to_locations` - Location code mappings (SLB, CWH, etc.)

## Multi-Customer Design Ready
- Configuration-driven parsing and validation
- Customer-specific location mappings
- Extensible for additional trading partners beyond Nifco

## Environment Configuration Needed
```bash
# SFTP Configuration 
SFTP_ENABLED=true
SFTP_LIBRARY=phpseclib
SFTP_HOST=localhost  # Production: edi.nifco.com
SFTP_PORT=22
SFTP_USERNAME=jdkarns  # Production: greenfield_plastics_edi
SFTP_PASSWORD=your_password  # Production: provided_by_nifco
SFTP_TIMEOUT=30

# Local paths (adjust for GreenGeeks in production)
SFTP_INBOX_PATH=/var/www/html/edimodule/data/inbox
SFTP_OUTBOX_PATH=/var/www/html/edimodule/data/outbox
SFTP_PROCESSED_PATH=/var/www/html/edimodule/data/processed
SFTP_ERROR_PATH=/var/www/html/edimodule/data/error
```

## ✅ SFTP Integration COMPLETE - Today's Implementation

### Key Files Created/Updated (2025-09-17):
1. **SFTPClient.php** - Complete phpseclib3 integration with all SFTP operations
2. **EDIFileMonitor.php** - Automated file processing with database logging
3. **monitor_daemon.php** - Development daemon with signal handling and process management
4. **cron_monitor.php** - Production cron script optimized for GreenGeeks shared hosting
5. **scripts/monitor_control.sh** - Management script for daemon control
6. **scripts/edi-monitor.service** - Systemd service file for dedicated servers
7. **scripts/greengeeks_cron_setup.md** - Comprehensive deployment documentation
8. **src/templates/sftp.php** - Enhanced SFTP dashboard with monitoring controls
9. **src/templates/cron_setup.php** - Web-based cron setup guide
10. **.env.production.template** - Production configuration template
11. **bootstrap.php** - Environment loading with dotenv support

### SFTP Features Implemented:
- ✅ **Connection Management**: Test, connect, disconnect with timeout handling
- ✅ **File Operations**: Download, upload, list, move, delete with pattern matching
- ✅ **Error Handling**: Comprehensive error reporting and logging
- ✅ **Lock File Protection**: Prevents multiple cron instances
- ✅ **Web Dashboard**: Real-time status, file listings, manual operations
- ✅ **Automated Processing**: Download → Parse → Database → Archive workflow
- ✅ **Production Ready**: GreenGeeks cron compatibility with 15-minute intervals

### Deployment Modes:
1. **Development**: Continuous daemon monitoring every 60 seconds
2. **Production**: Cron-based monitoring every 15 minutes (GreenGeeks compatible)

## Next Development Priorities
1. Production deployment testing on GreenGeeks
2. Part Master Management Interface (?page=part_master)
3. Delivery Schedule Matrix (?page=delivery_matrix) 
4. 856 ASN generation and outbound SFTP upload

## Production Deployment Path
### Development Environment:
- **SFTP**: localhost:22 with local user credentials
- **Monitoring**: Daemon mode or manual testing via web interface
- **Files**: Local data directory structure working

### Production Environment (GreenGeeks):
- **SFTP**: edi.nifco.com with greenfield_plastics_edi credentials
- **Monitoring**: Cron-based every 15 minutes using cron_monitor.php
- **Files**: GreenGeeks public_html structure with proper permissions
- **Configuration**: .env.production.template → .env with actual credentials

### Ready for Production:
✅ **SFTP Integration**: Full implementation complete
✅ **File Monitoring**: Cron-optimized for shared hosting
✅ **Web Interface**: Complete dashboard with monitoring controls
✅ **Documentation**: Comprehensive setup guides
✅ **Error Handling**: Production-grade logging and error recovery

## Testing Data Available
- Real delivery schedule data in sample_data.tsv
- Test EDI 862 file: /var/www/html/edimodule/data/processed/test_862_complete.edi
- SFTP server running and tested on localhost:22
- Cron monitor script tested and working

## Security Requirements
- Secure file transmission preparation
- Database connection encryption
- Audit logging for all transactions
- User authentication via ERP system