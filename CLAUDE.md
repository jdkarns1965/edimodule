# EDI Processing Application - Project Memory

## Project Overview
Building a LAMP-based EDI processing module for Greenfield Precision Plastics LLC to handle electronic data interchange with customer Nifco. This module will be part of a larger ERP system being developed.

## CURRENT PHASE: Reporting and Part Master Management

### Completed:
- SFTP integration with phpseclib working
- EDI 862 parsing and database storage
- Basic web interface operational

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

## Development Priorities - IMMEDIATE
1. Add Composer with phpseclib dependency
2. Create SFTPClient class using phpseclib
3. Build automated file monitoring service
4. Integrate SFTP operations with existing EDI parsing
5. Update web interface for SFTP status and controls
6. Test complete workflow: SFTP download → EDI parse → Database update → 856 generation → SFTP upload

## Testing Data Available
- Real delivery schedule data in sample_data.tsv
- Test EDI 862 file: /var/www/html/edimodule/data/inbox/test_862.edi
- SFTP server running and tested on localhost:22

## Deployment Path
- Development: Current setup with localhost SFTP
- Production: GreenGeeks with Nifco SFTP credentials (same codebase, different config)

## Security Requirements
- Secure file transmission preparation
- Database connection encryption
- Audit logging for all transactions
- User authentication via ERP system