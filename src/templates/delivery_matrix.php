<?php
require_once '../classes/DeliveryMatrix.php';
require_once '../classes/PartMaster.php';

use Greenfield\EDI\DeliveryMatrix;
use Greenfield\EDI\PartMaster;

$deliveryMatrix = new DeliveryMatrix($db);
$partMaster = new PartMaster($db);

// Export handling is now done by dedicated export_delivery_matrix.php handler

// Get filter parameters
$filters = [
    'location_code' => $_GET['location_code'] ?? '',
    'part_number' => $_GET['part_number'] ?? '',
    'po_number' => $_GET['po_number'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'status' => $_GET['status'] ?? 'active',
    'product_family' => $_GET['product_family'] ?? ''
];

$data = $deliveryMatrix->getDeliveryMatrix($filters);
$locations = $deliveryMatrix->getLocationCodes();
$productFamilies = $deliveryMatrix->getProductFamilies();

// Pagination
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$paginatedData = array_slice($data, $offset, $limit);
$totalPages = ceil(count($data) / $limit);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-table me-2"></i>Delivery Schedule Matrix</h2>
    <div>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">
            <i class="bi bi-download me-1"></i>Export to Excel
        </button>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filter Section -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filters & Search</h5>
    </div>
    <div class="card-body">
        <form method="get">
            <input type="hidden" name="page" value="delivery_matrix">
            
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Location</label>
                    <select name="location_code" class="form-select">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= htmlspecialchars($location['location_code']) ?>" 
                                    <?= $filters['location_code'] === $location['location_code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($location['location_code'] . ' - ' . $location['location_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Part Number</label>
                    <input type="text" name="part_number" class="form-control" 
                           value="<?= htmlspecialchars($filters['part_number']) ?>" 
                           placeholder="Search part...">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">PO Number</label>
                    <input type="text" name="po_number" class="form-control" 
                           value="<?= htmlspecialchars($filters['po_number']) ?>" 
                           placeholder="Search PO...">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="shipped" <?= $filters['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="received" <?= $filters['status'] === 'received' ? 'selected' : '' ?>>Received</option>
                        <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" 
                           value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" 
                           value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Product Family</label>
                    <select name="product_family" class="form-select">
                        <option value="">All Families</option>
                        <?php foreach ($productFamilies as $family): ?>
                            <option value="<?= htmlspecialchars($family) ?>" 
                                    <?= $filters['product_family'] === $family ? 'selected' : '' ?>>
                                <?= htmlspecialchars($family) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="?page=delivery_matrix" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- Results Info -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="text-muted">
            Showing <?= number_format(count($paginatedData)) ?> of <?= number_format(count($data)) ?> records
            <?php if ($filters['location_code'] || $filters['part_number'] || $filters['po_number'] || $filters['status'] !== 'active'): ?>
                (filtered)
            <?php endif; ?>
        </span>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=delivery_matrix&p=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>PO Number</th>
                        <th>Part Number</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>QPC</th>
                        <th>Containers</th>
                        <th>Promised Date</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginatedData as $item): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['formatted_po'] ?? $item['po_number']) ?></strong>
                            <?php if ($item['release_number']): ?>
                                <br><small class="text-muted">Rel: <?= htmlspecialchars($item['release_number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($item['supplier_item']) ?>
                            <?php if ($item['product_family']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($item['product_family']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($item['part_description'] ?? $item['item_description']) ?>
                            <?php if ($item['weight']): ?>
                                <br><small class="text-muted">Weight: <?= $item['weight'] ?> lbs</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <strong><?= number_format($item['quantity_ordered']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($item['uom']) ?></small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info"><?= $item['qpc'] ?? 1 ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success">
                                <?= $item['containers_needed'] ?? round($item['quantity_ordered'] / ($item['qpc'] ?? 1)) ?>
                            </span>
                        </td>
                        <td>
                            <?= date('M j, Y', strtotime($item['promised_date'])) ?>
                            <?php if ($item['need_by_date'] && $item['need_by_date'] !== $item['promised_date']): ?>
                                <br><small class="text-muted">Need: <?= date('M j', strtotime($item['need_by_date'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $item['location_code'] === 'SLB' ? 'primary' : ($item['location_code'] === 'CNL' ? 'warning' : 'secondary') ?>">
                                <?= htmlspecialchars($item['location_code'] ?? 'UNK') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?= $item['status'] === 'active' ? 'success' : ($item['status'] === 'shipped' ? 'info' : 'secondary') ?>">
                                <?= ucfirst($item['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (empty($paginatedData)): ?>
<div class="text-center py-5">
    <i class="bi bi-inbox display-4 text-muted"></i>
    <h4 class="text-muted mt-3">No delivery schedules found</h4>
    <p class="text-muted">Try adjusting your filters or check if data has been imported.</p>
</div>
<?php endif; ?>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export to Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Export Template</label>
                    <select id="exportTemplate" class="form-select" required>
                        <option value="delivery_matrix">Complete Delivery Matrix</option>
                        <option value="daily_production">Daily Production Plan</option>
                        <option value="weekly_planning">Weekly Planning Report</option>
                        <option value="location_specific">Location-Specific Report</option>
                        <option value="po_specific">PO-Specific Report</option>
                    </select>
                    <div class="form-text">
                        Choose the template that best fits your reporting needs.
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <strong>Export will include:</strong><br>
                    • <?= number_format(count($data)) ?> records matching current filters<br>
                    • Container calculations using QPC data<br>
                    • Location-specific PO formatting<br>
                    • Summary statistics and totals
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="exportDeliveryMatrix()">
                    <i class="bi bi-download me-1"></i>Export Excel
                </button>
            </div>
            
            <script>
            function exportDeliveryMatrix() {
                const template = document.getElementById('exportTemplate').value;
                
                // Build export URL pointing to dedicated export handler
                const exportUrl = new URL(window.location.origin + '/edimodule/src/public/export_delivery_matrix.php');
                exportUrl.searchParams.set('export', 'delivery_matrix');
                exportUrl.searchParams.set('export_template', template);
                
                // Add current filters
                <?php foreach ($filters as $key => $value): ?>
                    <?php if (!empty($value)): ?>
                        exportUrl.searchParams.set('<?= htmlspecialchars($key) ?>', '<?= htmlspecialchars($value) ?>');
                    <?php endif; ?>
                <?php endforeach; ?>
                
                // Redirect to export URL
                window.location.href = exportUrl.toString();
            }
            </script>
        </div>
    </div>
</div>