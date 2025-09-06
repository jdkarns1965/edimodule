<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ExcelExportService {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
    }
    
    /**
     * Export shipment data for Excel templates
     */
    public function exportShipmentForExcel($shipmentId, $templateType = 'packing_list', $format = 'xlsx') {
        $sql = "SELECT s.*, 
                       tp.name as customer_name, tp.partner_code,
                       sl.location_description, sl.location_code, sl.address, sl.city, sl.state
                FROM shipments s
                JOIN trading_partners tp ON s.partner_id = tp.id
                LEFT JOIN ship_to_locations sl ON s.ship_to_location_id = sl.id
                WHERE s.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$shipmentId]);
        $shipment = $stmt->fetch();
        
        if (!$shipment) {
            throw new Exception("Shipment not found");
        }
        
        // Get shipment items
        $itemsSql = "SELECT si.*, ds.po_number, ds.release_number, ds.item_description as full_description
                     FROM shipment_items si
                     LEFT JOIN delivery_schedules ds ON si.delivery_schedule_id = ds.id
                     WHERE si.shipment_id = ?
                     ORDER BY si.po_line";
        
        $itemsStmt = $this->db->prepare($itemsSql);
        $itemsStmt->execute([$shipmentId]);
        $items = $itemsStmt->fetchAll();
        
        switch ($templateType) {
            case 'packing_list':
                return $this->createPackingListExport($shipment, $items, $format);
            case 'pick_list':
                return $this->createPickListExport($shipment, $items, $format);
            case 'bol':
                return $this->createBOLExport($shipment, $items, $format);
            default:
                return $this->createPackingListExport($shipment, $items, $format);
        }
    }
    
    /**
     * Export delivery schedules to Excel
     */
    public function exportDeliverySchedule($customerId = null, $dateRange = null, $format = 'xlsx') {
        $sql = "SELECT ds.*, 
                       tp.name as customer_name, tp.partner_code,
                       sl.location_description, sl.location_code
                FROM delivery_schedules ds
                JOIN trading_partners tp ON ds.partner_id = tp.id
                LEFT JOIN ship_to_locations sl ON ds.ship_to_location_id = sl.id
                WHERE ds.status = 'active'";
        
        $params = [];
        
        if ($customerId) {
            $sql .= " AND ds.partner_id = ?";
            $params[] = $customerId;
        }
        
        if ($dateRange) {
            $sql .= " AND ds.promised_date BETWEEN ? AND ?";
            $params[] = $dateRange['start'];
            $params[] = $dateRange['end'];
        }
        
        $sql .= " ORDER BY ds.promised_date, ds.supplier_item";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $schedules = $stmt->fetchAll();
        
        return $this->createDeliveryScheduleExport($schedules, $format);
    }
    
    /**
     * Export inventory report
     */
    public function exportInventoryReport($dateRange = null, $location = null, $format = 'xlsx') {
        // For now, return part data from delivery schedules as inventory proxy
        $sql = "SELECT 
                    ds.supplier_item as part_number,
                    ds.item_description,
                    SUM(ds.quantity_ordered - ds.quantity_shipped) as available_quantity,
                    COUNT(DISTINCT ds.po_number) as open_orders,
                    MIN(ds.promised_date) as earliest_delivery,
                    MAX(ds.promised_date) as latest_delivery,
                    tp.name as customer_name
                FROM delivery_schedules ds
                JOIN trading_partners tp ON ds.partner_id = tp.id
                WHERE ds.status = 'active' 
                AND (ds.quantity_ordered - ds.quantity_shipped) > 0";
        
        $params = [];
        
        if ($dateRange) {
            $sql .= " AND ds.promised_date BETWEEN ? AND ?";
            $params[] = $dateRange['start'];
            $params[] = $dateRange['end'];
        }
        
        $sql .= " GROUP BY ds.supplier_item, ds.item_description, tp.name
                  ORDER BY ds.supplier_item";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $inventory = $stmt->fetchAll();
        
        return $this->createInventoryExport($inventory, $format);
    }
    
    /**
     * Create packing list export
     */
    private function createPackingListExport($shipment, $items, $format) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Packing List');
        
        // Header information
        $sheet->setCellValue('A1', 'PACKING LIST');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->mergeCells('A1:F1');
        
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Shipment Number:')->setCellValue('B' . $row, $shipment['shipment_number']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Customer:')->setCellValue('B' . $row, $shipment['customer_name']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Ship Date:')->setCellValue('B' . $row, $shipment['ship_date']);
        $row++;
        $sheet->setCellValue('A' . $row, 'PO Number:')->setCellValue('B' . $row, $shipment['po_number']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Ship To:')->setCellValue('B' . $row, $shipment['location_description']);
        $row++;
        
        if ($shipment['carrier_name']) {
            $sheet->setCellValue('A' . $row, 'Carrier:')->setCellValue('B' . $row, $shipment['carrier_name']);
            $row++;
        }
        
        if ($shipment['bol_number']) {
            $sheet->setCellValue('A' . $row, 'BOL Number:')->setCellValue('B' . $row, $shipment['bol_number']);
            $row++;
        }
        
        $row += 2;
        
        // Items header
        $headers = ['Part Number', 'Description', 'PO Line', 'Quantity', 'Containers', 'Lot Number'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $col++;
        }
        
        $this->applyHeaderStyle($sheet, 'A' . $row . ':F' . $row);
        $row++;
        
        // Items data
        foreach ($items as $item) {
            $sheet->setCellValue('A' . $row, $item['supplier_item']);
            $sheet->setCellValue('B' . $row, $item['full_description'] ?: $item['item_description']);
            $sheet->setCellValue('C' . $row, $item['po_line']);
            $sheet->setCellValue('D' . $row, $item['quantity_shipped']);
            $sheet->setCellValue('E' . $row, $item['container_count']);
            $sheet->setCellValue('F' . $row, $item['lot_number']);
            $row++;
        }
        
        $this->autoSizeColumns($sheet, 'A', 'F');
        
        return $this->saveSpreadsheet($spreadsheet, 'packing_list_' . $shipment['shipment_number'], $format);
    }
    
    /**
     * Create pick list export
     */
    private function createPickListExport($shipment, $items, $format) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pick List');
        
        // Header
        $sheet->setCellValue('A1', 'PICK LIST');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->mergeCells('A1:E1');
        
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Shipment Number:')->setCellValue('B' . $row, $shipment['shipment_number']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Ship Date:')->setCellValue('B' . $row, $shipment['ship_date']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Customer:')->setCellValue('B' . $row, $shipment['customer_name']);
        $row += 2;
        
        // Pick list headers
        $headers = ['Part Number', 'Description', 'Quantity to Pick', 'Location', 'Notes'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $col++;
        }
        
        $this->applyHeaderStyle($sheet, 'A' . $row . ':E' . $row);
        $row++;
        
        // Items
        foreach ($items as $item) {
            $sheet->setCellValue('A' . $row, $item['supplier_item']);
            $sheet->setCellValue('B' . $row, $item['full_description'] ?: $item['item_description']);
            $sheet->setCellValue('C' . $row, $item['quantity_shipped']);
            $sheet->setCellValue('D' . $row, ''); // Warehouse location - to be filled manually
            $sheet->setCellValue('E' . $row, $item['line_notes']);
            $row++;
        }
        
        $this->autoSizeColumns($sheet, 'A', 'E');
        
        return $this->saveSpreadsheet($spreadsheet, 'pick_list_' . $shipment['shipment_number'], $format);
    }
    
    /**
     * Create BOL export
     */
    private function createBOLExport($shipment, $items, $format) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BOL Data');
        
        // Header
        $sheet->setCellValue('A1', 'BILL OF LADING DATA');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->mergeCells('A1:F1');
        
        $row = 3;
        
        // BOL Details
        $sheet->setCellValue('A' . $row, 'BOL Number:')->setCellValue('B' . $row, $shipment['bol_number']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Shipment Number:')->setCellValue('B' . $row, $shipment['shipment_number']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Ship Date:')->setCellValue('B' . $row, $shipment['ship_date']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Carrier:')->setCellValue('B' . $row, $shipment['carrier_name']);
        $row++;
        $sheet->setCellValue('A' . $row, 'SCAC:')->setCellValue('B' . $row, $shipment['carrier_scac']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Total Weight:')->setCellValue('B' . $row, $shipment['total_weight'] . ' ' . $shipment['weight_uom']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Total Packages:')->setCellValue('B' . $row, $shipment['total_packages']);
        $row += 2;
        
        // Ship To
        $sheet->setCellValue('A' . $row, 'SHIP TO:');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue('A' . $row, $shipment['location_description']);
        $row++;
        if ($shipment['address']) {
            $sheet->setCellValue('A' . $row, $shipment['address']);
            $row++;
        }
        $sheet->setCellValue('A' . $row, $shipment['city'] . ', ' . $shipment['state']);
        $row += 2;
        
        // Items summary
        $sheet->setCellValue('A' . $row, 'ITEMS SUMMARY');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        $headers = ['Part Number', 'Description', 'Quantity', 'Containers'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $col++;
        }
        
        $this->applyHeaderStyle($sheet, 'A' . $row . ':D' . $row);
        $row++;
        
        foreach ($items as $item) {
            $sheet->setCellValue('A' . $row, $item['supplier_item']);
            $sheet->setCellValue('B' . $row, $item['full_description'] ?: $item['item_description']);
            $sheet->setCellValue('C' . $row, $item['quantity_shipped']);
            $sheet->setCellValue('D' . $row, $item['container_count']);
            $row++;
        }
        
        $this->autoSizeColumns($sheet, 'A', 'F');
        
        return $this->saveSpreadsheet($spreadsheet, 'bol_' . $shipment['shipment_number'], $format);
    }
    
    /**
     * Create delivery schedule export
     */
    private function createDeliveryScheduleExport($schedules, $format) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Delivery Schedule');
        
        // Header
        $sheet->setCellValue('A1', 'DELIVERY SCHEDULE EXPORT');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->mergeCells('A1:J1');
        
        $row = 3;
        $headers = [
            'Customer', 'PO Number', 'Release', 'Part Number', 'Description', 
            'Quantity', 'Promised Date', 'Ship To', 'Status', 'Priority'
        ];
        
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $col++;
        }
        
        $this->applyHeaderStyle($sheet, 'A' . $row . ':J' . $row);
        $row++;
        
        foreach ($schedules as $schedule) {
            $sheet->setCellValue('A' . $row, $schedule['customer_name']);
            $sheet->setCellValue('B' . $row, $schedule['po_number']);
            $sheet->setCellValue('C' . $row, $schedule['release_number']);
            $sheet->setCellValue('D' . $row, $schedule['supplier_item']);
            $sheet->setCellValue('E' . $row, $schedule['item_description']);
            $sheet->setCellValue('F' . $row, $schedule['quantity_ordered']);
            $sheet->setCellValue('G' . $row, $schedule['promised_date']);
            $sheet->setCellValue('H' . $row, $schedule['location_description']);
            $sheet->setCellValue('I' . $row, strtoupper($schedule['status']));
            $sheet->setCellValue('J' . $row, strtoupper($schedule['priority']));
            $row++;
        }
        
        $this->autoSizeColumns($sheet, 'A', 'J');
        
        return $this->saveSpreadsheet($spreadsheet, 'delivery_schedule_' . date('Y-m-d'), $format);
    }
    
    /**
     * Create inventory export
     */
    private function createInventoryExport($inventory, $format) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventory Report');
        
        // Header
        $sheet->setCellValue('A1', 'INVENTORY REPORT');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->mergeCells('A1:G1');
        
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Generated: ' . date('Y-m-d H:i:s'));
        $row += 2;
        
        $headers = [
            'Part Number', 'Description', 'Available Qty', 'Open Orders', 
            'Earliest Delivery', 'Latest Delivery', 'Customer'
        ];
        
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $col++;
        }
        
        $this->applyHeaderStyle($sheet, 'A' . $row . ':G' . $row);
        $row++;
        
        foreach ($inventory as $item) {
            $sheet->setCellValue('A' . $row, $item['part_number']);
            $sheet->setCellValue('B' . $row, $item['item_description']);
            $sheet->setCellValue('C' . $row, $item['available_quantity']);
            $sheet->setCellValue('D' . $row, $item['open_orders']);
            $sheet->setCellValue('E' . $row, $item['earliest_delivery']);
            $sheet->setCellValue('F' . $row, $item['latest_delivery']);
            $sheet->setCellValue('G' . $row, $item['customer_name']);
            $row++;
        }
        
        $this->autoSizeColumns($sheet, 'A', 'G');
        
        return $this->saveSpreadsheet($spreadsheet, 'inventory_report_' . date('Y-m-d'), $format);
    }
    
    /**
     * Export customers data
     */
    public function exportCustomers($format = 'xlsx') {
        $sql = "SELECT tp.*, 
                       COUNT(DISTINCT ds.id) as active_orders,
                       COUNT(DISTINCT sl.id) as locations_count
                FROM trading_partners tp
                LEFT JOIN delivery_schedules ds ON tp.id = ds.partner_id AND ds.status = 'active'
                LEFT JOIN ship_to_locations sl ON tp.id = sl.partner_id AND sl.active = 1
                GROUP BY tp.id
                ORDER BY tp.name";
        
        $stmt = $this->db->query($sql);
        $customers = $stmt->fetchAll();
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Customers');
        
        // Header
        $sheet->setCellValue('A1', 'CUSTOMER EXPORT');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->mergeCells('A1:H1');
        
        $row = 3;
        $headers = [
            'Partner Code', 'Company Name', 'EDI ID', 'Connection Type', 
            'Status', 'Active Orders', 'Locations', 'Contact Email'
        ];
        
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $col++;
        }
        
        $this->applyHeaderStyle($sheet, 'A' . $row . ':H' . $row);
        $row++;
        
        foreach ($customers as $customer) {
            $sheet->setCellValue('A' . $row, $customer['partner_code'] ?: '');
            $sheet->setCellValue('B' . $row, $customer['name'] ?: '');
            $sheet->setCellValue('C' . $row, $customer['edi_id'] ?: '');
            $sheet->setCellValue('D' . $row, $customer['connection_type'] ?: '');
            $sheet->setCellValue('E' . $row, strtoupper($customer['status'] ?: ''));
            $sheet->setCellValue('F' . $row, $customer['active_orders'] ?: 0);
            $sheet->setCellValue('G' . $row, $customer['locations_count'] ?: 0);
            $sheet->setCellValue('H' . $row, $customer['contact_email'] ?: '');
            $row++;
        }
        
        $this->autoSizeColumns($sheet, 'A', 'H');
        
        return $this->saveSpreadsheet($spreadsheet, 'customers_export_' . date('Y-m-d'), $format);
    }
    
    /**
     * Apply header styling
     */
    private function applyHeaderStyle($sheet, $range) {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE6E6FA']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ]
        ]);
    }
    
    /**
     * Auto-size columns
     */
    private function autoSizeColumns($sheet, $startCol, $endCol) {
        $col = $startCol;
        while ($col <= $endCol) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
    }
    
    /**
     * Save spreadsheet in specified format
     */
    private function saveSpreadsheet($spreadsheet, $filename, $format) {
        $uploadsDir = '/var/www/html/edimodule/data/exports/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $filename = $filename . '_' . $timestamp;
        
        // Set basic metadata
        $spreadsheet->getProperties()
            ->setCreator("EDI Module")
            ->setTitle("Customer Export");
        
        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $filepath = $uploadsDir . $filename . '.csv';
        } elseif ($format === 'xls') {
            // Use older XLS format for better compatibility
            $writer = new Xls($spreadsheet);
            $filepath = $uploadsDir . $filename . '.xls';
        } else {
            // Use standard XLSX format
            $writer = new Xlsx($spreadsheet);
            $filepath = $uploadsDir . $filename . '.xlsx';
        }
        
        $writer->save($filepath);
        
        return [
            'success' => true,
            'filename' => basename($filepath),
            'filepath' => $filepath,
            'download_url' => 'download.php?file=' . basename($filepath)
        ];
    }
    
    /**
     * Create simple CSV export for basic data
     */
    public function exportToCSV($data, $headers, $filename) {
        $uploadsDir = '/var/www/html/edimodule/data/exports/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $filepath = $uploadsDir . $filename . '_' . $timestamp . '.csv';
        
        $file = fopen($filepath, 'w');
        
        // Write headers
        fputcsv($file, $headers);
        
        // Write data
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        return [
            'success' => true,
            'filename' => basename($filepath),
            'filepath' => $filepath,
            'download_url' => 'download.php?file=' . basename($filepath)
        ];
    }
}

?>