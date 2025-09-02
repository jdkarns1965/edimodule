<?php
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$direction = $_GET['direction'] ?? '';

try {
    $conn = $db->getConnection();
    
    $whereConditions = ["1=1"];
    $params = [];
    
    if ($type) {
        $whereConditions[] = "et.transaction_type = ?";
        $params[] = $type;
    }
    
    if ($status) {
        $whereConditions[] = "et.status = ?";
        $params[] = $status;
    }
    
    if ($direction) {
        $whereConditions[] = "et.direction = ?";
        $params[] = $direction;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $countSql = "SELECT COUNT(*) as total FROM edi_transactions et WHERE $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    $sql = "
        SELECT et.*, tp.name as partner_name
        FROM edi_transactions et
        LEFT JOIN trading_partners tp ON et.partner_id = tp.id
        WHERE $whereClause
        ORDER BY et.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    $stats = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
            SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
            SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound,
            SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound
        FROM edi_transactions
    ")->fetch();
    
} catch (Exception $e) {
    AppConfig::logError("Transactions page error: " . $e->getMessage());
    $error = "Database error occurred.";
    $transactions = [];
    $totalRecords = 0;
    $totalPages = 0;
    $stats = ['total' => 0, 'processed' => 0, 'errors' => 0, 'inbound' => 0, 'outbound' => 0];
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-file-text me-2"></i>EDI Transactions</h2>
        <p class="text-muted">Monitor EDI transaction processing and audit trail</p>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Total Transactions</h6>
                        <h3 class="card-title mb-0"><?= number_format($stats['total']) ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-file-text text-primary" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Processed</h6>
                        <h3 class="card-title mb-0"><?= number_format($stats['processed']) ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card warning h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Inbound</h6>
                        <h3 class="card-title mb-0"><?= number_format($stats['inbound']) ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-arrow-down-circle text-info" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card danger h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Errors</h6>
                        <h3 class="card-title mb-0"><?= number_format($stats['errors']) ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-exclamation-triangle text-danger" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="transactions">
            
            <div class="col-md-3">
                <label for="type" class="form-label">Transaction Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All Types</option>
                    <option value="862" <?= $type == '862' ? 'selected' : '' ?>>862 - Shipping Schedule</option>
                    <option value="856" <?= $type == '856' ? 'selected' : '' ?>>856 - Advance Ship Notice</option>
                    <option value="TSV" <?= $type == 'TSV' ? 'selected' : '' ?>>TSV - Manual Import</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="direction" class="form-label">Direction</label>
                <select class="form-select" id="direction" name="direction">
                    <option value="">All Directions</option>
                    <option value="inbound" <?= $direction == 'inbound' ? 'selected' : '' ?>>Inbound</option>
                    <option value="outbound" <?= $direction == 'outbound' ? 'selected' : '' ?>>Outbound</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="received" <?= $status == 'received' ? 'selected' : '' ?>>Received</option>
                    <option value="processing" <?= $status == 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="processed" <?= $status == 'processed' ? 'selected' : '' ?>>Processed</option>
                    <option value="error" <?= $status == 'error' ? 'selected' : '' ?>>Error</option>
                    <option value="archived" <?= $status == 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>
            
            <div class="col-md-3 d-flex align-items-end">
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
            Transaction History (<?= number_format($totalRecords) ?> records)
        </h5>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="refreshPage()">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>
    <div class="card-body p-0">
        <?php if (empty($transactions)): ?>
            <div class="text-center p-5">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No transactions found</h4>
                <p class="text-muted">Try adjusting your filter criteria or <a href="?page=import">import some data</a> to generate transactions.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Direction</th>
                            <th>Control Number</th>
                            <th>Filename</th>
                            <th>Partner</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Processed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><code><?= $transaction['id'] ?></code></td>
                            <td>
                                <span class="badge bg-<?php
                                    switch($transaction['transaction_type']) {
                                        case '862': echo 'primary'; break;
                                        case '856': echo 'success'; break;
                                        case 'TSV': echo 'info'; break;
                                        default: echo 'secondary';
                                    }
                                ?>">
                                    <?= htmlspecialchars($transaction['transaction_type']) ?>
                                </span>
                            </td>
                            <td>
                                <i class="bi bi-arrow-<?= $transaction['direction'] == 'inbound' ? 'down' : 'up' ?>-circle 
                                   text-<?= $transaction['direction'] == 'inbound' ? 'info' : 'warning' ?> me-1"></i>
                                <?= ucfirst($transaction['direction']) ?>
                            </td>
                            <td>
                                <?php if ($transaction['control_number']): ?>
                                    <code><?= htmlspecialchars($transaction['control_number']) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($transaction['filename']): ?>
                                    <?= htmlspecialchars(basename($transaction['filename'])) ?>
                                    <?php if ($transaction['file_size']): ?>
                                        <br><small class="text-muted"><?= number_format($transaction['file_size']) ?> bytes</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Manual</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($transaction['partner_name']) ?></td>
                            <td>
                                <span class="badge bg-<?php
                                    switch($transaction['status']) {
                                        case 'received': echo 'secondary'; break;
                                        case 'processing': echo 'warning'; break;
                                        case 'processed': echo 'success'; break;
                                        case 'error': echo 'danger'; break;
                                        case 'archived': echo 'dark'; break;
                                        default: echo 'secondary';
                                    }
                                ?>">
                                    <?= ucfirst($transaction['status']) ?>
                                </span>
                                <?php if ($transaction['error_message']): ?>
                                    <i class="bi bi-exclamation-triangle text-danger ms-1" 
                                       data-bs-toggle="tooltip" 
                                       title="<?= htmlspecialchars(substr($transaction['error_message'], 0, 100)) ?>..."></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= date('M j, Y', strtotime($transaction['created_at'])) ?></div>
                                <small class="text-muted"><?= date('H:i:s', strtotime($transaction['created_at'])) ?></small>
                            </td>
                            <td>
                                <?php if ($transaction['processed_at']): ?>
                                    <div><?= date('M j, Y', strtotime($transaction['processed_at'])) ?></div>
                                    <small class="text-muted"><?= date('H:i:s', strtotime($transaction['processed_at'])) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                            data-bs-toggle="modal" data-bs-target="#transactionModal<?= $transaction['id'] ?>"
                                            title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if ($transaction['raw_content']): ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                                onclick="downloadTransaction(<?= $transaction['id'] ?>)"
                                                title="Download Raw Content">
                                            <i class="bi bi-download"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Transaction Detail Modal -->
                        <div class="modal fade" id="transactionModal<?= $transaction['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Transaction Details - ID <?= $transaction['id'] ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>Type:</strong> <?= htmlspecialchars($transaction['transaction_type']) ?><br>
                                                <strong>Direction:</strong> <?= ucfirst($transaction['direction']) ?><br>
                                                <strong>Status:</strong> 
                                                <span class="badge bg-<?php
                                                    switch($transaction['status']) {
                                                        case 'processed': echo 'success'; break;
                                                        case 'error': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?= ucfirst($transaction['status']) ?>
                                                </span>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Partner:</strong> <?= htmlspecialchars($transaction['partner_name']) ?><br>
                                                <strong>Control Number:</strong> <?= htmlspecialchars($transaction['control_number'] ?? 'N/A') ?><br>
                                                <strong>Filename:</strong> <?= htmlspecialchars($transaction['filename'] ?? 'Manual') ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($transaction['error_message']): ?>
                                        <div class="alert alert-danger">
                                            <strong>Error Message:</strong><br>
                                            <?= htmlspecialchars($transaction['error_message']) ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($transaction['processing_notes']): ?>
                                        <div class="mb-3">
                                            <strong>Processing Notes:</strong><br>
                                            <pre class="bg-light p-2 rounded"><?= htmlspecialchars($transaction['processing_notes']) ?></pre>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($transaction['parsed_content']): ?>
                                        <div class="mb-3">
                                            <strong>Parsed Content:</strong>
                                            <pre class="bg-light p-2 rounded" style="max-height: 300px; overflow-y: auto;"><?= htmlspecialchars(json_encode(json_decode($transaction['parsed_content']), JSON_PRETTY_PRINT)) ?></pre>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <?php if ($transaction['raw_content']): ?>
                                            <button type="button" class="btn btn-primary" onclick="downloadTransaction(<?= $transaction['id'] ?>)">
                                                <i class="bi bi-download me-1"></i>Download Raw
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Transactions pagination">
                    <ul class="pagination mb-0 justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=transactions&p=<?= $page-1 ?>&type=<?= urlencode($type) ?>&status=<?= urlencode($status) ?>&direction=<?= urlencode($direction) ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=transactions&p=<?= $i ?>&type=<?= urlencode($type) ?>&status=<?= urlencode($status) ?>&direction=<?= urlencode($direction) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=transactions&p=<?= $page+1 ?>&type=<?= urlencode($type) ?>&status=<?= urlencode($status) ?>&direction=<?= urlencode($direction) ?>">
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

<script>
// Initialize Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
})

function downloadTransaction(id) {
    // This would be implemented to download raw transaction content
    alert('Download functionality would be implemented here for transaction ID: ' + id);
}
</script>