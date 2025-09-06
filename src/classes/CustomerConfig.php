<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/app.php';

/**
 * Customer Configuration Helper
 * Retrieves customer-specific EDI configurations from the database
 * Replaces hard-coded NIFCO values with dynamic customer configurations
 */
class CustomerConfig {
    
    private static $cache = [];
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
    }
    
    /**
     * Get customer configuration by partner code or ID
     */
    public function getCustomerConfig($customerIdentifier) {
        // Use cache to avoid repeated database queries
        $cacheKey = "customer_" . $customerIdentifier;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM trading_partners 
                WHERE partner_code = ? OR id = ?
            ");
            $stmt->execute([$customerIdentifier, $customerIdentifier]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                throw new Exception("Customer not found: " . $customerIdentifier);
            }
            
            // Parse JSON configurations
            $customer['field_mappings'] = json_decode($customer['field_mappings'], true) ?? [];
            $customer['business_rules'] = json_decode($customer['business_rules'], true) ?? [];
            $customer['communication_config'] = json_decode($customer['communication_config'], true) ?? [];
            $customer['template_config'] = json_decode($customer['template_config'], true) ?? [];
            
            // Cache the result
            self::$cache[$cacheKey] = $customer;
            
            return $customer;
            
        } catch (Exception $e) {
            AppConfig::logError("Error loading customer config for: " . $customerIdentifier, [
                'error' => $e->getMessage()
            ]);
            
            // Return default NIFCO configuration as fallback
            return $this->getDefaultConfig();
        }
    }
    
    /**
     * Get customer-specific default values
     */
    public function getCustomerDefaults($customerIdentifier) {
        $config = $this->getCustomerConfig($customerIdentifier);
        
        return [
            'organization' => $config['default_organization'] ?? AppConfig::DEFAULT_ORGANIZATION,
            'supplier' => $config['default_supplier'] ?? AppConfig::DEFAULT_SUPPLIER,
            'uom' => $config['default_uom'] ?? AppConfig::DEFAULT_UOM,
            'date_format' => $config['date_format'] ?? 'MM/DD/YYYY',
            'po_number_format' => $config['po_number_format'] ?? 'NNNNNN-NNN'
        ];
    }
    
    /**
     * Get customer-specific field mappings for imports/exports
     */
    public function getFieldMappings($customerIdentifier) {
        $config = $this->getCustomerConfig($customerIdentifier);
        
        $defaultMappings = [
            'po_number_field' => 'PO Number',
            'supplier_item_field' => 'Supplier Item',
            'customer_item_field' => 'Item Number',
            'description_field' => 'Item Description',
            'quantity_field' => 'Quantity Ordered',
            'promised_date_field' => 'Promised Date',
            'ship_to_field' => 'Ship-To Location',
            'uom_field' => 'UOM',
            'organization_field' => 'Organization',
            'supplier_field' => 'Supplier'
        ];
        
        return array_merge($defaultMappings, $config['field_mappings']);
    }
    
    /**
     * Get customer-specific business rules
     */
    public function getBusinessRules($customerIdentifier) {
        $config = $this->getCustomerConfig($customerIdentifier);
        
        $defaultRules = [
            'po_parsing_rule' => 'split_on_dash',
            'date_parsing_formats' => ['M/D/YYYY', 'MM/DD/YYYY', 'M/D/YY'],
            'location_mapping_type' => 'description_based',
            'container_calculation_rule' => 'round_up',
            'default_lead_time_days' => 0
        ];
        
        return array_merge($defaultRules, $config['business_rules']);
    }
    
    /**
     * Get customer-specific communication settings
     */
    public function getCommunicationConfig($customerIdentifier) {
        $config = $this->getCustomerConfig($customerIdentifier);
        
        $defaultConfig = [
            'protocol' => 'SFTP',
            'host' => 'localhost',
            'port' => 22,
            'username' => 'default',
            'inbox_path' => '/inbox',
            'outbox_path' => '/outbox',
            'file_naming_convention' => 'EDI_{YYYYMMDD}_{HHMMSS}.edi',
            'retry_attempts' => 3,
            'timeout_seconds' => 30
        ];
        
        return array_merge($defaultConfig, $config['communication_config']);
    }
    
    /**
     * Get customer-specific template configuration
     */
    public function getTemplateConfig($customerIdentifier) {
        $config = $this->getCustomerConfig($customerIdentifier);
        
        $defaultConfig = [
            'import_template_name' => 'generic_import.csv',
            'export_template_name' => 'generic_export.edi',
            'required_headers' => ['PO Number', 'Supplier Item', 'Item Description', 'Quantity Ordered', 'Promised Date', 'Ship-To Location'],
            'optional_headers' => ['Quantity Received', 'Need-By Date', 'UOM', 'Organization', 'Supplier', 'Item Number']
        ];
        
        return array_merge($defaultConfig, $config['template_config']);
    }
    
    /**
     * Get default NIFCO configuration (fallback)
     */
    private function getDefaultConfig() {
        return [
            'id' => 0,
            'partner_code' => 'DEFAULT',
            'name' => 'Default Configuration',
            'edi_standard' => 'X12',
            'edi_version' => '004010',
            'date_format' => 'MM/DD/YYYY',
            'po_number_format' => 'NNNNNN-NNN',
            'default_organization' => AppConfig::DEFAULT_ORGANIZATION,
            'default_supplier' => AppConfig::DEFAULT_SUPPLIER,
            'default_uom' => AppConfig::DEFAULT_UOM,
            'field_mappings' => [
                'po_number_field' => 'PO Number',
                'supplier_item_field' => 'Supplier Item',
                'customer_item_field' => 'Item Number',
                'description_field' => 'Item Description',
                'quantity_field' => 'Quantity Ordered',
                'promised_date_field' => 'Promised Date',
                'ship_to_field' => 'Ship-To Location',
                'uom_field' => 'UOM',
                'organization_field' => 'Organization',
                'supplier_field' => 'Supplier'
            ],
            'business_rules' => [
                'po_parsing_rule' => 'split_on_dash',
                'date_parsing_formats' => ['M/D/YYYY', 'MM/DD/YYYY', 'M/D/YY'],
                'location_mapping_type' => 'description_based',
                'container_calculation_rule' => 'round_up',
                'default_lead_time_days' => 0
            ],
            'communication_config' => [
                'protocol' => 'SFTP',
                'host' => 'localhost',
                'port' => 22,
                'username' => 'default',
                'inbox_path' => '/inbox',
                'outbox_path' => '/outbox',
                'file_naming_convention' => 'EDI_{YYYYMMDD}_{HHMMSS}.edi',
                'retry_attempts' => 3,
                'timeout_seconds' => 30
            ],
            'template_config' => [
                'import_template_name' => 'default_import.csv',
                'export_template_name' => 'default_export.edi',
                'required_headers' => ['PO Number', 'Supplier Item', 'Item Description', 'Quantity Ordered', 'Promised Date', 'Ship-To Location'],
                'optional_headers' => ['Quantity Received', 'Need-By Date', 'UOM', 'Organization', 'Supplier', 'Item Number']
            ]
        ];
    }
    
    /**
     * Parse PO number according to customer rules
     */
    public function parsePONumber($poNumber, $customerIdentifier) {
        $rules = $this->getBusinessRules($customerIdentifier);
        $rule = $rules['po_parsing_rule'];
        
        switch ($rule) {
            case 'split_on_dash':
                $parts = explode('-', trim($poNumber));
                return [
                    'po_number' => $parts[0],
                    'release_number' => isset($parts[1]) ? $parts[1] : null
                ];
                
            case 'split_on_period':
                $parts = explode('.', trim($poNumber));
                return [
                    'po_number' => $parts[0],
                    'release_number' => isset($parts[1]) ? $parts[1] : null
                ];
                
            case 'no_split':
            default:
                return [
                    'po_number' => trim($poNumber),
                    'release_number' => null
                ];
        }
    }
    
    /**
     * Parse date according to customer format preferences
     */
    public function parseDate($dateString, $customerIdentifier) {
        $rules = $this->getBusinessRules($customerIdentifier);
        $formats = $rules['date_parsing_formats'];
        
        $dateString = trim($dateString);
        
        // Check for Excel serial numbers
        if (is_numeric($dateString) && $dateString > 1000) {
            throw new Exception("Date appears to be Excel serial number ($dateString). Please format dates properly.");
        }
        
        // Try each format in order
        foreach ($formats as $format) {
            $phpFormat = str_replace(['YYYY', 'MM', 'DD', 'YY', 'M', 'D'], 
                                   ['Y', 'm', 'd', 'y', 'n', 'j'], $format);
            
            $date = DateTime::createFromFormat($phpFormat, $dateString);
            if ($date && $date->format($phpFormat) === $dateString) {
                return $date->format('Y-m-d');
            }
        }
        
        // Fallback to strtotime
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        throw new Exception("Unable to parse date: $dateString");
    }
    
    /**
     * Clear configuration cache (useful for testing)
     */
    public static function clearCache() {
        self::$cache = [];
    }
    
    /**
     * Get list of all active customers
     */
    public function getActiveCustomers() {
        try {
            $stmt = $this->db->query("
                SELECT id, partner_code, name, edi_standard, edi_version
                FROM trading_partners 
                WHERE status = 'active'
                ORDER BY partner_code
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            AppConfig::logError("Error loading active customers", ['error' => $e->getMessage()]);
            return [];
        }
    }
}
?>