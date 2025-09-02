<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/app.php';

class EDI862Parser {
    
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
    
    public function parseEDI($ediContent, $partnerId = null) {
        if (empty($ediContent)) {
            throw new Exception("EDI content is empty");
        }
        
        if (!$partnerId) {
            $partnerId = $this->getPartnerIdByCode('NIFCO');
        }
        
        $this->resetStats();
        AppConfig::logInfo("Starting EDI 862 parsing", ['partner_id' => $partnerId]);
        
        try {
            $this->db->beginTransaction();
            
            $segments = $this->parseEDISegments($ediContent);
            $this->validateEDIStructure($segments);
            
            $transactionSets = $this->extractTransactionSets($segments);
            
            foreach ($transactionSets as $transactionSet) {
                $this->processTransactionSet($transactionSet, $partnerId);
            }
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        
        $this->stats['end_time'] = microtime(true);
        $this->stats['duration'] = round($this->stats['end_time'] - $this->stats['start_time'], 2);
        
        AppConfig::logInfo("EDI 862 parsing completed", $this->stats);
        
        return [
            'success' => true,
            'records_processed' => $this->stats['processed'],
            'stats' => $this->stats
        ];
    }
    
    private function parseEDISegments($ediContent) {
        $segments = explode('~', trim($ediContent));
        $parsedSegments = [];
        
        foreach ($segments as $segment) {
            $segment = trim($segment);
            if (empty($segment)) continue;
            
            $elements = explode('*', $segment);
            $segmentType = $elements[0];
            
            $parsedSegments[] = [
                'type' => $segmentType,
                'elements' => $elements,
                'raw' => $segment
            ];
        }
        
        return $parsedSegments;
    }
    
    private function validateEDIStructure($segments) {
        if (empty($segments)) {
            throw new Exception("No EDI segments found");
        }
        
        $hasISA = false;
        $hasGS = false;
        $hasST = false;
        
        foreach ($segments as $segment) {
            switch ($segment['type']) {
                case 'ISA':
                    $hasISA = true;
                    break;
                case 'GS':
                    $hasGS = true;
                    break;
                case 'ST':
                    $hasST = true;
                    if (count($segment['elements']) > 1 && $segment['elements'][1] !== '862') {
                        AppConfig::logWarning("Unexpected transaction type: " . $segment['elements'][1]);
                    }
                    break;
            }
        }
        
        if (!$hasISA || !$hasGS || !$hasST) {
            throw new Exception("Invalid EDI structure - missing required envelope segments");
        }
    }
    
    private function extractTransactionSets($segments) {
        $transactionSets = [];
        $currentSet = [];
        $inTransactionSet = false;
        
        foreach ($segments as $segment) {
            if ($segment['type'] === 'ST') {
                if ($inTransactionSet) {
                    $transactionSets[] = $currentSet;
                }
                $currentSet = [$segment];
                $inTransactionSet = true;
            } elseif ($segment['type'] === 'SE') {
                if ($inTransactionSet) {
                    $currentSet[] = $segment;
                    $transactionSets[] = $currentSet;
                    $currentSet = [];
                    $inTransactionSet = false;
                }
            } elseif ($inTransactionSet) {
                $currentSet[] = $segment;
            }
        }
        
        return $transactionSets;
    }
    
    private function processTransactionSet($transactionSet, $partnerId) {
        $scheduleHeader = null;
        $lineItems = [];
        
        foreach ($transactionSet as $segment) {
            switch ($segment['type']) {
                case 'BSS':
                    $scheduleHeader = $this->parseBSSSegment($segment, $partnerId);
                    break;
                case 'FST':
                    $lineItems[] = $this->parseFSTSegment($segment, $scheduleHeader, $partnerId);
                    break;
                case 'SSD':
                    if (!empty($lineItems)) {
                        $this->processSSDSegment($segment, end($lineItems));
                    }
                    break;
            }
        }
        
        foreach ($lineItems as $lineItem) {
            if ($lineItem) {
                $this->processDeliverySchedule($lineItem);
                $this->stats['processed']++;
            }
        }
    }
    
    private function parseBSSSegment($segment, $partnerId) {
        $elements = $segment['elements'];
        
        return [
            'partner_id' => $partnerId,
            'transaction_type' => '862',
            'purpose_code' => $elements[1] ?? '00',
            'schedule_type' => $elements[2] ?? 'D',
            'schedule_quantity_qualifier' => $elements[3] ?? 'D',
            'transaction_date' => isset($elements[4]) ? $this->parseEDIDate($elements[4]) : date('Y-m-d')
        ];
    }
    
    private function parseFSTSegment($segment, $scheduleHeader, $partnerId) {
        $elements = $segment['elements'];
        
        if (count($elements) < 5) {
            $this->stats['errors'][] = "FST segment missing required elements: " . $segment['raw'];
            return null;
        }
        
        return [
            'partner_id' => $partnerId,
            'forecast_qualifier' => $elements[1] ?? 'D',
            'forecast_quantity' => (int)($elements[2] ?? 0),
            'forecast_quantity_qualifier' => $elements[3] ?? 'D',
            'forecast_date' => isset($elements[4]) ? $this->parseEDIDate($elements[4]) : null,
            'po_number' => null,
            'supplier_item' => null,
            'ship_to_location_id' => null
        ];
    }
    
    private function processSSDSegment($segment, &$lineItem) {
        $elements = $segment['elements'];
        
        if (count($elements) >= 3) {
            $lineItem['po_number'] = $elements[1] ?? null;
            $lineItem['supplier_item'] = $elements[2] ?? null;
            
            if (isset($elements[3]) && !empty($elements[3])) {
                $lineItem['ship_to_location_id'] = $this->getShipToLocationIdByCode(
                    $lineItem['partner_id'], 
                    $elements[3]
                );
            }
        }
    }
    
    private function parseEDIDate($ediDate) {
        if (strlen($ediDate) === 8) {
            return substr($ediDate, 0, 4) . '-' . substr($ediDate, 4, 2) . '-' . substr($ediDate, 6, 2);
        }
        
        if (strlen($ediDate) === 6) {
            $year = '20' . substr($ediDate, 0, 2);
            $month = substr($ediDate, 2, 2);
            $day = substr($ediDate, 4, 2);
            return $year . '-' . $month . '-' . $day;
        }
        
        return date('Y-m-d');
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
    
    private function getShipToLocationIdByCode($partnerId, $locationCode) {
        $stmt = $this->db->prepare("
            SELECT id FROM ship_to_locations 
            WHERE partner_id = ? AND location_code = ? AND active = 1
        ");
        $stmt->execute([$partnerId, $locationCode]);
        $result = $stmt->fetch();
        
        return $result ? $result['id'] : null;
    }
    
    private function processDeliverySchedule($data) {
        if (empty($data['po_number']) || empty($data['supplier_item'])) {
            $this->stats['skipped']++;
            return;
        }
        
        $poData = $this->parsePONumber($data['po_number']);
        $data['po_number'] = $poData['po_number'];
        $data['release_number'] = $poData['release_number'];
        $data['po_line'] = $data['po_number'] . '-' . ($data['release_number'] ?? '001');
        $data['customer_item'] = $data['supplier_item'];
        $data['item_description'] = 'EDI Imported Item';
        $data['quantity_ordered'] = $data['forecast_quantity'];
        $data['quantity_received'] = 0;
        $data['promised_date'] = $data['forecast_date'];
        $data['need_by_date'] = $data['forecast_date'];
        $data['ship_to_description'] = 'EDI Location';
        $data['uom'] = AppConfig::DEFAULT_UOM;
        $data['organization'] = AppConfig::DEFAULT_ORGANIZATION;
        $data['supplier'] = AppConfig::DEFAULT_SUPPLIER;
        
        $existingId = $this->findExistingSchedule($data);
        
        if ($existingId) {
            $this->updateSchedule($existingId, $data);
            $this->stats['updated']++;
        } else {
            $this->insertSchedule($data);
            $this->stats['inserted']++;
        }
    }
    
    private function parsePONumber($poNumber) {
        $parts = explode('-', trim($poNumber));
        return [
            'po_number' => $parts[0],
            'release_number' => isset($parts[1]) ? $parts[1] : '001'
        ];
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
        // Auto-detect new parts and add to part_master
        $this->autoDetectPart($data['supplier_item'], $data['item_description']);
        
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
    
    private function autoDetectPart($partNumber, $description = null) {
        if (empty($partNumber)) return;
        
        // Check if part already exists
        $checkSql = "SELECT id FROM part_master WHERE part_number = ?";
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([$partNumber]);
        
        if ($checkStmt->fetch()) {
            return; // Part already exists
        }
        
        // Insert new auto-detected part
        $insertSql = "
            INSERT IGNORE INTO part_master (
                part_number, description, qpc, auto_detected, 
                first_detected_date, created_at
            ) VALUES (
                ?, ?, 1, TRUE, NOW(), NOW()
            )
        ";
        
        $insertStmt = $this->db->prepare($insertSql);
        $insertStmt->execute([
            $partNumber,
            $description ?: 'Auto-detected from EDI processing'
        ]);
        
        if ($insertStmt->rowCount() > 0) {
            AppConfig::logInfo("Auto-detected new part: $partNumber", [
                'part_number' => $partNumber,
                'description' => $description
            ]);
        }
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
    
    public function getParsingStats() {
        return $this->stats;
    }
}