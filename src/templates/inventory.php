<?php
require_once '../classes/ExcelExportService.php';

$exportService = new ExcelExportService();

// Handle export requests
if (isset($_GET['export'])) {
    try {
        $format = $_GET['format'] ?? 'xlsx';
        $dateRange = null;
        
        if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            $dateRange = [
                'start' => $_GET['start_date'],
                'end' => $_GET['end_date']
            ];
        }
        
        $location = $_GET['location'] ?? null;
        $result = $exportService->exportInventoryReport($dateRange, $location, $format);
        
        if ($result['success']) {
            header('Location: ../../' . $result['download_url']);
            exit;
        }
    } catch (Exception $e) {
        $error = "Export failed: " . $e->getMessage();
    }
}

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
$selectedCustomer = $_GET['customer_id'] ?? '';

// Get inventory data (using delivery schedules as proxy)
$sql = "SELECT 
            ds.supplier_item as part_number,
            ds.item_description,
            SUM(ds.quantity_ordered) as total_ordered,
            SUM(ds.quantity_shipped) as total_shipped,
            SUM(ds.quantity_ordered - ds.quantity_shipped) as available_quantity,
            COUNT(DISTINCT ds.po_number) as open_orders,
            MIN(ds.promised_date) as earliest_delivery,
            MAX(ds.promised_date) as latest_delivery,
            tp.name as customer_name,
            tp.id as customer_id,
            AVG(ds.unit_price) as avg_unit_price
        FROM delivery_schedules ds
        JOIN trading_partners tp ON ds.partner_id = tp.id
        WHERE ds.status = 'active' 
        AND ds.promised_date BETWEEN ? AND ?";

$params = [$startDate, $endDate];

if ($selectedCustomer) {
    $sql .= " AND tp.id = ?";
    $params[] = $selectedCustomer;
}

$sql .= " GROUP BY ds.supplier_item, ds.item_description, tp.name, tp.id
          HAVING available_quantity > 0
          ORDER BY ds.supplier_item, tp.name";

$stmt = $db->getConnection()->prepare($sql);
$stmt->execute($params);
$inventory = $stmt->fetchAll();

// Get customers for filter
$customersStmt = $db->getConnection()->query("SELECT id, name FROM trading_partners ORDER BY name");
$customers = $customersStmt->fetchAll();

// Calculate summary stats
$totalParts = count($inventory);
$totalAvailable = array_sum(array_column($inventory, 'available_quantity'));
$totalOrders = array_sum(array_column($inventory, 'open_orders'));
$lowStockParts = count(array_filter($inventory, fn($item) => $item['available_quantity'] < 100));
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="bi bi-boxes me-2"></i>Inventory Management</h2>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                    <i class="bi bi-funnel me-1"></i>Filters
                </button>
                <div class="btn-group">
                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-download me-1"></i>Export to Excel
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?page=inventory&export=1&format=xlsx&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&customer_id=<?= $selectedCustomer ?>">
                            <i class="bi bi-file-excel me-1"></i>Excel (.xlsx)</a></li>
                        <li><a class="dropdown-item" href="?page=inventory&export=1&format=csv&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&customer_id=<?= $selectedCustomer ?>">
                            <i class="bi bi-file-text me-1"></i>CSV</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="?page=inventory&export=1&format=xlsx&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&customer_id=<?= $selectedCustomer ?>&low_stock=1">
                            <i class="bi bi-exclamation-triangle me-1"></i>Low Stock Only</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Active Filters -->
<?php if ($selectedCustomer || $startDate !== date('Y-m-d') || $endDate !== date('Y-m-d', strtotime('+30 days'))): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="alert alert-info d-flex align-items-center">
            <i class="bi bi-info-circle me-2"></i>
            <div class="flex-grow-1">
                <strong>Active Filters:</strong>
                <?php if ($selectedCustomer): ?>
                    <span class="badge bg-primary ms-1">Customer: <?= htmlspecialchars(array_column($customers, 'name', 'id')[$selectedCustomer] ?? 'Unknown') ?></span>
                <?php endif; ?>
                <span class="badge bg-secondary ms-1">Date Range: <?= $startDate ?> to <?= $endDate ?></span>
            </div>
            <a href="?page=inventory" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Inventory Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-box fs-1 text-primary me-3"></i>
                    <div>
                        <h3 class="mb-0"><?= number_format($totalParts) ?></h3>
                        <small class="text-muted">Active Parts</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card success">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-check2-all fs-1 text-success me-3"></i>
                    <div>
                        <h3 class="mb-0"><?= number_format($totalAvailable) ?></h3>
                        <small class="text-muted">Available Quantity</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card warning">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-cart3 fs-1 text-warning me-3"></i>
                    <div>
                        <h3 class="mb-0"><?= number_format($totalOrders) ?></h3>
                        <small class="text-muted">Open Orders</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card danger">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle fs-1 text-danger me-3"></i>
                    <div>
                        <h3 class="mb-0"><?= number_format($lowStockParts) ?></h3>
                        <small class="text-muted">Low Stock Parts</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inventory Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Inventory Report - <?= date('M j, Y', strtotime($startDate)) ?> to <?= date('M j, Y', strtotime($endDate)) ?></h5>
        <small class="text-muted"><?= count($inventory) ?> parts found</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="inventoryTable">
                <thead class="table-dark">
                    <tr>
                        <th>Part Number</th>
                        <th>Description</th>
                        <th>Customer</th>
                        <th>Available Qty</th>
                        <th>Total Ordered</th>
                        <th>Total Shipped</th>
                        <th>Open Orders</th>
                        <th>Earliest Delivery</th>
                        <th>Latest Delivery</th>
                        <th>Avg Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                    <tr <?= $item['available_quantity'] < 100 ? 'class="table-warning"' : '' ?>>
                        <td><strong><?= htmlspecialchars($item['part_number']) ?></strong></td>
                        <td><?= htmlspecialchars($item['item_description']) ?></td>
                        <td><small><?= htmlspecialchars($item['customer_name']) ?></small></td>
                        <td>
                            <span class="badge <?= $item['available_quantity'] < 100 ? 'bg-warning' : 'bg-success' ?>">
                                <?= number_format($item['available_quantity']) ?>
                            </span>
                        </td>
                        <td><?= number_format($item['total_ordered']) ?></td>
                        <td><?= number_format($item['total_shipped']) ?></td>
                        <td>
                            <span class="badge bg-primary"><?= $item['open_orders'] ?></span>
                        </td>
                        <td><small><?= date('M j', strtotime($item['earliest_delivery'])) ?></small></td>
                        <td><small><?= date('M j', strtotime($item['latest_delivery'])) ?></small></td>
                        <td>
                            <?php if ($item['avg_unit_price']): ?>
                                <small>$<?= number_format($item['avg_unit_price'], 2) ?></small>
                            <?php else: ?>
                                <small class="text-muted">N/A</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" title="View Part Details" 
                                        onclick="viewPartDetails('<?= htmlspecialchars($item['part_number']) ?>')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-info" title="Create Shipment" 
                                        onclick="createShipment('<?= htmlspecialchars($item['part_number']) ?>', <?= $item['customer_id'] ?>)">
                                    <i class="bi bi-truck"></i>
                                </button>
                                <div class="btn-group">
                                    <button class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" title="Export Options">
                                        <i class="bi bi-download"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="?page=schedules&supplier_item=<?= urlencode($item['part_number']) ?>">
                                            <i class="bi bi-calendar me-1"></i>View Schedules</a></li>
                                        <li><a class="dropdown-item" href="?page=inventory&export=1&part=<?= urlencode($item['part_number']) ?>&format=xlsx">
                                            <i class="bi bi-file-excel me-1"></i>Export Part Data</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($inventory)): ?>
        <div class="text-center py-5">
            <i class="bi bi-box-seam fs-1 text-muted"></i>
            <h5 class="mt-3 text-muted">No inventory data found</h5>
            <p class="text-muted">Try adjusting your date range or customer filter, or check if delivery schedules are loaded.</p>
            <div class="mt-3">
                <a href="?page=schedules" class="btn btn-primary me-2">
                    <i class="bi bi-calendar me-1"></i>View Delivery Schedules
                </a>
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#filterModal">
                    <i class="bi bi-funnel me-1"></i>Adjust Filters
                </button>
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
                <input type="hidden" name="page" value="inventory">
                <div class="modal-header">
                    <h5 class="modal-title">Filter Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="customer_id" class="form-label">Customer</label>
                        <select class="form-select" id="customer_id" name="customer_id">
                            <option value="">All Customers</option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>" <?= $selectedCustomer == $customer['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($customer['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $startDate ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $endDate ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="low_stock_only" name="low_stock_only" value="1">
                            <label class="form-check-label" for="low_stock_only">
                                Show only low stock parts (< 100 units)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewPartDetails(partNumber) {
    alert('Part details view for: ' + partNumber + '\n(Feature to be implemented)');
}

function createShipment(partNumber, customerId) {
    if (confirm('Create new shipment for part ' + partNumber + '?')) {
        window.location.href = '?page=build_shipment&part=' + encodeURIComponent(partNumber) + '&customer=' + customerId;
    }
}

// Initialize table sorting if DataTables is available
document.addEventListener('DOMContentLoaded', function() {
    // Simple table search functionality
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search parts...';
    searchInput.className = 'form-control mb-3';
    
    const table = document.getElementById('inventoryTable');
    if (table) {
        table.parentNode.insertBefore(searchInput, table);
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
});
</script>