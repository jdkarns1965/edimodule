<?php
require_once '../classes/ExcelExportService.php';

$exportService = new ExcelExportService();

// Handle export requests
if (isset($_GET['export'])) {
    try {
        $format = $_GET['format'] ?? 'xlsx';
        $result = $exportService->exportCustomers($format);
        
        if ($result['success']) {
            header('Location: ../../' . $result['download_url']);
            exit;
        }
    } catch (Exception $e) {
        $error = "Export failed: " . $e->getMessage();
    }
}

// Handle customer operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_customer':
                try {
                    $sql = "INSERT INTO trading_partners (partner_code, name, edi_id, connection_type, status, contact_email) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $db->getConnection()->prepare($sql);
                    $stmt->execute([
                        $_POST['partner_code'],
                        $_POST['name'],
                        $_POST['edi_id'],
                        $_POST['connection_type'],
                        $_POST['status'],
                        $_POST['contact_email']
                    ]);
                    $success = "Customer added successfully";
                } catch (Exception $e) {
                    $error = "Failed to add customer: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get customers with stats
$sql = "SELECT tp.*, 
               COUNT(DISTINCT ds.id) as active_orders,
               COUNT(DISTINCT sl.id) as locations_count,
               MAX(ds.created_at) as last_order_date
        FROM trading_partners tp
        LEFT JOIN delivery_schedules ds ON tp.id = ds.partner_id AND ds.status = 'active'
        LEFT JOIN ship_to_locations sl ON tp.id = sl.partner_id AND sl.active = 1
        GROUP BY tp.id
        ORDER BY tp.name";

$customers = $db->getConnection()->query($sql)->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="bi bi-people me-2"></i>Customer Management</h2>
            <div class="btn-group">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                    <i class="bi bi-plus-circle me-1"></i>Add Customer
                </button>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-download me-1"></i>Export to Excel
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?page=customers&export=1&format=xlsx">
                            <i class="bi bi-file-excel me-1"></i>Excel (.xlsx)</a></li>
                        <li><a class="dropdown-item" href="?page=customers&export=1&format=csv">
                            <i class="bi bi-file-text me-1"></i>CSV</a></li>
                    </ul>
                </div>
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

<!-- Customer Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-people fs-1 text-primary me-3"></i>
                    <div>
                        <h3 class="mb-0"><?= count($customers) ?></h3>
                        <small class="text-muted">Total Customers</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card success">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle fs-1 text-success me-3"></i>
                    <div>
                        <h3 class="mb-0"><?= count(array_filter($customers, fn($c) => $c['status'] === 'active')) ?></h3>
                        <small class="text-muted">Active Customers</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card warning">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-cart fs-1 text-warning me-3"></i>
                    <div>
                        <h3 class="mb-0"><?= array_sum(array_column($customers, 'active_orders')) ?></h3>
                        <small class="text-muted">Active Orders</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card danger">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-geo-alt fs-1 text-info me-3"></i>
                    <div>
                        <h3 class="mb-0"><?= array_sum(array_column($customers, 'locations_count')) ?></h3>
                        <small class="text-muted">Ship-To Locations</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Customer List</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Partner Code</th>
                        <th>Company Name</th>
                        <th>EDI ID</th>
                        <th>Connection</th>
                        <th>Status</th>
                        <th>Orders</th>
                        <th>Locations</th>
                        <th>Last Order</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($customer['partner_code']) ?></strong></td>
                        <td><?= htmlspecialchars($customer['name']) ?></td>
                        <td><code><?= htmlspecialchars($customer['edi_id']) ?></code></td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($customer['connection_type']) ?></span>
                        </td>
                        <td>
                            <?php 
                            $statusClass = match($customer['status']) {
                                'active' => 'bg-success',
                                'inactive' => 'bg-danger',
                                'testing' => 'bg-warning',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= strtoupper($customer['status']) ?></span>
                        </td>
                        <td>
                            <?php if ($customer['active_orders'] > 0): ?>
                                <span class="badge bg-primary"><?= $customer['active_orders'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($customer['locations_count'] > 0): ?>
                                <span class="badge bg-info"><?= $customer['locations_count'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($customer['last_order_date']): ?>
                                <small><?= date('M j, Y', strtotime($customer['last_order_date'])) ?></small>
                            <?php else: ?>
                                <small class="text-muted">None</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" title="Edit Customer">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <div class="btn-group">
                                    <button class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" title="Export Customer Data">
                                        <i class="bi bi-download"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="?page=customers&export_customer=<?= $customer['id'] ?>&format=xlsx">
                                            <i class="bi bi-file-excel me-1"></i>Customer Data (Excel)</a></li>
                                        <li><a class="dropdown-item" href="?page=schedules&customer_id=<?= $customer['id'] ?>">
                                            <i class="bi bi-calendar me-1"></i>View Schedules</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($customers)): ?>
        <div class="text-center py-4">
            <i class="bi bi-people fs-1 text-muted"></i>
            <h5 class="mt-2 text-muted">No customers found</h5>
            <p class="text-muted">Add your first customer to get started with EDI processing.</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                <i class="bi bi-plus-circle me-1"></i>Add First Customer
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_customer">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="partner_code" class="form-label">Partner Code *</label>
                                <input type="text" class="form-control" id="partner_code" name="partner_code" 
                                       placeholder="e.g., NIFCO" maxlength="20" required>
                                <small class="form-text text-muted">Short identifier for this customer</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edi_id" class="form-label">EDI ID *</label>
                                <input type="text" class="form-control" id="edi_id" name="edi_id" 
                                       placeholder="e.g., 6148363808" maxlength="15" required>
                                <small class="form-text text-muted">EDI identifier number</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Company Name *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               placeholder="e.g., Nifco Inc." maxlength="100" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="connection_type" class="form-label">Connection Type *</label>
                                <select class="form-select" id="connection_type" name="connection_type" required>
                                    <option value="">Select connection type</option>
                                    <option value="SFTP" selected>SFTP</option>
                                    <option value="AS2">AS2</option>
                                    <option value="FTP">FTP</option>
                                    <option value="VAN">VAN</option>
                                    <option value="API">API</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="testing" selected>Testing</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact_email" class="form-label">Contact Email</label>
                        <input type="email" class="form-control" id="contact_email" name="contact_email" 
                               placeholder="contact@customer.com" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-generate partner code from company name
    document.getElementById('name').addEventListener('input', function() {
        const name = this.value;
        const partnerCode = document.getElementById('partner_code');
        
        if (name && !partnerCode.value) {
            // Generate partner code from company name
            const code = name
                .split(' ')
                .map(word => word.charAt(0))
                .join('')
                .toUpperCase()
                .substring(0, 6);
            partnerCode.value = code;
        }
    });
});
</script>