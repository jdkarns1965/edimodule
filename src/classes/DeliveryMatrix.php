<?php

namespace Greenfield\EDI;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class DeliveryMatrix {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    public function getDeliveryMatrix($filters = []) {
        $sql = "SELECT 
            ds.*,
            pm.qpc,
            pm.description as part_description,
            pm.product_family,
            pm.weight,
            ROUND(ds.quantity_ordered / COALESCE(pm.qpc, 1), 0) as containers_needed,
            CASE 
                WHEN ds.location_code = 'SLB' AND ds.po_number REGEXP '-[0-9]+$' THEN 
                    CONCAT(SUBSTRING_INDEX(ds.po_number, '-', 1), '-', LPAD(SUBSTRING_INDEX(ds.po_number, '-', -1), 3, '0'))
                ELSE ds.po_number 
            END as formatted_po,
            stl.location_description as location_name,
            stl.location_code
        FROM delivery_schedules ds
        LEFT JOIN part_master pm ON ds.supplier_item = pm.part_number
        LEFT JOIN ship_to_locations stl ON ds.ship_to_location_id = stl.id
        WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['location_code'])) {
            $sql .= " AND COALESCE(ds.location_code, stl.location_code) = :location_code";
            $params[':location_code'] = $filters['location_code'];
        }
        
        if (!empty($filters['part_number'])) {
            $sql .= " AND ds.supplier_item LIKE :part_number";
            $params[':part_number'] = '%' . $filters['part_number'] . '%';
        }
        
        if (!empty($filters['po_number'])) {
            $sql .= " AND ds.po_number LIKE :po_number";
            $params[':po_number'] = '%' . $filters['po_number'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND ds.promised_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND ds.promised_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND ds.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['product_family'])) {
            $sql .= " AND pm.product_family = :product_family";
            $params[':product_family'] = $filters['product_family'];
        }
        
        $sql .= " ORDER BY ds.promised_date ASC, ds.po_number ASC, ds.supplier_item ASC";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getLocationSummary($filters = []) {
        $sql = "SELECT 
            COALESCE(ds.location_code, stl.location_code, 'UNKNOWN') as location_code,
            COALESCE(stl.location_description, 'Unknown Location') as location_name,
            COUNT(*) as total_lines,
            SUM(ds.quantity_ordered) as total_quantity,
            SUM(ROUND(ds.quantity_ordered / COALESCE(pm.qpc, 1), 0)) as total_containers,
            COUNT(DISTINCT ds.supplier_item) as unique_parts,
            COUNT(DISTINCT ds.po_number) as unique_pos,
            MIN(ds.promised_date) as earliest_date,
            MAX(ds.promised_date) as latest_date
        FROM delivery_schedules ds
        LEFT JOIN part_master pm ON ds.supplier_item = pm.part_number
        LEFT JOIN ship_to_locations stl ON ds.ship_to_location_id = stl.id
        WHERE ds.status = 'active'";
        
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND ds.promised_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND ds.promised_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $sql .= " GROUP BY COALESCE(ds.location_code, stl.location_code, 'UNKNOWN'), 
                           COALESCE(stl.location_description, 'Unknown Location')
                  ORDER BY location_code";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function exportToExcel($data, $templateType = 'delivery_matrix', $filters = []) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        switch ($templateType) {
            case 'delivery_matrix':
                return $this->createDeliveryMatrixExport($spreadsheet, $sheet, $data, $filters);
            case 'daily_production':
                return $this->createDailyProductionExport($spreadsheet, $sheet, $data, $filters);
            case 'weekly_planning':
                return $this->createWeeklyPlanningExport($spreadsheet, $sheet, $data, $filters);
            case 'location_specific':
                return $this->createLocationSpecificExport($spreadsheet, $sheet, $data, $filters);
            case 'po_specific':
                return $this->createPOSpecificExport($spreadsheet, $sheet, $data, $filters);
            default:
                return $this->createDeliveryMatrixExport($spreadsheet, $sheet, $data, $filters);
        }
    }
    
    private function createDeliveryMatrixExport($spreadsheet, $sheet, $data, $filters) {
        // Set document properties
        $properties = $spreadsheet->getProperties();
        $properties->setCreator('EDI Processing Module')
                   ->setLastModifiedBy('EDI Processing Module')
                   ->setTitle('Delivery Schedule Matrix')
                   ->setSubject('EDI Delivery Schedules')
                   ->setDescription('Generated delivery schedule matrix from EDI processing system')
                   ->setKeywords('EDI delivery schedule matrix export')
                   ->setCategory('Reports');
        
        $sheet->setTitle($this->sanitizeSheetTitle('Delivery Matrix'));
        
        // Set headers
        $headers = [
            'A1' => 'PO Number',
            'B1' => 'Release',
            'C1' => 'Part Number',
            'D1' => 'Description',
            'E1' => 'QPC',
            'F1' => 'Quantity Ordered',
            'G1' => 'Containers',
            'H1' => 'UOM',
            'I1' => 'Promised Date',
            'J1' => 'Need By Date',
            'K1' => 'Location',
            'L1' => 'Status',
            'M1' => 'Product Family',
            'N1' => 'Weight',
            'O1' => 'Notes'
        ];
        
        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }
        
        // Style header row
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '366092']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:O1')->applyFromArray($headerStyle);
        
        // Add data rows
        $row = 2;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item['formatted_po'] ?? $item['po_number'] ?? '');
            $sheet->setCellValue('B' . $row, $item['release_number'] ?? '');
            $sheet->setCellValue('C' . $row, $item['supplier_item'] ?? '');
            $sheet->setCellValue('D' . $row, $item['part_description'] ?? $item['item_description'] ?? '');
            $sheet->setCellValue('E' . $row, (int)($item['qpc'] ?? 1));
            $sheet->setCellValue('F' . $row, (int)($item['quantity_ordered'] ?? 0));
            $sheet->setCellValue('G' . $row, (int)($item['containers_needed'] ?? round(($item['quantity_ordered'] ?? 0) / ($item['qpc'] ?? 1))));
            $sheet->setCellValue('H' . $row, $item['uom'] ?? 'EACH');
            $sheet->setCellValue('I' . $row, $item['promised_date'] ?? '');
            $sheet->setCellValue('J' . $row, $item['need_by_date'] ?? '');
            $sheet->setCellValue('K' . $row, $item['location_code'] ?? $item['ship_to_description'] ?? '');
            $sheet->setCellValue('L' . $row, ucfirst($item['status'] ?? 'unknown'));
            $sheet->setCellValue('M' . $row, $item['product_family'] ?? '');
            $sheet->setCellValue('N' . $row, $item['weight'] ? (float)$item['weight'] : '');
            $sheet->setCellValue('O' . $row, $item['notes'] ?? '');
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Add filters and format
        $sheet->setAutoFilter('A1:O' . ($row - 1));
        
        // Create summary at the bottom
        $summaryRow = $row + 2;
        $sheet->setCellValue('A' . $summaryRow, 'Summary:');
        $sheet->setCellValue('B' . $summaryRow, 'Total Records: ' . count($data));
        
        // Calculate totals safely
        $totalQuantity = 0;
        $totalContainers = 0;
        foreach ($data as $item) {
            $totalQuantity += (int)($item['quantity_ordered'] ?? 0);
            $totalContainers += (int)($item['containers_needed'] ?? round(($item['quantity_ordered'] ?? 0) / ($item['qpc'] ?? 1)));
        }
        
        $sheet->setCellValue('D' . $summaryRow, 'Total Quantity: ' . number_format($totalQuantity));
        $sheet->setCellValue('F' . $summaryRow, 'Total Containers: ' . number_format($totalContainers));
        
        $sheet->getStyle('A' . $summaryRow . ':F' . $summaryRow)->applyFromArray(['font' => ['bold' => true]]);
        
        return $this->writeExcelFile($spreadsheet, 'delivery_matrix_' . date('Y-m-d'));
    }
    
    private function createDailyProductionExport($spreadsheet, $sheet, $data, $filters) {
        $sheet->setTitle($this->sanitizeSheetTitle('Daily Production Plan'));
        
        // Group by date and location
        $dailyData = [];
        foreach ($data as $item) {
            $date = $item['promised_date'];
            $location = $item['location_code'] ?? 'UNKNOWN';
            $key = $date . '_' . $location;
            
            if (!isset($dailyData[$key])) {
                $dailyData[$key] = [
                    'date' => $date,
                    'location' => $location,
                    'items' => [],
                    'total_containers' => 0,
                    'total_parts' => 0
                ];
            }
            
            $dailyData[$key]['items'][] = $item;
            $dailyData[$key]['total_containers'] += $item['containers_needed'] ?? 0;
            $dailyData[$key]['total_parts'] += $item['quantity_ordered'];
        }
        
        // Create daily production format
        $headers = [
            'A1' => 'Date',
            'B1' => 'Location',
            'C1' => 'Part Number',
            'D1' => 'Description',
            'E1' => 'Quantity',
            'F1' => 'Containers',
            'G1' => 'PO Number',
            'H1' => 'Status'
        ];
        
        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }
        
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '366092']]
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
        
        $row = 2;
        foreach ($dailyData as $dayData) {
            foreach ($dayData['items'] as $item) {
                $sheet->setCellValue('A' . $row, $dayData['date']);
                $sheet->setCellValue('B' . $row, $dayData['location']);
                $sheet->setCellValue('C' . $row, $item['supplier_item']);
                $sheet->setCellValue('D' . $row, $item['part_description'] ?? $item['item_description']);
                $sheet->setCellValue('E' . $row, $item['quantity_ordered']);
                $sheet->setCellValue('F' . $row, $item['containers_needed']);
                $sheet->setCellValue('G' . $row, $item['formatted_po'] ?? $item['po_number']);
                $sheet->setCellValue('H' . $row, ucfirst($item['status']));
                $row++;
            }
        }
        
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        return $this->writeExcelFile($spreadsheet, 'daily_production_' . date('Y-m-d'));
    }
    
    private function createLocationSpecificExport($spreadsheet, $sheet, $data, $filters) {
        $locationCode = $filters['location_code'] ?? 'ALL';
        $sheet->setTitle($this->sanitizeSheetTitle('Location ' . $locationCode));
        
        // Headers for location-specific export
        $headers = [
            'A1' => 'PO Number',
            'B1' => 'Release',
            'C1' => 'Part Number',
            'D1' => 'Description',
            'E1' => 'Quantity',
            'F1' => 'QPC',
            'G1' => 'Containers',
            'H1' => 'Promised Date',
            'I1' => 'Status'
        ];
        
        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }
        
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '366092']]
        ];
        $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);
        
        $row = 2;
        foreach ($data as $item) {
            // Handle SLB sequential releases vs CNL/CWH duplicate releases
            $poDisplay = $item['po_number'];
            if ($locationCode === 'SLB' && strpos($item['po_number'], '-') !== false) {
                $parts = explode('-', $item['po_number']);
                if (count($parts) === 2) {
                    $poDisplay = $parts[0] . '-' . str_pad($parts[1], 3, '0', STR_PAD_LEFT);
                }
            }
            
            $sheet->setCellValue('A' . $row, $poDisplay);
            $sheet->setCellValue('B' . $row, $item['release_number']);
            $sheet->setCellValue('C' . $row, $item['supplier_item']);
            $sheet->setCellValue('D' . $row, $item['part_description'] ?? $item['item_description']);
            $sheet->setCellValue('E' . $row, $item['quantity_ordered']);
            $sheet->setCellValue('F' . $row, $item['qpc'] ?? 1);
            $sheet->setCellValue('G' . $row, $item['containers_needed']);
            $sheet->setCellValue('H' . $row, $item['promised_date']);
            $sheet->setCellValue('I' . $row, ucfirst($item['status']));
            $row++;
        }
        
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        return $this->writeExcelFile($spreadsheet, 'location_' . $locationCode . '_' . date('Y-m-d'));
    }
    
    private function createWeeklyPlanningExport($spreadsheet, $sheet, $data, $filters) {
        $sheet->setTitle($this->sanitizeSheetTitle('Weekly Planning'));
        
        // Group by week
        $weeklyData = [];
        foreach ($data as $item) {
            $date = new \DateTime($item['promised_date']);
            $weekStart = $date->modify('monday this week')->format('Y-m-d');
            $weekKey = $weekStart;
            
            if (!isset($weeklyData[$weekKey])) {
                $weeklyData[$weekKey] = [
                    'week_start' => $weekStart,
                    'items' => [],
                    'total_quantity' => 0,
                    'total_containers' => 0
                ];
            }
            
            $weeklyData[$weekKey]['items'][] = $item;
            $weeklyData[$weekKey]['total_quantity'] += (int)($item['quantity_ordered'] ?? 0);
            $weeklyData[$weekKey]['total_containers'] += (int)($item['containers_needed'] ?? 0);
        }
        
        // Headers
        $headers = [
            'A1' => 'Week Starting',
            'B1' => 'Location',
            'C1' => 'Part Number',
            'D1' => 'Description',
            'E1' => 'Quantity',
            'F1' => 'Containers',
            'G1' => 'PO Number',
            'H1' => 'Promised Date'
        ];
        
        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }
        
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '366092']]
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
        
        // Data rows
        $row = 2;
        foreach ($weeklyData as $week) {
            foreach ($week['items'] as $item) {
                $sheet->setCellValue('A' . $row, $week['week_start']);
                $sheet->setCellValue('B' . $row, $item['location_code'] ?? '');
                $sheet->setCellValue('C' . $row, $item['supplier_item'] ?? '');
                $sheet->setCellValue('D' . $row, $item['part_description'] ?? $item['item_description'] ?? '');
                $sheet->setCellValue('E' . $row, (int)($item['quantity_ordered'] ?? 0));
                $sheet->setCellValue('F' . $row, (int)($item['containers_needed'] ?? 0));
                $sheet->setCellValue('G' . $row, $item['formatted_po'] ?? $item['po_number'] ?? '');
                $sheet->setCellValue('H' . $row, $item['promised_date'] ?? '');
                $row++;
            }
        }
        
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        return $this->writeExcelFile($spreadsheet, 'weekly_planning_' . date('Y-m-d'));
    }
    
    private function createPOSpecificExport($spreadsheet, $sheet, $data, $filters) {
        $poNumber = $filters['po_number'] ?? 'ALL';
        $sheet->setTitle($this->sanitizeSheetTitle('PO ' . $poNumber));
        
        // Group by PO number
        $poData = [];
        foreach ($data as $item) {
            $po = $item['po_number'];
            if (!isset($poData[$po])) {
                $poData[$po] = [
                    'po_number' => $po,
                    'items' => [],
                    'total_quantity' => 0,
                    'total_containers' => 0
                ];
            }
            
            $poData[$po]['items'][] = $item;
            $poData[$po]['total_quantity'] += (int)($item['quantity_ordered'] ?? 0);
            $poData[$po]['total_containers'] += (int)($item['containers_needed'] ?? 0);
        }
        
        // Headers
        $headers = [
            'A1' => 'PO Number',
            'B1' => 'Release',
            'C1' => 'Part Number',
            'D1' => 'Description',
            'E1' => 'Quantity',
            'F1' => 'QPC',
            'G1' => 'Containers',
            'H1' => 'Promised Date',
            'I1' => 'Location',
            'J1' => 'Status'
        ];
        
        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }
        
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '366092']]
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
        
        // Data rows
        $row = 2;
        foreach ($poData as $po) {
            foreach ($po['items'] as $item) {
                $sheet->setCellValue('A' . $row, $item['formatted_po'] ?? $item['po_number'] ?? '');
                $sheet->setCellValue('B' . $row, $item['release_number'] ?? '');
                $sheet->setCellValue('C' . $row, $item['supplier_item'] ?? '');
                $sheet->setCellValue('D' . $row, $item['part_description'] ?? $item['item_description'] ?? '');
                $sheet->setCellValue('E' . $row, (int)($item['quantity_ordered'] ?? 0));
                $sheet->setCellValue('F' . $row, (int)($item['qpc'] ?? 1));
                $sheet->setCellValue('G' . $row, (int)($item['containers_needed'] ?? 0));
                $sheet->setCellValue('H' . $row, $item['promised_date'] ?? '');
                $sheet->setCellValue('I' . $row, $item['location_code'] ?? '');
                $sheet->setCellValue('J' . $row, ucfirst($item['status'] ?? 'unknown'));
                $row++;
            }
        }
        
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        return $this->writeExcelFile($spreadsheet, 'po_specific_' . date('Y-m-d'));
    }
    
    private function writeExcelFile($spreadsheet, $filename) {
        try {
            // Clean filename for safety and add timestamp
            $cleanFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
            $timestampFilename = $cleanFilename . '_' . date('Y-m-d_H-i-s') . '.xlsx';
            
            // Use the same exports directory as shipment builder
            $exportsDir = dirname(__DIR__, 2) . '/data/exports';
            $filepath = $exportsDir . '/' . $timestampFilename;
            
            // Create writer and configure
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            
            // Ensure exports directory exists and is writable
            if (!is_dir($exportsDir)) {
                mkdir($exportsDir, 0755, true);
            }
            
            if (!is_writable($exportsDir)) {
                throw new \Exception('Exports directory is not writable: ' . $exportsDir);
            }
            
            // Save file
            $writer->save($filepath);
            
            // Verify file was created
            if (!file_exists($filepath)) {
                throw new \Exception('Excel file was not created');
            }
            
            $filesize = filesize($filepath);
            if ($filesize === false || $filesize === 0) {
                throw new \Exception('Excel file is empty');
            }
            
            // Return the download URL instead of file path
            return 'download.php?file=' . $timestampFilename;
            
        } catch (\Exception $e) {
            error_log('Excel file creation error: ' . $e->getMessage());
            throw new \Exception('Failed to create Excel file: ' . $e->getMessage());
        }
    }
    
    public function getLocationCodes() {
        $sql = "SELECT DISTINCT 
            COALESCE(ds.location_code, stl.location_code, 'UNKNOWN') as location_code,
            COALESCE(stl.location_description, 'Unknown Location') as location_name
        FROM delivery_schedules ds
        LEFT JOIN ship_to_locations stl ON ds.ship_to_location_id = stl.id
        WHERE COALESCE(ds.location_code, stl.location_code) IS NOT NULL
        ORDER BY location_code";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    public function getProductFamilies() {
        $sql = "SELECT DISTINCT pm.product_family 
        FROM delivery_schedules ds
        JOIN part_master pm ON ds.supplier_item = pm.part_number
        WHERE pm.product_family IS NOT NULL AND pm.product_family != ''
        ORDER BY pm.product_family";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
    
    /**
     * Sanitize sheet title to comply with Excel naming restrictions
     * - Remove invalid characters: : * ? / \ [ ]
     * - Limit to 31 characters
     * - Ensure not empty
     */
    private function sanitizeSheetTitle($title) {
        // Remove invalid characters
        $sanitized = preg_replace('/[:\*\?\/\\\[\]]/', '', $title);
        
        // Trim whitespace
        $sanitized = trim($sanitized);
        
        // Limit length to 31 characters
        if (strlen($sanitized) > 31) {
            $sanitized = substr($sanitized, 0, 31);
        }
        
        // Ensure not empty
        if (empty($sanitized)) {
            $sanitized = 'Sheet1';
        }
        
        return $sanitized;
    }
}