<?php

namespace Greenfield\EDI;

class PartMaster {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    public function getAllParts($search = '', $activeOnly = true, $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM part_master WHERE 1=1";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " AND active = 1";
        }
        
        if (!empty($search)) {
            $sql .= " AND (part_number LIKE :search1 OR customer_part_number LIKE :search2 OR description LIKE :search3)";
            $params[':search1'] = "%{$search}%";
            $params[':search2'] = "%{$search}%";
            $params[':search3'] = "%{$search}%";
        }
        
        $sql .= " ORDER BY part_number ASC LIMIT :limit OFFSET :offset";
        
        // Add limit and offset to params array
        $params[':limit'] = (int)$limit;
        $params[':offset'] = (int)$offset;
        
        $stmt = $this->db->prepare($sql);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getPartByNumber($partNumber) {
        $sql = "SELECT * FROM part_master WHERE part_number = :part_number";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':part_number', $partNumber);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    public function createPart($data) {
        $sql = "INSERT INTO part_master (
            part_number, customer_part_number, description, qpc, uom, 
            weight, dimensions, material, color, product_family, 
            active, notes
        ) VALUES (
            :part_number, :customer_part_number, :description, :qpc, :uom,
            :weight, :dimensions, :material, :color, :product_family,
            :active, :notes
        )";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':part_number' => $data['part_number'],
            ':customer_part_number' => $data['customer_part_number'] ?: null,
            ':description' => $data['description'] ?: null,
            ':qpc' => (int)($data['qpc'] ?: 1),
            ':uom' => $data['uom'] ?: 'EACH',
            ':weight' => $data['weight'] ? (float)$data['weight'] : null,
            ':dimensions' => $data['dimensions'] ?: null,
            ':material' => $data['material'] ?: null,
            ':color' => $data['color'] ?: null,
            ':product_family' => $data['product_family'] ?: null,
            ':active' => isset($data['active']) ? (bool)$data['active'] : true,
            ':notes' => $data['notes'] ?: null
        ]);
    }
    
    public function updatePart($partNumber, $data) {
        $sql = "UPDATE part_master SET 
            customer_part_number = :customer_part_number,
            description = :description,
            qpc = :qpc,
            uom = :uom,
            weight = :weight,
            dimensions = :dimensions,
            material = :material,
            color = :color,
            product_family = :product_family,
            active = :active,
            notes = :notes,
            updated_at = CURRENT_TIMESTAMP
        WHERE part_number = :part_number";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':part_number' => $partNumber,
            ':customer_part_number' => $data['customer_part_number'] ?: null,
            ':description' => $data['description'] ?: null,
            ':qpc' => (int)($data['qpc'] ?: 1),
            ':uom' => $data['uom'] ?: 'EACH',
            ':weight' => $data['weight'] ? (float)$data['weight'] : null,
            ':dimensions' => $data['dimensions'] ?: null,
            ':material' => $data['material'] ?: null,
            ':color' => $data['color'] ?: null,
            ':product_family' => $data['product_family'] ?: null,
            ':active' => isset($data['active']) ? (bool)$data['active'] : true,
            ':notes' => $data['notes'] ?: null
        ]);
    }
    
    public function deletePart($partNumber) {
        $sql = "UPDATE part_master SET active = 0 WHERE part_number = :part_number";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':part_number' => $partNumber]);
    }
    
    public function getPartCount($search = '', $activeOnly = true) {
        $sql = "SELECT COUNT(*) as total FROM part_master WHERE 1=1";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " AND active = 1";
        }
        
        if (!empty($search)) {
            $sql .= " AND (part_number LIKE :search1 OR customer_part_number LIKE :search2 OR description LIKE :search3)";
            $params[':search1'] = "%{$search}%";
            $params[':search2'] = "%{$search}%";
            $params[':search3'] = "%{$search}%";
        }
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    public function importPartsFromCSV($csvData) {
        $imported = 0;
        $errors = [];
        
        $this->db->beginTransaction();
        
        try {
            foreach ($csvData as $rowIndex => $row) {
                if ($rowIndex === 0) continue; // Skip header row
                
                if (empty($row[0])) continue; // Skip empty part numbers
                
                $partData = [
                    'part_number' => trim($row[0]),
                    'customer_part_number' => isset($row[1]) ? trim($row[1]) : '',
                    'description' => isset($row[2]) ? trim($row[2]) : '',
                    'qpc' => isset($row[3]) && is_numeric($row[3]) ? (int)$row[3] : 1,
                    'uom' => isset($row[4]) ? trim($row[4]) : 'EACH',
                    'weight' => isset($row[5]) && is_numeric($row[5]) ? (float)$row[5] : null,
                    'dimensions' => isset($row[6]) ? trim($row[6]) : '',
                    'material' => isset($row[7]) ? trim($row[7]) : '',
                    'color' => isset($row[8]) ? trim($row[8]) : '',
                    'product_family' => isset($row[9]) ? trim($row[9]) : '',
                    'notes' => isset($row[10]) ? trim($row[10]) : ''
                ];
                
                try {
                    // Check if part exists
                    $existingPart = $this->getPartByNumber($partData['part_number']);
                    
                    if ($existingPart) {
                        // Update existing part
                        $this->updatePart($partData['part_number'], $partData);
                    } else {
                        // Create new part
                        $this->createPart($partData);
                    }
                    
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($rowIndex + 1) . ": " . $e->getMessage();
                }
            }
            
            $this->db->commit();
            return ['success' => true, 'imported' => $imported, 'errors' => $errors];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage(), 'imported' => 0, 'errors' => $errors];
        }
    }
    
    public function autoDetectNewParts() {
        $sql = "INSERT IGNORE INTO part_master (part_number, description, qpc, auto_detected, first_detected_date) 
                SELECT DISTINCT 
                    ds.supplier_item as part_number,
                    COALESCE(ds.item_description, 'Auto-detected from EDI') as description,
                    1 as qpc,
                    TRUE as auto_detected,
                    MIN(ds.created_at) as first_detected_date
                FROM delivery_schedules ds 
                LEFT JOIN part_master pm ON ds.supplier_item = pm.part_number
                WHERE ds.supplier_item IS NOT NULL 
                AND pm.part_number IS NULL
                GROUP BY ds.supplier_item, ds.item_description";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    public function getProductFamilies() {
        $sql = "SELECT DISTINCT product_family FROM part_master WHERE product_family IS NOT NULL AND product_family != '' ORDER BY product_family";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
    
    public function getMaterials() {
        $sql = "SELECT DISTINCT material FROM part_master WHERE material IS NOT NULL AND material != '' ORDER BY material";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}