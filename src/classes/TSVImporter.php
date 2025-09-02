<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/app.php';

class TSVImporter {
    
    private $db;
    private $stats;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
        $this->resetStats();
    }
    
    private function resetStats() {
        $this->stats = [
            'processed' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'start_time' => microtime(true)
        ];
    }
    
    public function importFromFile($filePath, $partnerId = null) {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }
        
        if (!$partnerId) {
            $partnerId = $this->getPartnerIdByCode('NIFCO');
        }
        
        $this->resetStats();
        AppConfig::logInfo("Starting TSV import from: $filePath", ['partner_id' => $partnerId]);
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Cannot open file: $filePath");
        }
        
        try {
            $this->db->beginTransaction();
            
            $headers = $this->readHeaders($handle);
            $this->validateHeaders($headers);
            
            $lineNumber = 1;
            while (($row = fgetcsv($handle, 0, "\t")) !== FALSE) {
                $lineNumber++;
                
                try {
                    $data = $this->mapRowToData($headers, $row, $partnerId);
                    if ($data) {
                        $this->processDeliverySchedule($data);
                        $this->stats['processed']++;
                    } else {
                        $this->stats['skipped']++;
                    }
                } catch (Exception $e) {
                    $error = "Line $lineNumber: " . $e->getMessage();
                    $this->stats['errors'][] = $error;
                    AppConfig::logError("TSV import error", [
                        'file' => $filePath,
                        'line' => $lineNumber,
                        'error' => $e->getMessage(),
                        'row' => $row
                    ]);
                }
            }
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }
        
        $this->stats['end_time'] = microtime(true);
        $this->stats['duration'] = round($this->stats['end_time'] - $this->stats['start_time'], 2);
        
        AppConfig::logInfo("TSV import completed", $this->stats);
        
        return $this->stats;
    }
    
    private function readHeaders($handle) {
        $headers = fgetcsv($handle, 0, "\t");
        if (!$headers) {
            throw new Exception("Cannot read headers from TSV file");
        }
        
        return array_map('trim', $headers);
    }
    
    private function validateHeaders($headers) {
        $requiredHeaders = [
            'PO Number',
            'Supplier Item', 
            'Item Description',
            'Quantity Ordered',
            'Promised Date',
            'Ship-To Location'
        ];
        
        foreach ($requiredHeaders as $required) {
            if (!in_array($required, $headers)) {
                throw new Exception("Missing required header: $required");
            }
        }
    }
    
    private function mapRowToData($headers, $row, $partnerId) {
        if (count($row) !== count($headers)) {
            throw new Exception("Row column count doesn't match headers");
        }
        
        $data = array_combine($headers, $row);
        
        if (empty(trim($data['PO Number'])) || empty(trim($data['Supplier Item']))) {
            return null;
        }
        
        $poData = $this->parsePONumber($data['PO Number']);
        $shipToLocationId = $this->getShipToLocationId($partnerId, $data['Ship-To Location']);
        
        return [
            'partner_id' => $partnerId,
            'po_number' => $poData['po_number'],
            'release_number' => $poData['release_number'],
            'po_line' => $data['PO Number'],
            'supplier_item' => trim($data['Supplier Item']),
            'customer_item' => !empty($data['Item Number']) ? trim($data['Item Number']) : trim($data['Supplier Item']),
            'item_description' => trim($data['Item Description']),
            'quantity_ordered' => (int) $data['Quantity Ordered'],
            'quantity_received' => !empty($data['Quantity Received']) ? (int) $data['Quantity Received'] : 0,
            'promised_date' => $this->parseDate($data['Promised Date']),
            'need_by_date' => !empty($data['Need-By Date']) ? $this->parseDate($data['Need-By Date']) : $this->parseDate($data['Promised Date']),
            'ship_to_location_id' => $shipToLocationId,
            'ship_to_description' => trim($data['Ship-To Location']),
            'uom' => !empty($data['UOM']) ? $data['UOM'] : AppConfig::DEFAULT_UOM,
            'organization' => !empty($data['Organization']) ? $data['Organization'] : AppConfig::DEFAULT_ORGANIZATION,
            'supplier' => !empty($data['Supplier']) ? $data['Supplier'] : AppConfig::DEFAULT_SUPPLIER
        ];
    }
    
    private function parsePONumber($poNumber) {
        $parts = explode('-', trim($poNumber));
        return [
            'po_number' => $parts[0],
            'release_number' => isset($parts[1]) ? $parts[1] : null
        ];
    }
    
    private function parseDate($dateString) {
        $dateString = trim($dateString);
        
        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $dateString, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[1], $matches[2]);
        }
        
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            throw new Exception("Invalid date format: $dateString");
        }
        
        return date('Y-m-d', $timestamp);
    }
    
    private function getPartnerIdByCode($code) {
        $stmt = $this->db->prepare("SELECT id FROM trading_partners WHERE partner_code = ? AND status = 'active'");
        $stmt->execute([$code]);
        $result = $stmt->fetch();
        
        if (!$result) {
            throw new Exception("Trading partner not found: $code");
        }
        
        return $result['id'];
    }
    
    private function getShipToLocationId($partnerId, $locationDescription) {
        $stmt = $this->db->prepare("
            SELECT id FROM ship_to_locations 
            WHERE partner_id = ? AND location_description = ? AND active = 1
        ");
        $stmt->execute([$partnerId, trim($locationDescription)]);
        $result = $stmt->fetch();
        
        return $result ? $result['id'] : null;
    }
    
    private function processDeliverySchedule($data) {
        $existingId = $this->findExistingSchedule($data);
        
        if ($existingId) {
            $this->updateSchedule($existingId, $data);
            $this->stats['updated']++;
        } else {
            $this->insertSchedule($data);
            $this->stats['inserted']++;
        }
    }
    
    private function findExistingSchedule($data) {
        $stmt = $this->db->prepare("
            SELECT id FROM delivery_schedules 
            WHERE partner_id = ? AND po_number = ? AND release_number = ? 
            AND supplier_item = ? AND promised_date = ? 
            AND status IN ('active', 'shipped')
        ");
        
        $stmt->execute([
            $data['partner_id'],
            $data['po_number'],
            $data['release_number'],
            $data['supplier_item'],
            $data['promised_date']
        ]);
        
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    private function insertSchedule($data) {
        $sql = "
            INSERT INTO delivery_schedules (
                partner_id, po_number, release_number, po_line, line_number,
                supplier_item, customer_item, item_description, quantity_ordered, quantity_received,
                promised_date, need_by_date, ship_to_location_id, ship_to_description,
                uom, organization, supplier, status, priority, created_at
            ) VALUES (
                :partner_id, :po_number, :release_number, :po_line, 1,
                :supplier_item, :customer_item, :item_description, :quantity_ordered, :quantity_received,
                :promised_date, :need_by_date, :ship_to_location_id, :ship_to_description,
                :uom, :organization, :supplier, 'active', 'normal', NOW()
            )
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
    }
    
    private function updateSchedule($id, $data) {
        $sql = "
            UPDATE delivery_schedules SET
                quantity_ordered = :quantity_ordered,
                quantity_received = :quantity_received,
                item_description = :item_description,
                ship_to_location_id = :ship_to_location_id,
                ship_to_description = :ship_to_description,
                updated_at = NOW()
            WHERE id = :id
        ";
        
        $data['id'] = $id;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'quantity_ordered' => $data['quantity_ordered'],
            'quantity_received' => $data['quantity_received'],
            'item_description' => $data['item_description'],
            'ship_to_location_id' => $data['ship_to_location_id'],
            'ship_to_description' => $data['ship_to_description']
        ]);
    }
    
    public function getImportStats() {
        return $this->stats;
    }
}
?>