<?php
require_once '../classes/ExcelExportService.php';

$exportService = new ExcelExportService();

// Handle export requests
if (isset($_GET['export']) && isset($_GET['shipment_id'])) {
    try {
        $shipmentId = $_GET['shipment_id'];
        $templateType = $_GET['template'] ?? 'packing_list';
        $format = $_GET['format'] ?? 'xlsx';
        
        $result = $exportService->exportShipmentForExcel($shipmentId, $templateType, $format);
        
        if ($result['success']) {
            header('Location: ' . $result['download_url']);
            exit;
        }
    } catch (Exception $e) {
        $error = "Export failed: " . $e->getMessage();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_shipment':
                    $db->getConnection()->beginTransaction();
                    
                    // Create shipment
                    $sql = "INSERT INTO shipments (shipment_number, partner_id, po_number, ship_to_location_id, 
                                                  ship_date, carrier_scac, carrier_name, bol_number, 
                                                  total_weight, total_packages, status, created_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'planned', 'Web User')";
                    
                    $stmt = $db->getConnection()->prepare($sql);
                    $stmt->execute([
                        $_POST['shipment_number'],
                        $_POST['partner_id'],
                        $_POST['po_number'],
                        !empty($_POST['ship_to_location_id']) ? $_POST['ship_to_location_id'] : null,
                        $_POST['ship_date'],
                        $_POST['carrier_scac'],
                        $_POST['carrier_name'],
                        $_POST['bol_number'],
                        $_POST['total_weight'] ?: 0,
                        $_POST['total_packages'] ?: 1
                    ]);
                    
                    $shipmentId = $db->getConnection()->lastInsertId();
                    
                    // Add selected items to shipment
                    if (!empty($_POST['selected_items'])) {
                        $itemSql = "INSERT INTO shipment_items (shipment_id, delivery_schedule_id, supplier_item, 
                                                               customer_item, item_description, po_line, quantity_shipped, 
                                                               container_count, lot_number) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $itemStmt = $db->getConnection()->prepare($itemSql);
                        
                        foreach ($_POST['selected_items'] as $itemId) {
                            // Get item details from delivery schedule
                            $itemQuery = "SELECT * FROM delivery_schedules WHERE id = ?";
                            $itemResult = $db->getConnection()->prepare($itemQuery);
                            $itemResult->execute([$itemId]);
                            $item = $itemResult->fetch();
                            
                            if ($item) {
                                $qtyShipped = $_POST['qty_' . $itemId] ?? $item['quantity_ordered'];
                                $containers = $_POST['containers_' . $itemId] ?? 1;
                                $lotNumber = $_POST['lot_' . $itemId] ?? '';
                                
                                $itemStmt->execute([
                                    $shipmentId,
                                    $itemId,
                                    $item['supplier_item'],
                                    $item['customer_item'],
                                    $item['item_description'],
                                    $item['po_line'],
                                    $qtyShipped,
                                    $containers,
                                    $lotNumber
                                ]);
                                
                                // Update delivery schedule shipped quantity
                                $updateSql = "UPDATE delivery_schedules SET quantity_shipped = quantity_shipped + ? WHERE id = ?";
                                $updateStmt = $db->getConnection()->prepare($updateSql);
                                $updateStmt->execute([$qtyShipped, $itemId]);
                            }
                        }
                    }
                    
                    $db->getConnection()->commit();
                    $success = "Shipment created successfully! ID: " . $shipmentId;
                    $_GET['created_shipment'] = $shipmentId;
                    
                    break;
            }
        }
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $error = "Failed to create shipment: " . $e->getMessage();
    }
}

// Get filter parameters
$customerId = $_GET['customer_id'] ?? '';
$poNumber = $_GET['po_number'] ?? '';
$partNumber = $_GET['part_number'] ?? '';
$locationId = $_GET['location_id'] ?? '';
$dateFilterMode = $_GET['date_filter_mode'] ?? 'range'; // 'exact' or 'range'
$exactDate = $_GET['exact_date'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Get customers
$customersStmt = $db->getConnection()->query("SELECT id, name, partner_code FROM trading_partners ORDER BY name");
$customers = $customersStmt->fetchAll();

// Get available delivery schedules for shipment building
$sql = "SELECT ds.*, tp.name as customer_name, tp.partner_code,
               sl.location_description, sl.location_code,
               (ds.quantity_ordered - ds.quantity_shipped) as available_qty
        FROM delivery_schedules ds
        JOIN trading_partners tp ON ds.partner_id = tp.id
        LEFT JOIN ship_to_locations sl ON ds.ship_to_location_id = sl.id
        WHERE ds.status = 'active' 
        AND (ds.quantity_ordered - ds.quantity_shipped) > 0";

$params = [];

if ($customerId) {
    $sql .= " AND ds.partner_id = ?";
    $params[] = $customerId;
}

if ($poNumber) {
    $sql .= " AND ds.po_number LIKE ?";
    $params[] = '%' . $poNumber . '%';
}

if ($partNumber) {
    $sql .= " AND ds.supplier_item LIKE ?";
    $params[] = '%' . $partNumber . '%';
}

if ($locationId) {
    $sql .= " AND ds.ship_to_location_id = ?";
    $params[] = $locationId;
}

// Handle date filtering based on mode
if ($dateFilterMode === 'exact' && $exactDate) {
    $sql .= " AND DATE(ds.promised_date) = ?";
    $params[] = $exactDate;
} elseif ($dateFilterMode === 'range') {
    if ($dateFrom) {
        $sql .= " AND ds.promised_date >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $sql .= " AND ds.promised_date <= ?";
        $params[] = $dateTo;
    }
}

$sql .= " ORDER BY ds.promised_date, ds.supplier_item";

$stmt = $db->getConnection()->prepare($sql);
$stmt->execute($params);
$availableItems = $stmt->fetchAll();

// Get recent shipments
$recentShipmentsStmt = $db->getConnection()->query("
    SELECT s.*, tp.name as customer_name, 
           COUNT(si.id) as item_count
    FROM shipments s
    JOIN trading_partners tp ON s.partner_id = tp.id
    LEFT JOIN shipment_items si ON s.id = si.shipment_id
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT 10
");
$recentShipments = $recentShipmentsStmt->fetchAll();

// Get ship-to locations for all customers (for filter dropdown)
$allLocationsStmt = $db->getConnection()->query(
    "SELECT sl.*, tp.name as customer_name 
     FROM ship_to_locations sl 
     JOIN trading_partners tp ON sl.partner_id = tp.id 
     WHERE sl.active = 1 
     ORDER BY tp.name, sl.location_description"
);
$allLocations = $allLocationsStmt->fetchAll();

// Get ship-to locations for selected customer (for shipment creation)
$shipToLocations = [];
if ($customerId) {
    $locStmt = $db->getConnection()->prepare("SELECT * FROM ship_to_locations WHERE partner_id = ? AND active = 1");
    $locStmt->execute([$customerId]);
    $shipToLocations = $locStmt->fetchAll();
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="bi bi-truck me-2"></i>Shipment Builder</h2>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                    <i class="bi bi-funnel me-1"></i>Filter Items
                </button>
                <button type="button" class="btn btn-success" onclick="showCreateShipmentModal()" 
                        <?= empty($availableItems) ? 'disabled' : '' ?>>
                    <i class="bi bi-plus-circle me-1"></i>Create Shipment
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Active Filters -->
<?php if ($customerId || $poNumber || $partNumber || $locationId || $exactDate || $dateFrom || $dateTo): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="alert alert-info d-flex align-items-center">
            <i class="bi bi-info-circle me-2"></i>
            <div class="flex-grow-1">
                <strong>Active Filters:</strong>
                <?php if ($customerId): ?>
                    <span class="badge bg-primary ms-1">Customer: <?= htmlspecialchars(array_column($customers, 'name', 'id')[$customerId] ?? 'Unknown') ?></span>
                <?php endif; ?>
                <?php if ($poNumber): ?>
                    <span class="badge bg-secondary ms-1">PO: <?= htmlspecialchars($poNumber) ?></span>
                <?php endif; ?>
                <?php if ($partNumber): ?>
                    <span class="badge bg-secondary ms-1">Part: <?= htmlspecialchars($partNumber) ?></span>
                <?php endif; ?>
                <?php if ($locationId): ?>
                    <span class="badge bg-info ms-1">Location: <?= htmlspecialchars(array_column($allLocations, 'location_description', 'id')[$locationId] ?? 'Unknown') ?></span>
                <?php endif; ?>
                <?php if ($dateFilterMode === 'exact' && $exactDate): ?>
                    <span class="badge bg-warning ms-1">Exact Date: <?= htmlspecialchars($exactDate) ?></span>
                <?php elseif ($dateFilterMode === 'range'): ?>
                    <?php if ($dateFrom): ?>
                        <span class="badge bg-warning ms-1">From: <?= htmlspecialchars($dateFrom) ?></span>
                    <?php endif; ?>
                    <?php if ($dateTo): ?>
                        <span class="badge bg-warning ms-1">To: <?= htmlspecialchars($dateTo) ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <a href="?page=build_shipment" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- Available Items for Shipment -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Available Items for Shipment</h5>
                <small class="text-muted"><?= count($availableItems) ?> items available</small>
            </div>
            <div class="card-body">
                <?php if (!empty($availableItems)): ?>
                <form id="shipmentForm">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                            <label class="form-check-label" for="selectAll">
                                <strong>Select All Filtered Results</strong> <span class="text-muted">(<?= count($availableItems) ?> items)</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th width="50">Select</th>
                                    <th>Customer</th>
                                    <th>PO Number</th>
                                    <th>Part Number</th>
                                    <th>Description</th>
                                    <th>Available</th>
                                    <th>Ship To</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($availableItems as $item): ?>
                                <tr>
                                    <td>
                                        <input class="form-check-input item-checkbox" type="checkbox" 
                                               name="selected_items[]" value="<?= $item['id'] ?>"
                                               data-customer="<?= $item['partner_id'] ?>"
                                               data-location="<?= $item['ship_to_location_id'] ?>"
                                               data-location-desc="<?= htmlspecialchars($item['location_description'] ?? '') ?>"
                                               data-po="<?= $item['po_number'] ?>">
                                    </td>
                                    <td><small><?= htmlspecialchars($item['customer_name']) ?></small></td>
                                    <td><small><?= htmlspecialchars($item['po_number']) ?></small></td>
                                    <td><strong><?= htmlspecialchars($item['supplier_item']) ?></strong></td>
                                    <td><small><?= htmlspecialchars($item['item_description']) ?></small></td>
                                    <td>
                                        <span class="badge bg-primary"><?= number_format($item['available_qty']) ?></span>
                                    </td>
                                    <td><small><?= htmlspecialchars($item['location_description']) ?></small></td>
                                    <td><small><?= date('M j', strtotime($item['promised_date'])) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <h5 class="mt-2 text-muted">No items available for shipment</h5>
                    <p class="text-muted">Try adjusting your filters or check if there are active delivery schedules.</p>
                    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="bi bi-funnel me-1"></i>Adjust Filters
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Shipments & Export Options -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Shipments</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recentShipments)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentShipments as $shipment): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start p-2">
                        <div>
                            <strong><?= htmlspecialchars($shipment['shipment_number']) ?></strong>
                            <br>
                            <small class="text-muted"><?= htmlspecialchars($shipment['customer_name']) ?></small>
                            <br>
                            <small class="text-muted"><?= $shipment['item_count'] ?> items</small>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <div class="dropdown">
                                <button class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="bi bi-download"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><h6 class="dropdown-header">Excel Exports</h6></li>
                                    <li><a class="dropdown-item" href="?page=build_shipment&export=1&shipment_id=<?= $shipment['id'] ?>&template=packing_list&format=xlsx">
                                        <i class="bi bi-file-excel me-1"></i>Packing List</a></li>
                                    <li><a class="dropdown-item" href="?page=build_shipment&export=1&shipment_id=<?= $shipment['id'] ?>&template=pick_list&format=xlsx">
                                        <i class="bi bi-list-check me-1"></i>Pick List</a></li>
                                    <li><a class="dropdown-item" href="?page=build_shipment&export=1&shipment_id=<?= $shipment['id'] ?>&template=bol&format=xlsx">
                                        <i class="bi bi-truck me-1"></i>BOL Data</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="?page=build_shipment&export=1&shipment_id=<?= $shipment['id'] ?>&template=packing_list&format=csv">
                                        <i class="bi bi-file-text me-1"></i>CSV Export</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-3">
                    <i class="bi bi-truck text-muted"></i>
                    <p class="text-muted mb-0">No recent shipments</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Export Templates -->
        <?php if (isset($_GET['created_shipment'])): ?>
        <div class="card mt-3">
            <div class="card-header bg-success text-white">
                <h6 class="card-title mb-0">
                    <i class="bi bi-check-circle me-1"></i>Shipment Created - Export Options
                </h6>
            </div>
            <div class="card-body">
                <p class="small mb-3">Your shipment has been created. Export data for your M365 templates:</p>
                
                <div class="d-grid gap-2">
                    <a href="?page=build_shipment&export=1&shipment_id=<?= $_GET['created_shipment'] ?>&template=packing_list&format=xlsx" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-excel me-1"></i>Packing List (Excel)
                    </a>
                    <a href="?page=build_shipment&export=1&shipment_id=<?= $_GET['created_shipment'] ?>&template=pick_list&format=xlsx" 
                       class="btn btn-outline-info btn-sm">
                        <i class="bi bi-list-check me-1"></i>Pick List (Excel)
                    </a>
                    <a href="?page=build_shipment&export=1&shipment_id=<?= $_GET['created_shipment'] ?>&template=bol&format=xlsx" 
                       class="btn btn-outline-success btn-sm">
                        <i class="bi bi-truck me-1"></i>BOL Data (Excel)
                    </a>
                </div>
                
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-info-circle me-1"></i>Files will download ready for your M365 templates
                </small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="GET">
                <input type="hidden" name="page" value="build_shipment">
                <div class="modal-header">
                    <h5 class="modal-title">Filter Available Items</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">Customer</label>
                                <select class="form-select" id="customer_id" name="customer_id">
                                    <option value="">All Customers</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['id'] ?>" <?= $customerId == $customer['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($customer['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="location_id" class="form-label">Ship-To Location</label>
                                <select class="form-select" id="location_id" name="location_id">
                                    <option value="">All Locations</option>
                                    <?php foreach ($allLocations as $location): ?>
                                    <option value="<?= $location['id'] ?>" <?= $locationId == $location['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($location['customer_name'] . ' - ' . $location['location_description'] . ' (' . $location['location_code'] . ')') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="po_number" class="form-label">PO Number</label>
                                <input type="text" class="form-control" id="po_number" name="po_number" 
                                       value="<?= htmlspecialchars($poNumber) ?>" placeholder="Search PO numbers...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="part_number" class="form-label">Part Number</label>
                                <input type="text" class="form-control" id="part_number" name="part_number" 
                                       value="<?= htmlspecialchars($partNumber) ?>" placeholder="Search part numbers...">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Date Filtering Section -->
                    <div class="mb-3">
                        <label class="form-label">Date Filtering</label>
                        <div class="row">
                            <div class="col-12">
                                <div class="btn-group w-100 mb-3" role="group" aria-label="Date filter mode">
                                    <input type="radio" class="btn-check" name="date_filter_mode" id="date_mode_exact" 
                                           value="exact" <?= $dateFilterMode === 'exact' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="date_mode_exact">
                                        <i class="bi bi-calendar-date me-1"></i>Exact Date
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="date_filter_mode" id="date_mode_range" 
                                           value="range" <?= $dateFilterMode === 'range' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="date_mode_range">
                                        <i class="bi bi-calendar-range me-1"></i>Date Range
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Exact Date Section -->
                        <div id="exact-date-section" class="row" style="display: <?= $dateFilterMode === 'exact' ? 'block' : 'none' ?>">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="exact_date" class="form-label">Select Date</label>
                                    <input type="date" class="form-control" id="exact_date" name="exact_date" 
                                           value="<?= htmlspecialchars($exactDate) ?>" 
                                           placeholder="Select specific date for delivery schedules">
                                    <small class="form-text text-muted">
                                        <i class="bi bi-info-circle me-1"></i>Shows delivery schedules with exact promised date
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Date Range Section -->
                        <div id="date-range-section" class="row" style="display: <?= $dateFilterMode === 'range' ? 'flex' : 'none' ?>">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_from" class="form-label">Date From</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" 
                                           value="<?= htmlspecialchars($dateFrom) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_to" class="form-label">Date To</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" 
                                           value="<?= htmlspecialchars($dateTo) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-light border">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Use filters to narrow down available items for easier shipment building. 
                            Date filters apply to the promised delivery dates.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="?page=build_shipment" class="btn btn-outline-danger me-auto">
                        <i class="bi bi-x-circle me-1"></i>Clear All Filters
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Shipment Modal -->
<div class="modal fade" id="createShipmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="createShipmentForm">
                <input type="hidden" name="action" value="create_shipment">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Shipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Shipment Number *</label>
                                <input type="text" class="form-control" name="shipment_number" 
                                       value="SH<?= date('Ymd') ?>-<?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ship Date *</label>
                                <input type="date" class="form-control" name="ship_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Customer *</label>
                                <select class="form-select" name="partner_id" id="shipment_customer" required>
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ship To Location</label>
                                <select class="form-select" name="ship_to_location_id" id="shipment_location">
                                    <option value="">Select Location</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">PO Number</label>
                        <input type="text" class="form-control" name="po_number" id="shipment_po">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Carrier Name</label>
                                <input type="text" class="form-control" name="carrier_name" placeholder="e.g., RYDER">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">SCAC Code</label>
                                <input type="text" class="form-control" name="carrier_scac" placeholder="e.g., RYDD" maxlength="4">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">BOL Number</label>
                                <input type="text" class="form-control" name="bol_number">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Total Weight</label>
                                <input type="number" class="form-control" name="total_weight" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Total Packages</label>
                                <input type="number" class="form-control" name="total_packages" value="1" min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Selected Items:</strong> <span id="selectedItemsCount">0</span> items will be added to this shipment.
                        <div id="selectedItemsList" class="mt-2 small"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Shipment & Export Options</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(checkbox => checkbox.checked = this.checked);
    updateSelectedItems();
    updateSelectAllLabel();
});

// Update selected items display
function updateSelectedItems() {
    const selected = document.querySelectorAll('.item-checkbox:checked');
    const count = selected.length;
    
    document.getElementById('selectedItemsCount').textContent = count;
    
    // Update button state
    const createButton = document.querySelector('button[onclick="showCreateShipmentModal()"]');
    if (createButton) {
        createButton.disabled = count === 0;
        if (count === 0) {
            createButton.classList.add('btn-outline-secondary');
            createButton.classList.remove('btn-success');
        } else {
            createButton.classList.remove('btn-outline-secondary');
            createButton.classList.add('btn-success');
        }
    }
    
    // Auto-populate shipment form with common data from selected items
    if (count > 0) {
        const firstItem = selected[0];
        const customerId = firstItem.dataset.customer;
        const poNumber = firstItem.dataset.po;
        
        // Analyze ship-to locations
        const locationData = analyzeSelectedLocations(selected);
        
        // Set customer
        const customerSelect = document.getElementById('shipment_customer');
        if (customerSelect && customerId) {
            customerSelect.value = customerId;
            customerSelect.dispatchEvent(new Event('change'));
        }
        
        // Set PO number
        const poInput = document.getElementById('shipment_po');
        if (poInput && poNumber) {
            poInput.value = poNumber;
        }
        
        // Handle ship-to location auto-population
        handleLocationAutoPopulation(locationData);
        
        // Update selected items list preview
        updateSelectedItemsPreview(selected);
    }
}

// Update select all label based on current state
function updateSelectAllLabel() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    const selectAllCheckbox = document.getElementById('selectAll');
    const selectAllLabel = document.querySelector('label[for="selectAll"]');
    
    if (checkedBoxes.length === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllLabel.innerHTML = `<strong>Select All Filtered Results</strong> <span class="text-muted">(${checkboxes.length} items)</span>`;
    } else if (checkedBoxes.length === checkboxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllLabel.innerHTML = `<strong>All Items Selected</strong> <span class="text-success">(${checkedBoxes.length} items)</span>`;
    } else {
        selectAllCheckbox.indeterminate = true;
        selectAllLabel.innerHTML = `<strong>Selected: ${checkedBoxes.length} of ${checkboxes.length}</strong> <span class="text-primary">(partial selection)</span>`;
    }
}

// Update selected items preview in modal
function updateSelectedItemsPreview(selected) {
    const previewContainer = document.getElementById('selectedItemsList');
    if (!previewContainer) return;
    
    if (selected.length === 0) {
        previewContainer.innerHTML = '<span class="text-muted">No items selected</span>';
        return;
    }
    
    let previewHtml = '<div class="row g-2">';
    let itemCount = 0;
    
    selected.forEach((checkbox, index) => {
        if (itemCount >= 6) return; // Show max 6 items in preview
        
        const row = checkbox.closest('tr');
        const partNumber = row.children[3].textContent.trim();
        const customer = row.children[1].textContent.trim();
        const quantity = row.children[5].textContent.trim();
        const location = row.children[6].textContent.trim();
        
        previewHtml += `
            <div class="col-md-6">
                <div class="border rounded p-2">
                    <small><strong>${partNumber}</strong></small><br>
                    <small class="text-muted">${customer} • Qty: ${quantity}</small><br>
                    <small class="text-info">📍 ${location}</small>
                </div>
            </div>
        `;
        itemCount++;
    });
    
    if (selected.length > 6) {
        previewHtml += `
            <div class="col-12">
                <div class="text-center text-muted">
                    <small>... and ${selected.length - 6} more items</small>
                </div>
            </div>
        `;
    }
    
    previewHtml += '</div>';
    previewContainer.innerHTML = previewHtml;
}

// Analyze ship-to locations from selected items
function analyzeSelectedLocations(selectedItems) {
    const locationMap = new Map();
    
    selectedItems.forEach(checkbox => {
        const locationId = checkbox.dataset.location;
        const locationDesc = checkbox.dataset.locationDesc;
        
        if (locationId && locationId !== 'null' && locationId !== '') {
            if (locationMap.has(locationId)) {
                locationMap.get(locationId).count++;
            } else {
                locationMap.set(locationId, {
                    id: locationId,
                    description: locationDesc,
                    count: 1
                });
            }
        }
    });
    
    return {
        totalItems: selectedItems.length,
        uniqueLocations: locationMap.size,
        locations: Array.from(locationMap.values()),
        hasMultipleLocations: locationMap.size > 1,
        hasSingleLocation: locationMap.size === 1,
        primaryLocation: locationMap.size === 1 ? Array.from(locationMap.values())[0] : null
    };
}

// Handle ship-to location auto-population
function handleLocationAutoPopulation(locationData) {
    const locationSelect = document.getElementById('shipment_location');
    const alertContainer = document.getElementById('selectedItemsList');
    
    if (!locationSelect) return;
    
    // Clear any existing location warnings
    const existingWarning = document.getElementById('location-warning');
    if (existingWarning) {
        existingWarning.remove();
    }
    
    if (locationData.hasSingleLocation && locationData.primaryLocation) {
        // Auto-select the single location
        setTimeout(() => {
            locationSelect.value = locationData.primaryLocation.id;
        }, 100); // Small delay to ensure options are loaded
        
        // Show success message
        showLocationMessage('success', 
            `Ship-to location auto-selected: ${locationData.primaryLocation.description}`, 
            alertContainer
        );
    } else if (locationData.hasMultipleLocations) {
        // Show warning for multiple locations
        const locationList = locationData.locations.map(loc => 
            `${loc.description} (${loc.count} items)`
        ).join(', ');
        
        showLocationMessage('warning', 
            `Selected items have different ship-to locations: ${locationList}. Please select the appropriate location or create separate shipments.`, 
            alertContainer
        );
    }
}

// Show location-related message
function showLocationMessage(type, message, container) {
    if (!container) return;
    
    const alertClass = type === 'success' ? 'alert-success' : 'alert-warning';
    const iconClass = type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle';
    
    const messageDiv = document.createElement('div');
    messageDiv.id = 'location-warning';
    messageDiv.className = `alert ${alertClass} mt-2`;
    messageDiv.innerHTML = `
        <i class="bi ${iconClass} me-1"></i>
        <small>${message}</small>
    `;
    
    container.appendChild(messageDiv);
}

// Customer change handler for locations
document.getElementById('shipment_customer').addEventListener('change', function() {
    const customerId = this.value;
    const locationSelect = document.getElementById('shipment_location');
    
    // Clear existing options
    locationSelect.innerHTML = '<option value="">Loading...</option>';
    
    // Clear any existing location warnings
    const existingWarning = document.getElementById('location-warning');
    if (existingWarning) {
        existingWarning.remove();
    }
    
    if (customerId) {
        // Fetch locations for customer
        fetch('?page=api&action=get_locations&customer_id=' + customerId)
            .then(response => response.json())
            .then(data => {
                locationSelect.innerHTML = '<option value="">Select Location</option>';
                data.forEach(location => {
                    locationSelect.innerHTML += `<option value="${location.id}">${location.location_description} (${location.location_code})</option>`;
                });
                
                // Re-trigger location auto-population after locations are loaded
                const selected = document.querySelectorAll('.item-checkbox:checked');
                if (selected.length > 0) {
                    setTimeout(() => {
                        const locationData = analyzeSelectedLocations(selected);
                        handleLocationAutoPopulation(locationData);
                    }, 100);
                }
            })
            .catch(() => {
                locationSelect.innerHTML = '<option value="">Select Location</option>';
            });
    } else {
        locationSelect.innerHTML = '<option value="">Select Location</option>';
    }
});

// Show create shipment modal
function showCreateShipmentModal() {
    const selected = document.querySelectorAll('.item-checkbox:checked');
    
    if (selected.length === 0) {
        alert('Please select at least one item to create a shipment.');
        return;
    }
    
    updateSelectedItems();
    
    // Copy selected items to the form
    const form = document.getElementById('createShipmentForm');
    
    // Remove existing hidden inputs
    form.querySelectorAll('input[name="selected_items[]"]').forEach(input => input.remove());
    
    // Add selected items as hidden inputs
    selected.forEach(checkbox => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'selected_items[]';
        hiddenInput.value = checkbox.value;
        form.appendChild(hiddenInput);
    });
    
    // Show modal and trigger location analysis after a short delay
    const modal = new bootstrap.Modal(document.getElementById('createShipmentModal'));
    modal.show();
    
    // Re-analyze locations when modal opens to ensure fresh data
    setTimeout(() => {
        const locationData = analyzeSelectedLocations(selected);
        handleLocationAutoPopulation(locationData);
    }, 300);
}

// Add event listeners to checkboxes
document.querySelectorAll('.item-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        updateSelectedItems();
        updateSelectAllLabel();
    });
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedItems();
    updateSelectAllLabel();
    
    // Add date filter mode toggle handlers
    const dateFilterRadios = document.querySelectorAll('input[name="date_filter_mode"]');
    dateFilterRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            toggleDateFilterSections(this.value);
        });
    });
    
    // Add filter shortcut buttons
    addQuickFilterButtons();
    
    // Initialize the correct date filter section visibility
    const currentMode = document.querySelector('input[name="date_filter_mode"]:checked')?.value || 'range';
    toggleDateFilterSections(currentMode);
});

// Toggle between exact date and date range sections
function toggleDateFilterSections(mode) {
    const exactSection = document.getElementById('exact-date-section');
    const rangeSection = document.getElementById('date-range-section');
    
    if (mode === 'exact') {
        exactSection.style.display = 'block';
        rangeSection.style.display = 'none';
        // Clear range values when switching to exact mode
        document.getElementById('date_from').value = '';
        document.getElementById('date_to').value = '';
    } else {
        exactSection.style.display = 'none';
        rangeSection.style.display = 'flex';
        // Clear exact date when switching to range mode
        document.getElementById('exact_date').value = '';
    }
    
    // Update quick filter buttons
    updateQuickFilterButtons(mode);
}

// Add quick filter buttons dynamically
function addQuickFilterButtons() {
    const dateFilterSection = document.querySelector('.mb-3:has([name="date_filter_mode"])');
    if (!dateFilterSection) return;
    
    const quickFilterDiv = document.createElement('div');
    quickFilterDiv.id = 'quick-filter-buttons';
    quickFilterDiv.className = 'mb-3';
    quickFilterDiv.innerHTML = `
        <label class="form-label">Quick Filters:</label><br>
        <div class="btn-group btn-group-sm" role="group" id="quick-filter-group">
            <button type="button" class="btn btn-outline-secondary" onclick="setDateFilter('today')">Today</button>
            <button type="button" class="btn btn-outline-secondary" onclick="setDateFilter('week')">This Week</button>
            <button type="button" class="btn btn-outline-secondary" onclick="setDateFilter('month')">This Month</button>
        </div>
    `;
    
    // Insert after the date filter mode buttons
    const modeButtonsDiv = dateFilterSection.querySelector('.btn-group');
    if (modeButtonsDiv && modeButtonsDiv.parentElement) {
        modeButtonsDiv.parentElement.insertAdjacentElement('afterend', quickFilterDiv);
    }
}

// Update quick filter buttons based on current mode
function updateQuickFilterButtons(mode) {
    const quickFilterGroup = document.getElementById('quick-filter-group');
    if (!quickFilterGroup) return;
    
    // Update button tooltips and behavior based on mode
    const buttons = quickFilterGroup.querySelectorAll('button');
    buttons.forEach(btn => {
        if (mode === 'exact') {
            btn.title = `Set exact date filter for ${btn.textContent.toLowerCase()}`;
        } else {
            btn.title = `Set date range filter for ${btn.textContent.toLowerCase()}`;
        }
    });
}

// Quick date filter functions
function setDateFilter(period) {
    const today = new Date();
    const currentMode = document.querySelector('input[name="date_filter_mode"]:checked')?.value || 'range';
    
    if (currentMode === 'exact') {
        // For exact date mode, set the single date based on period
        const exactDateInput = document.getElementById('exact_date');
        let targetDate;
        
        switch(period) {
            case 'today':
                targetDate = today;
                break;
            case 'week':
                // For exact mode, use start of current week
                targetDate = new Date(today);
                targetDate.setDate(today.getDate() - today.getDay());
                break;
            case 'month':
                // For exact mode, use first day of current month
                targetDate = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
        }
        
        if (targetDate) {
            exactDateInput.value = targetDate.toISOString().split('T')[0];
        }
    } else {
        // For range mode, set from/to dates
        const dateFromInput = document.getElementById('date_from');
        const dateToInput = document.getElementById('date_to');
        
        switch(period) {
            case 'today':
                const todayStr = today.toISOString().split('T')[0];
                dateFromInput.value = todayStr;
                dateToInput.value = todayStr;
                break;
            case 'week':
                const weekStart = new Date(today);
                weekStart.setDate(today.getDate() - today.getDay());
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekStart.getDate() + 6);
                
                dateFromInput.value = weekStart.toISOString().split('T')[0];
                dateToInput.value = weekEnd.toISOString().split('T')[0];
                break;
            case 'month':
                const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                
                dateFromInput.value = monthStart.toISOString().split('T')[0];
                dateToInput.value = monthEnd.toISOString().split('T')[0];
                break;
        }
    }
}
</script>