<?php
require_once '../classes/ExcelExportService.php';

$exportService = new ExcelExportService();

// Handle export requests
if (isset($_GET['export'])) {
    try {
        $format = $_GET['format'] ?? 'xlsx';
        $customerId = $_GET['partner'] ?? null;
        $dateRange = null;
        
        if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            $dateRange = [
                'start' => $_GET['start_date'],
                'end' => $_GET['end_date']
            ];
        }
        
        $result = $exportService->exportDeliverySchedule($customerId, $dateRange, $format);
        
        if ($result['success']) {
            header('Location: ../../' . $result['download_url']);
            exit;
        }
    } catch (Exception $e) {
        $error = "Export failed: " . $e->getMessage();
    }
}

$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$partner = $_GET['partner'] ?? '';

try {
    $conn = $db->getConnection();
    
    $whereConditions = ["1=1"];
    $params = [];
    
    if ($search) {
        $whereConditions[] = "(ds.po_number LIKE ? OR ds.supplier_item LIKE ? OR ds.item_description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($status) {
        $whereConditions[] = "ds.status = ?";
        $params[] = $status;
    }
    
    if ($partner) {
        $whereConditions[] = "ds.partner_id = ?";
        $params[] = $partner;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $countSql = "SELECT COUNT(*) as total FROM delivery_schedules ds WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    $sql = "
        SELECT ds.*, tp.name as partner_name, sl.location_description, sl.location_code
        FROM delivery_schedules ds
        LEFT JOIN trading_partners tp ON ds.partner_id = tp.id
        LEFT JOIN ship_to_locations sl ON ds.ship_to_location_id = sl.id
        WHERE $whereClause
        ORDER BY ds.promised_date ASC, ds.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll();
    
    $partners = $conn->query("SELECT id, name FROM trading_partners WHERE status = 'active' ORDER BY name")->fetchAll();
    
} catch (Exception $e) {
    AppConfig::logError("Schedules page error: " . $e->getMessage());
    $error = "Database error occurred: " . $e->getMessage();
    $schedules = [];
    $partners = [];
    $totalRecords = 0;
    $totalPages = 0;
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-calendar me-2"></i>Delivery Schedules</h2>
        <p class="text-muted">Manage and monitor delivery schedules</p>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="schedules">
            
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?= htmlspecialchars($search) ?>" 
                       placeholder="PO number, item number, description...">
            </div>
            
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="shipped" <?= $status == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="received" <?= $status == 'received' ? 'selected' : '' ?>>Received</option>
                    <option value="cancelled" <?= $status == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="closed" <?= $status == 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="partner" class="form-label">Trading Partner</label>
                <select class="form-select" id="partner" name="partner">
                    <option value="">All Partners</option>
                    <?php foreach ($partners as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $partner == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-list-ul me-2"></i>
            Schedules (<?= number_format($totalRecords) ?> records)
        </h5>
        <div class="btn-group btn-group-sm">
            <div class="btn-group">
                <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download me-1"></i>Export to Excel
                </button>
                <ul class="dropdown-menu">
                    <li><h6 class="dropdown-header">Current View</h6></li>
                    <li><a class="dropdown-item" href="?page=schedules&export=1&format=xlsx&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&partner=<?= urlencode($partner) ?>">
                        <i class="bi bi-file-excel me-1"></i>Excel (.xlsx)</a></li>
                    <li><a class="dropdown-item" href="?page=schedules&export=1&format=csv&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&partner=<?= urlencode($partner) ?>">
                        <i class="bi bi-file-text me-1"></i>CSV</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">All Active Schedules</h6></li>
                    <li><a class="dropdown-item" href="?page=schedules&export=1&format=xlsx">
                        <i class="bi bi-file-excel me-1"></i>All Active (Excel)</a></li>
                    <li><a class="dropdown-item" href="?page=schedules&export=1&format=csv">
                        <i class="bi bi-file-text me-1"></i>All Active (CSV)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#customExportModal">
                        <i class="bi bi-gear me-1"></i>Custom Export Options</a></li>
                </ul>
            </div>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Print
            </button>
            <a href="?page=import" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Import More
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($schedules)): ?>
            <div class="text-center p-5">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No schedules found</h4>
                <p class="text-muted">Try adjusting your search criteria or <a href="?page=import">import some data</a>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>PO Line</th>
                            <th>Supplier Item</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Promised Date</th>
                            <th>Ship To</th>
                            <th>Partner</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                        <?php 
                        $isOverdue = $schedule['status'] == 'active' && strtotime($schedule['promised_date']) < strtotime('today');
                        $isDueToday = $schedule['status'] == 'active' && strtotime($schedule['promised_date']) == strtotime('today');
                        $rowClass = $isOverdue ? 'table-danger' : ($isDueToday ? 'table-warning' : '');
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td>
                                <code><?= htmlspecialchars($schedule['po_line']) ?></code>
                                <?php if ($isOverdue): ?>
                                    <i class="bi bi-exclamation-triangle text-danger ms-1" title="Overdue"></i>
                                <?php elseif ($isDueToday): ?>
                                    <i class="bi bi-clock text-warning ms-1" title="Due today"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($schedule['supplier_item']) ?></div>
                                <?php if ($schedule['customer_item'] && $schedule['customer_item'] != $schedule['supplier_item']): ?>
                                    <small class="text-muted">Customer: <?= htmlspecialchars($schedule['customer_item']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span title="<?= htmlspecialchars($schedule['item_description']) ?>">
                                    <?= htmlspecialchars(substr($schedule['item_description'], 0, 40)) ?>
                                    <?= strlen($schedule['item_description']) > 40 ? '...' : '' ?>
                                </span>
                            </td>
                            <td>
                                <div><?= number_format($schedule['quantity_ordered']) ?> <?= $schedule['uom'] ?></div>
                                <?php if ($schedule['quantity_received'] > 0 || $schedule['quantity_shipped'] > 0): ?>
                                    <small class="text-muted">
                                        <?php if ($schedule['quantity_shipped'] > 0): ?>
                                            Shipped: <?= number_format($schedule['quantity_shipped']) ?>
                                        <?php endif; ?>
                                        <?php if ($schedule['quantity_received'] > 0): ?>
                                            Received: <?= number_format($schedule['quantity_received']) ?>
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($schedule['promised_date'])) ?></td>
                            <td>
                                <?= htmlspecialchars($schedule['location_description'] ?? $schedule['ship_to_description']) ?>
                                <?php if ($schedule['location_code']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($schedule['location_code']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($schedule['partner_name']) ?></td>
                            <td>
                                <span class="badge bg-<?php
                                    switch($schedule['status']) {
                                        case 'active': echo 'success'; break;
                                        case 'shipped': echo 'info'; break;
                                        case 'received': echo 'primary'; break;
                                        case 'cancelled': echo 'danger'; break;
                                        case 'closed': echo 'secondary'; break;
                                        default: echo 'secondary';
                                    }
                                ?>">
                                    <?= ucfirst($schedule['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                            data-bs-toggle="tooltip" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if ($schedule['status'] == 'active'): ?>
                                        <button type="button" class="btn btn-outline-success btn-sm"
                                                data-bs-toggle="tooltip" title="Mark as Shipped">
                                            <i class="bi bi-truck"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Schedules pagination">
                    <ul class="pagination mb-0 justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=schedules&p=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&partner=<?= urlencode($partner) ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=schedules&p=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&partner=<?= urlencode($partner) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=schedules&p=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&partner=<?= urlencode($partner) ?>">
                                    Next
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Custom Export Modal -->
<div class="modal fade" id="customExportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="GET">
                <input type="hidden" name="page" value="schedules">
                <input type="hidden" name="export" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Custom Export Options</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" value="xlsx" id="format_xlsx" checked>
                            <label class="form-check-label" for="format_xlsx">
                                <i class="bi bi-file-excel me-1 text-success"></i>Excel (.xlsx) - Recommended for M365 templates
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" value="csv" id="format_csv">
                            <label class="form-check-label" for="format_csv">
                                <i class="bi bi-file-text me-1 text-primary"></i>CSV - Universal format
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_partner" class="form-label">Customer</label>
                        <select class="form-select" id="export_partner" name="partner">
                            <option value="">All Customers</option>
                            <?php foreach ($partners as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_status" class="form-label">Status Filter</label>
                        <select class="form-select" id="export_status" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" selected>Active Only</option>
                            <option value="shipped">Shipped</option>
                            <option value="received">Received</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Excel Export Features:</strong>
                        <ul class="mb-0 mt-2 small">
                            <li>Formatted headers and styling</li>
                            <li>Auto-sized columns for readability</li>
                            <li>Ready for M365 template integration</li>
                            <li>Includes customer and location details</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-download me-1"></i>Export to Excel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
})

// Update export button text based on format selection
document.querySelectorAll('input[name="format"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const button = document.querySelector('#customExportModal .btn-success');
        if (this.value === 'csv') {
            button.innerHTML = '<i class="bi bi-download me-1"></i>Export to CSV';
        } else {
            button.innerHTML = '<i class="bi bi-download me-1"></i>Export to Excel';
        }
    });
});
</script>