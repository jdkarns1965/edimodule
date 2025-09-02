<?php
try {
    $conn = $db->getConnection();
    
    $totalSchedules = $conn->query("SELECT COUNT(*) as count FROM delivery_schedules WHERE status = 'active'")->fetch()['count'];
    $totalPartners = $conn->query("SELECT COUNT(*) as count FROM trading_partners WHERE status = 'active'")->fetch()['count'];
    $totalTransactions = $conn->query("SELECT COUNT(*) as count FROM edi_transactions WHERE status = 'processed'")->fetch()['count'];
    $pendingShipments = $conn->query("SELECT COUNT(*) as count FROM delivery_schedules WHERE status = 'active' AND promised_date <= CURDATE()")->fetch()['count'];
    
    $recentSchedules = $conn->query("
        SELECT ds.*, tp.name as partner_name, sl.location_description 
        FROM delivery_schedules ds
        LEFT JOIN trading_partners tp ON ds.partner_id = tp.id
        LEFT JOIN ship_to_locations sl ON ds.ship_to_location_id = sl.id
        WHERE ds.status = 'active'
        ORDER BY ds.promised_date ASC, ds.created_at DESC
        LIMIT 10
    ")->fetchAll();
    
    $upcomingDeliveries = $conn->query("
        SELECT DATE(promised_date) as delivery_date, COUNT(*) as count, SUM(quantity_ordered) as total_qty
        FROM delivery_schedules 
        WHERE status = 'active' AND promised_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        GROUP BY DATE(promised_date)
        ORDER BY delivery_date
    ")->fetchAll();
    
} catch (Exception $e) {
    AppConfig::logError("Dashboard error: " . $e->getMessage());
    $error = "Database connection error. Please check configuration.";
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-speedometer2 me-2"></i>EDI Processing Dashboard</h2>
        <p class="text-muted"><?= AppConfig::COMPANY_NAME ?> - Real-time delivery schedule monitoring</p>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
</div>
<?php else: ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Active Schedules</h6>
                        <h2 class="card-title mb-0"><?= number_format($totalSchedules) ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-calendar-check text-primary" style="font-size: 2rem;"></i>
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
                        <h6 class="card-subtitle mb-2 text-muted">Trading Partners</h6>
                        <h2 class="card-title mb-0"><?= number_format($totalPartners) ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-building text-success" style="font-size: 2rem;"></i>
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
                        <h6 class="card-subtitle mb-2 text-muted">EDI Transactions</h6>
                        <h2 class="card-title mb-0"><?= number_format($totalTransactions) ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-arrow-left-right text-warning" style="font-size: 2rem;"></i>
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
                        <h6 class="card-subtitle mb-2 text-muted">Due Today</h6>
                        <h2 class="card-title mb-0"><?= number_format($pendingShipments) ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-exclamation-triangle text-danger" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock me-2"></i>Recent Delivery Schedules</h5>
                <a href="?page=schedules" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentSchedules)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <p>No delivery schedules found. <a href="?page=import">Import some data</a> to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>PO Number</th>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Promised Date</th>
                                    <th>Ship To</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSchedules as $schedule): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($schedule['po_line']) ?></code></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($schedule['supplier_item']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars(substr($schedule['item_description'], 0, 40)) ?>...</small>
                                    </td>
                                    <td><?= number_format($schedule['quantity_ordered']) ?> <?= $schedule['uom'] ?></td>
                                    <td><?= date('M j, Y', strtotime($schedule['promised_date'])) ?></td>
                                    <td><?= htmlspecialchars($schedule['location_description'] ?? $schedule['ship_to_description']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $schedule['status'] == 'active' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($schedule['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Upcoming Deliveries</h5>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingDeliveries)): ?>
                    <p class="text-muted text-center">No upcoming deliveries scheduled.</p>
                <?php else: ?>
                    <?php foreach ($upcomingDeliveries as $delivery): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <div class="fw-bold"><?= date('M j', strtotime($delivery['delivery_date'])) ?></div>
                            <small class="text-muted"><?= $delivery['count'] ?> schedules</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-primary"><?= number_format($delivery['total_qty']) ?></div>
                            <small class="text-muted">pieces</small>
                        </div>
                    </div>
                    <hr class="my-2">
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="?page=sftp" class="btn btn-primary">
                        <i class="bi bi-cloud-arrow-down me-2"></i>SFTP Dashboard
                    </a>
                    <a href="?page=import" class="btn btn-outline-primary">
                        <i class="bi bi-upload me-2"></i>Import TSV Data
                    </a>
                    <a href="?page=schedules" class="btn btn-outline-secondary">
                        <i class="bi bi-calendar me-2"></i>View All Schedules
                    </a>
                    <a href="?page=transactions" class="btn btn-outline-secondary">
                        <i class="bi bi-file-text me-2"></i>EDI Transactions
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>