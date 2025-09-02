# EDI Processing Application

A LAMP-based Electronic Data Interchange (EDI) processing system for Greenfield Precision Plastics LLC to handle automated communication with customer Nifco using EDI 862 (shipping schedules) and EDI 856 (advance shipping notices).

## Project Overview

This application automates the processing of delivery schedules and shipping notifications between Greenfield Precision Plastics and their automotive industry customers, starting with Nifco. The system is designed as a module within a larger ERP system.

## Features

- **EDI Transaction Processing**: Parse inbound EDI 862 shipping schedules and generate outbound EDI 856 advance ship notices
- **Multi-Customer Support**: Configurable system to handle multiple trading partners with different EDI requirements
- **Data Import**: Import existing delivery schedule data from TSV/CSV files
- **Web Dashboard**: Monitor EDI transactions, delivery schedules, and shipment status
- **Error Handling**: Comprehensive validation and error reporting for EDI transactions
- **Audit Trail**: Complete transaction logging and compliance reporting

## Technical Stack

- **Backend**: PHP 8.1+, MySQL 8.0+
- **Frontend**: Bootstrap 5, jQuery
- **Server**: Apache 2.4+ with mod_rewrite
- **EDI Standards**: X12 Version 004 Release 001

## Quick Start

### Prerequisites

- PHP 8.1 or higher with extensions: mysqli, mbstring, xml, curl
- MySQL 8.0 or higher
- Apache 2.4+ with mod_rewrite enabled
- Composer for dependency management

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd edi-processing-app
   ```

2. **Set up environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database and configuration settings
   ```

3. **Create database and import schema**
   ```bash
   mysql -u root -p
   CREATE DATABASE edi_processing;
   CREATE USER 'edi_user'@'localhost' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON edi_processing.* TO 'edi_user'@'localhost';
   
   mysql -u edi_user -p edi_processing < database_schema.sql
   ```

4. **Set up directory structure**
   ```bash
   mkdir -p data/{inbox,outbox,processed,error,archive}
   chmod 755 data/
   chmod 777 data/*
   ```

5. **Import sample data**
   ```bash
   # Use the provided sample_data.tsv to test data import functionality
   ```

## Project Structure

```
/
├── CLAUDE.md              # Project memory for Claude Code
├── README.md              # This file
├── PRD.md                 # Product Requirements Document
├── database_schema.sql    # Database setup script
├── sample_data.tsv        # Sample delivery schedule data
├── .env.example           # Environment configuration template
└── src/                   # Application source code (created by Claude Code)
    ├── public/            # Web-accessible files
    ├── classes/           # PHP classes for EDI processing
    ├── config/            # Configuration files
    ├── templates/         # HTML templates
    └── data/              # EDI file processing directories
```

## Key Data Patterns

### Delivery Schedule Data
- PO Format: `1067045-236` (PO Number - Release Number)
- Part Numbers: `23466`, `28204`, `27977` (supplier item codes)
- Quantities: 120 - 26,000 pieces per line
- Ship-To Locations: "SHELBYVILLE KENTUCKY" (SLB), "CWH"
- Delivery Frequency: Weekly schedules

### EDI Transaction Types
- **862 (Inbound)**: Shipping schedules from Nifco with delivery requirements
- **856 (Outbound)**: Advance ship notices to Nifco with shipment details

## Configuration

### Trading Partners
The system is pre-configured for Nifco with:
- EDI ID: `6148363808`
- Location codes: SLB (Shelbyville), CWH (Canal Pointe Warehouse)
- Contact: fieldsc@us.nifco.com

### Location Mappings
| Description | EDI Code | Purpose |
|-------------|----------|---------|
| SHELBYVILLE KENTUCKY | SLB | Manufacturing Plant |
| Canal Pointe Warehouse | CWH | Warehouse |
| Canal Pointe Manufacturing | CNL | Manufacturing |
| Lavern Manufacturing | LVG | Manufacturing |
| Groveport Warehouse | GWH | Warehouse |

## Development with Claude Code

This project is designed to work with Claude Code for automated development. The key files for Claude Code are:

- **CLAUDE.md**: Contains project memory and context
- **database_schema.sql**: Complete database structure
- **sample_data.tsv**: Real delivery schedule data for testing
- **.env.example**: Configuration template

To start development with Claude Code:
```bash
claude-code --message "Build the EDI processing application based on the PRD.md and database schema. Start with the core LAMP structure and data import functionality."
```

## Future Enhancements

- **Additional Trading Partners**: Extend configuration for automotive OEMs
- **Advanced Communication**: AS2 secure transmission protocol
- **ERP Integration**: Full integration with existing ERP systems
- **Mobile Interface**: Responsive design for mobile EDI management
- **Analytics Dashboard**: Performance metrics and delivery analytics

## Security Considerations

- Database connection encryption
- Secure file transmission protocols (AS2, SFTP)
- Comprehensive audit logging
- User authentication and authorization
- Data backup and recovery procedures

## Support

For technical issues or questions about EDI requirements, contact the development team or refer to the comprehensive documentation in the PRD.md file.

## License

This project is proprietary software for Greenfield Precision Plastics LLC.