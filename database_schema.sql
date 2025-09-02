-- EDI Processing Application Database Schema
-- For Greenfield Precision Plastics LLC EDI System

-- Drop existing tables if they exist (for development)
DROP TABLE IF EXISTS shipment_items;
DROP TABLE IF EXISTS shipments;
DROP TABLE IF EXISTS delivery_schedules;
DROP TABLE IF EXISTS customer_connections;
DROP TABLE IF EXISTS customer_locations;
DROP TABLE IF EXISTS customer_edi_configs;
DROP TABLE IF EXISTS edi_transactions;
DROP TABLE IF EXISTS ship_to_locations;
DROP TABLE IF EXISTS trading_partners;

-- Trading partners (customers who use EDI)
CREATE TABLE trading_partners (
    id INT PRIMARY KEY AUTO_INCREMENT,
    partner_code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    edi_id VARCHAR(15) NOT NULL,
    connection_type ENUM('AS2','SFTP','FTP','VAN','API') DEFAULT 'SFTP',
    status ENUM('active','inactive','testing') DEFAULT 'testing',
    contact_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_partner_code (partner_code),
    INDEX idx_edi_id (edi_id)
);

-- Ship-to location mapping for EDI codes
CREATE TABLE ship_to_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    partner_id INT NOT NULL,
    location_description VARCHAR(100) NOT NULL,
    location_code VARCHAR(10) NOT NULL,
    address VARCHAR(200),
    city VARCHAR(100),
    state VARCHAR(50),
    postal_code VARCHAR(20),
    country VARCHAR(50) DEFAULT 'US',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (partner_id) REFERENCES trading_partners(id),
    INDEX idx_location_code (location_code),
    INDEX idx_partner_location (partner_id, location_code)
);

-- EDI transaction audit log
CREATE TABLE edi_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    partner_id INT NOT NULL,
    transaction_type VARCHAR(3) NOT NULL,
    direction ENUM('inbound','outbound') NOT NULL,
    control_number VARCHAR(20),
    filename VARCHAR(255),
    file_size INT,
    raw_content LONGTEXT,
    parsed_content JSON,
    status ENUM('received','processing','processed','error','archived') DEFAULT 'received',
    error_message TEXT,
    processing_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (partner_id) REFERENCES trading_partners(id),
    INDEX idx_partner_type (partner_id, transaction_type),
    INDEX idx_control_number (control_number),
    INDEX idx_status_date (status, created_at),
    INDEX idx_filename (filename)
);

-- Current delivery schedules (from EDI 862 or manual import)
CREATE TABLE delivery_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    partner_id INT NOT NULL,
    po_number VARCHAR(20) NOT NULL,
    release_number VARCHAR(10),
    po_line VARCHAR(30) NOT NULL,
    line_number INT DEFAULT 1,
    supplier_item VARCHAR(50) NOT NULL,
    customer_item VARCHAR(50),
    item_description VARCHAR(200),
    quantity_ordered INT NOT NULL,
    quantity_received INT DEFAULT 0,
    quantity_shipped INT DEFAULT 0,
    promised_date DATE NOT NULL,
    need_by_date DATE NOT NULL,
    ship_to_location_id INT,
    ship_to_description VARCHAR(100),
    uom VARCHAR(10) DEFAULT 'EACH',
    unit_price DECIMAL(10,4),
    organization VARCHAR(50) DEFAULT 'NIFCO',
    supplier VARCHAR(100) DEFAULT 'GREENFIELD PRECISION PLASTICS LLC',
    status ENUM('active','shipped','received','cancelled','closed') DEFAULT 'active',
    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
    notes TEXT,
    edi_transaction_id INT NULL,
    erp_po_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (partner_id) REFERENCES trading_partners(id),
    FOREIGN KEY (ship_to_location_id) REFERENCES ship_to_locations(id),
    FOREIGN KEY (edi_transaction_id) REFERENCES edi_transactions(id),
    INDEX idx_po_release (po_number, release_number),
    INDEX idx_promised_date (promised_date),
    INDEX idx_supplier_item (supplier_item),
    INDEX idx_status_date (status, promised_date),
    INDEX idx_ship_to (ship_to_location_id),
    INDEX idx_erp_po (erp_po_id)
);

-- Outbound shipment data for generating EDI 856
CREATE TABLE shipments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shipment_number VARCHAR(50) UNIQUE NOT NULL,
    partner_id INT NOT NULL,
    po_number VARCHAR(20) NOT NULL,
    ship_to_location_id INT,
    ship_date DATE NOT NULL,
    estimated_delivery DATE,
    actual_delivery DATE NULL,
    carrier_scac VARCHAR(4),
    carrier_name VARCHAR(100),
    service_level VARCHAR(50),
    bol_number VARCHAR(50),
    pro_number VARCHAR(50),
    trailer_number VARCHAR(20),
    seal_number VARCHAR(20),
    total_weight DECIMAL(10,2),
    weight_uom VARCHAR(5) DEFAULT 'LB',
    total_packages INT DEFAULT 1,
    packaging_type VARCHAR(20) DEFAULT 'CTN',
    status ENUM('planned','picked','packed','shipped','delivered','cancelled') DEFAULT 'planned',
    created_by VARCHAR(100),
    shipped_by VARCHAR(100),
    tracking_url VARCHAR(255),
    special_instructions TEXT,
    edi_transaction_id INT NULL,
    erp_shipment_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (partner_id) REFERENCES trading_partners(id),
    FOREIGN KEY (ship_to_location_id) REFERENCES ship_to_locations(id),
    FOREIGN KEY (edi_transaction_id) REFERENCES edi_transactions(id),
    INDEX idx_shipment_number (shipment_number),
    INDEX idx_partner_po (partner_id, po_number),
    INDEX idx_ship_date (ship_date),
    INDEX idx_bol_number (bol_number),
    INDEX idx_status (status)
);

-- Shipment line items (for EDI 856 detail)
CREATE TABLE shipment_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shipment_id INT NOT NULL,
    delivery_schedule_id INT NULL,
    supplier_item VARCHAR(50) NOT NULL,
    customer_item VARCHAR(50),
    item_description VARCHAR(200),
    po_line VARCHAR(30) NOT NULL,
    po_line_number INT,
    release_shipment_line VARCHAR(50),
    quantity_shipped INT NOT NULL,
    uom VARCHAR(10) DEFAULT 'EACH',
    unit_price DECIMAL(10,4),
    container_count INT DEFAULT 1,
    container_type VARCHAR(20) DEFAULT 'CTN',
    container_serial VARCHAR(50),
    lot_number VARCHAR(50),
    expiration_date DATE NULL,
    country_of_origin VARCHAR(50) DEFAULT 'US',
    harmonized_code VARCHAR(20),
    line_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_schedule_id) REFERENCES delivery_schedules(id),
    INDEX idx_shipment_item (shipment_id, supplier_item),
    INDEX idx_po_line (po_line),
    INDEX idx_container_serial (container_serial)
);

-- Customer-specific EDI configuration for multi-customer support
CREATE TABLE customer_edi_configs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    partner_id INT NOT NULL,
    transaction_type VARCHAR(3) NOT NULL,
    segment_id VARCHAR(3) NOT NULL,
    element_position INT NOT NULL,
    element_name VARCHAR(100),
    is_required BOOLEAN DEFAULT FALSE,
    data_type VARCHAR(20) DEFAULT 'AN',
    max_length INT,
    min_length INT,
    validation_rule TEXT,
    default_value VARCHAR(100),
    mapping_rule TEXT,
    notes TEXT,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (partner_id) REFERENCES trading_partners(id),
    INDEX idx_partner_transaction (partner_id, transaction_type),
    INDEX idx_segment_element (segment_id, element_position)
);

-- Customer connection configurations
CREATE TABLE customer_connections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    partner_id INT NOT NULL,
    connection_type ENUM('AS2','SFTP','FTP','VAN','API') NOT NULL,
    connection_name VARCHAR(100),
    endpoint_url VARCHAR(255),
    port INT,
    username VARCHAR(100),
    password_encrypted TEXT,
    certificate_path VARCHAR(255),
    private_key_path VARCHAR(255),
    test_endpoint_url VARCHAR(255),
    inbox_path VARCHAR(255),
    outbox_path VARCHAR(255),
    archive_path VARCHAR(255),
    is_test_mode BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    last_test_date TIMESTAMP NULL,
    last_success_date TIMESTAMP NULL,
    connection_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (partner_id) REFERENCES trading_partners(id),
    INDEX idx_partner_type (partner_id, connection_type)
);

-- Insert default trading partner (Nifco)
INSERT INTO trading_partners (partner_code, name, edi_id, connection_type, status, contact_email) 
VALUES ('NIFCO', 'Nifco Inc.', '6148363808', 'SFTP', 'active', 'fieldsc@us.nifco.com');

-- Get the Nifco partner ID for foreign key references
SET @nifco_id = LAST_INSERT_ID();

-- Insert Nifco ship-to locations
INSERT INTO ship_to_locations (partner_id, location_description, location_code, address, city, state) VALUES
(@nifco_id, 'SHELBYVILLE KENTUCKY', 'SLB', 'Shelbyville Manufacturing Plant', 'Shelbyville', 'KY'),
(@nifco_id, 'Canal Pointe Warehouse', 'CWH', 'Canal Pointe Warehouse', 'Newark', 'OH'),
(@nifco_id, 'Canal Pointe Manufacturing', 'CNL', 'Canal Pointe Manufacturing', 'Newark', 'OH'),
(@nifco_id, 'Lavern Manufacturing', 'LVG', 'Lavern Manufacturing Plant', 'LaVergne', 'TN'),
(@nifco_id, 'Groveport Warehouse', 'GWH', 'Groveport Warehouse', 'Groveport', 'OH'),
(@nifco_id, 'Chihuahua Manufacturing', 'MEX', 'Chihuahua Manufacturing', 'Chihuahua', 'Mexico'),
(@nifco_id, 'Nifco Central México', 'NCM', 'Nifco Central México', 'Mexico City', 'Mexico');

-- Insert basic EDI configuration for Nifco 862 transactions
INSERT INTO customer_edi_configs (partner_id, transaction_type, segment_id, element_position, element_name, is_required) VALUES
(@nifco_id, '862', 'ST', 1, 'Transaction Set Identifier Code', TRUE),
(@nifco_id, '862', 'ST', 2, 'Transaction Set Control Number', TRUE),
(@nifco_id, '862', 'BSS', 1, 'Transaction Set Purpose Code', TRUE),
(@nifco_id, '862', 'BSS', 2, 'Reference Identification', TRUE),
(@nifco_id, '862', 'BSS', 3, 'Schedule Date', TRUE),
(@nifco_id, '862', 'BSS', 4, 'Schedule Type Qualifier', TRUE),
(@nifco_id, '862', 'BSS', 10, 'Purchase Order Number', FALSE);

-- Insert basic EDI configuration for Nifco 856 transactions  
INSERT INTO customer_edi_configs (partner_id, transaction_type, segment_id, element_position, element_name, is_required) VALUES
(@nifco_id, '856', 'ST', 1, 'Transaction Set Identifier Code', TRUE),
(@nifco_id, '856', 'ST', 2, 'Transaction Set Control Number', TRUE),
(@nifco_id, '856', 'BSN', 1, 'Transaction Set Purpose Code', TRUE),
(@nifco_id, '856', 'BSN', 2, 'Shipment Identification', TRUE),
(@nifco_id, '856', 'BSN', 3, 'Date', TRUE),
(@nifco_id, '856', 'BSN', 4, 'Time', TRUE);