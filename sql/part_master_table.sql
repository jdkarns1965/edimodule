-- Part Master Table for EDI Processing Module
-- Stores part information with QPC (Quantity Per Container) data
-- Supports multi-location parts and auto-detection from EDI

CREATE TABLE IF NOT EXISTS part_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_number VARCHAR(50) NOT NULL UNIQUE,
    customer_part_number VARCHAR(50) NULL,
    description VARCHAR(200) NULL,
    qpc INT NOT NULL DEFAULT 1 COMMENT 'Quantity Per Container - for container calculations',
    uom VARCHAR(10) DEFAULT 'EACH',
    weight DECIMAL(10,4) NULL COMMENT 'Part weight for shipping calculations',
    dimensions VARCHAR(100) NULL COMMENT 'Length x Width x Height',
    material VARCHAR(100) NULL,
    color VARCHAR(50) NULL,
    product_family VARCHAR(100) NULL,
    active BOOLEAN DEFAULT TRUE,
    auto_detected BOOLEAN DEFAULT FALSE COMMENT 'TRUE if part was auto-detected from EDI processing',
    first_detected_date DATETIME NULL COMMENT 'Date when part was first seen in EDI',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_part_number (part_number),
    INDEX idx_customer_part (customer_part_number),
    INDEX idx_active (active),
    INDEX idx_auto_detected (auto_detected),
    INDEX idx_product_family (product_family)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add location_code column to delivery_schedules for location-specific reporting
ALTER TABLE delivery_schedules 
ADD COLUMN location_code VARCHAR(10) NULL AFTER ship_to_location_id,
ADD INDEX idx_location_code (location_code);

-- Create part_location_mapping table for multi-location parts
CREATE TABLE IF NOT EXISTS part_location_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_number VARCHAR(50) NOT NULL,
    location_code VARCHAR(10) NOT NULL,
    customer_part_number VARCHAR(50) NULL,
    qpc INT NULL COMMENT 'Location-specific QPC override',
    active BOOLEAN DEFAULT TRUE,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_part_location (part_number, location_code),
    INDEX idx_part_number (part_number),
    INDEX idx_location_code (location_code),
    INDEX idx_active (active),
    FOREIGN KEY (part_number) REFERENCES part_master(part_number) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample data based on existing delivery schedules
INSERT IGNORE INTO part_master (part_number, description, qpc, auto_detected, first_detected_date) 
SELECT DISTINCT 
    supplier_item as part_number,
    COALESCE(item_description, 'Auto-detected from EDI') as description,
    1 as qpc,
    TRUE as auto_detected,
    MIN(created_at) as first_detected_date
FROM delivery_schedules 
WHERE supplier_item IS NOT NULL 
GROUP BY supplier_item, item_description;

-- Update delivery_schedules with location codes based on ship_to_description
UPDATE delivery_schedules ds
JOIN ship_to_locations stl ON ds.ship_to_location_id = stl.id
SET ds.location_code = stl.location_code
WHERE ds.location_code IS NULL;

-- For records without ship_to_location_id, try to match by description
UPDATE delivery_schedules 
SET location_code = CASE
    WHEN UPPER(ship_to_description) LIKE '%SHELBYVILLE%' OR UPPER(ship_to_description) LIKE '%SLB%' THEN 'SLB'
    WHEN UPPER(ship_to_description) LIKE '%CWH%' THEN 'CWH'
    WHEN UPPER(ship_to_description) LIKE '%CNL%' OR UPPER(ship_to_description) LIKE '%CENTRAL%' THEN 'CNL'
    ELSE NULL
END
WHERE location_code IS NULL AND ship_to_description IS NOT NULL;