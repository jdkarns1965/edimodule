-- Migration: Add EDI configuration fields to trading_partners table
-- Purpose: Support multi-customer EDI configurations
-- Date: 2025-09-06

-- Add new columns for EDI configuration
ALTER TABLE trading_partners 
ADD COLUMN edi_standard ENUM('X12', 'EDIFACT', 'TRADACOMS', 'CUSTOM') DEFAULT 'X12' AFTER edi_id,
ADD COLUMN edi_version VARCHAR(10) DEFAULT '004010' AFTER edi_standard,
ADD COLUMN date_format VARCHAR(20) DEFAULT 'MM/DD/YYYY' AFTER edi_version,
ADD COLUMN po_number_format VARCHAR(50) DEFAULT 'NNNNNN-NNN' AFTER date_format,
ADD COLUMN default_organization VARCHAR(100) DEFAULT 'NIFCO' AFTER po_number_format,
ADD COLUMN default_supplier VARCHAR(100) DEFAULT 'GREENFIELD PRECISION PLASTICS LLC' AFTER default_organization,
ADD COLUMN default_uom VARCHAR(10) DEFAULT 'EACH' AFTER default_supplier,
ADD COLUMN field_mappings JSON NULL COMMENT 'Customer-specific field name mappings' AFTER default_uom,
ADD COLUMN business_rules JSON NULL COMMENT 'Customer-specific business rules and calculations' AFTER field_mappings,
ADD COLUMN communication_config JSON NULL COMMENT 'SFTP, AS2, API connection details' AFTER business_rules,
ADD COLUMN template_config JSON NULL COMMENT 'Import/Export template configurations' AFTER communication_config;

-- Add indexes for performance
ALTER TABLE trading_partners 
ADD INDEX idx_edi_standard (edi_standard),
ADD INDEX idx_status_standard (status, edi_standard);

-- Update existing NIFCO record with current configuration
UPDATE trading_partners 
SET 
    edi_standard = 'X12',
    edi_version = '004010',
    date_format = 'MM/DD/YYYY',
    po_number_format = 'NNNNNN-NNN',
    default_organization = 'NIFCO',
    default_supplier = 'GREENFIELD PRECISION PLASTICS LLC',
    default_uom = 'EACH',
    field_mappings = JSON_OBJECT(
        'po_number_field', 'PO Number',
        'supplier_item_field', 'Supplier Item',
        'customer_item_field', 'Item Number',
        'description_field', 'Item Description',
        'quantity_field', 'Quantity Ordered',
        'promised_date_field', 'Promised Date',
        'ship_to_field', 'Ship-To Location',
        'uom_field', 'UOM',
        'organization_field', 'Organization',
        'supplier_field', 'Supplier'
    ),
    business_rules = JSON_OBJECT(
        'po_parsing_rule', 'split_on_dash',
        'date_parsing_formats', JSON_ARRAY('M/D/YYYY', 'MM/DD/YYYY', 'M/D/YY'),
        'location_mapping_type', 'description_based',
        'container_calculation_rule', 'round_up',
        'default_lead_time_days', 0
    ),
    communication_config = JSON_OBJECT(
        'protocol', 'SFTP',
        'host', 'edi.nifco.com',
        'port', 22,
        'username', 'greenfield_plastics_edi',
        'inbox_path', '/inbox',
        'outbox_path', '/outbox',
        'file_naming_convention', 'EDI862_{YYYYMMDD}_{HHMMSS}.edi',
        'retry_attempts', 3,
        'timeout_seconds', 30
    ),
    template_config = JSON_OBJECT(
        'import_template_name', 'nifco_862_import.csv',
        'export_template_name', 'nifco_856_export.edi',
        'required_headers', JSON_ARRAY('PO Number', 'Supplier Item', 'Item Description', 'Quantity Ordered', 'Promised Date', 'Ship-To Location'),
        'optional_headers', JSON_ARRAY('Quantity Received', 'Need-By Date', 'UOM', 'Organization', 'Supplier', 'Item Number')
    )
WHERE partner_code = 'NIFCO';

-- Create sample configuration for a second customer (Ford) for testing
INSERT IGNORE INTO trading_partners (
    partner_code, name, edi_id, edi_standard, edi_version, date_format, po_number_format,
    default_organization, default_supplier, default_uom, connection_type, status,
    field_mappings, business_rules, communication_config, template_config
) VALUES (
    'FORD', 'Ford Motor Company', '1234567890', 'X12', '005010', 'YYYY-MM-DD', 'NNNNNNNNNN',
    'FORD', 'GREENFIELD PRECISION PLASTICS LLC', 'EA', 'AS2', 'testing',
    JSON_OBJECT(
        'po_number_field', 'Purchase_Order',
        'supplier_item_field', 'Part_Number',
        'customer_item_field', 'Ford_Part_Number',
        'description_field', 'Part_Description',
        'quantity_field', 'Order_Quantity',
        'promised_date_field', 'Delivery_Date',
        'ship_to_field', 'Plant_Code',
        'uom_field', 'Unit_Of_Measure',
        'organization_field', 'Customer',
        'supplier_field', 'Vendor'
    ),
    JSON_OBJECT(
        'po_parsing_rule', 'no_split',
        'date_parsing_formats', JSON_ARRAY('YYYY-MM-DD', 'YYYYMMDD'),
        'location_mapping_type', 'code_based',
        'container_calculation_rule', 'exact',
        'default_lead_time_days', 1
    ),
    JSON_OBJECT(
        'protocol', 'AS2',
        'host', 'edi.ford.com',
        'as2_identifier', 'GREENFIELD_EDI',
        'certificate_path', '/certificates/ford.p12',
        'inbox_path', '/ford/inbox',
        'outbox_path', '/ford/outbox',
        'file_naming_convention', 'FORD_{transaction}_{YYYYMMDD}.edi',
        'retry_attempts', 5,
        'timeout_seconds', 60
    ),
    JSON_OBJECT(
        'import_template_name', 'ford_830_import.csv',
        'export_template_name', 'ford_856_export.edi',
        'required_headers', JSON_ARRAY('Purchase_Order', 'Part_Number', 'Part_Description', 'Order_Quantity', 'Delivery_Date', 'Plant_Code'),
        'optional_headers', JSON_ARRAY('Received_Quantity', 'Required_Date', 'Unit_Of_Measure', 'Customer', 'Vendor', 'Ford_Part_Number')
    )
);

-- Verify the changes
SELECT 
    partner_code,
    name,
    edi_standard,
    edi_version,
    date_format,
    po_number_format,
    JSON_EXTRACT(field_mappings, '$.po_number_field') as po_field,
    JSON_EXTRACT(business_rules, '$.po_parsing_rule') as po_rule,
    JSON_EXTRACT(communication_config, '$.protocol') as protocol
FROM trading_partners 
WHERE partner_code IN ('NIFCO', 'FORD');