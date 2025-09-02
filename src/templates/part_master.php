<?php
require_once '../classes/PartMaster.php';

use Greenfield\EDI\PartMaster;

$partMaster = new PartMaster($db);
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$messageType = '';

// Handle form submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    switch ($action) {
        case 'create':
            if ($partMaster->createPart($_POST)) {
                $message = 'Part created successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error creating part.';
                $messageType = 'danger';
            }
            break;
            
        case 'update':
            if ($partMaster->updatePart($_POST['original_part_number'], $_POST)) {
                $message = 'Part updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error updating part.';
                $messageType = 'danger';
            }
            break;
            
        case 'delete':
            if ($partMaster->deletePart($_POST['part_number'])) {
                $message = 'Part deactivated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error deactivating part.';
                $messageType = 'danger';
            }
            break;
            
        case 'import':
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $csvFile = $_FILES['csv_file']['tmp_name'];
                $csvData = array_map('str_getcsv', file($csvFile));
                $result = $partMaster->importPartsFromCSV($csvData);
                
                if ($result['success']) {
                    $message = "Import successful! {$result['imported']} parts imported.";
                    if (!empty($result['errors'])) {
                        $message .= " Errors: " . implode(', ', $result['errors']);
                    }
                    $messageType = 'success';
                } else {
                    $message = 'Import failed: ' . $result['message'];
                    $messageType = 'danger';
                }
            } else {
                $message = 'Please select a valid CSV file.';
                $messageType = 'danger';
            }
            break;
            
        case 'auto_detect':
            $count = $partMaster->autoDetectNewParts();
            $message = "Auto-detection complete! {$count} new parts added.";
            $messageType = 'info';
            break;
    }
}

// Get parameters for listing
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;
$editPart = null;

if ($action === 'edit' && !empty($_GET['part'])) {
    $editPart = $partMaster->getPartByNumber($_GET['part']);
}

$parts = $partMaster->getAllParts($search, true, $limit, $offset);
$totalParts = $partMaster->getPartCount($search);
$totalPages = ceil($totalParts / $limit);
$productFamilies = $partMaster->getProductFamilies();
$materials = $partMaster->getMaterials();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-gear me-2"></i>Part Master Management</h2>
    <div>
        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addPartModal">
            <i class="bi bi-plus-circle me-1"></i>Add Part
        </button>
        <button class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="bi bi-upload me-1"></i>Import CSV
        </button>
        <form method="post" class="d-inline">
            <input type="hidden" name="action" value="auto_detect">
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-search me-1"></i>Auto-Detect New Parts
            </button>
        </form>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Search and Filter Section -->
<div class="row mb-4">
    <div class="col-md-8">
        <form method="get" class="d-flex">
            <input type="hidden" name="page" value="part_master">
            <input type="text" name="search" class="form-control me-2" placeholder="Search parts..." 
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-search"></i>
            </button>
        </form>
    </div>
    <div class="col-md-4 text-end">
        <small class="text-muted">Showing <?= number_format(count($parts)) ?> of <?= number_format($totalParts) ?> parts</small>
    </div>
</div>

<!-- Parts Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Part Number</th>
                        <th>Customer Part</th>
                        <th>Description</th>
                        <th>QPC</th>
                        <th>UOM</th>
                        <th>Product Family</th>
                        <th>Auto-Detected</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parts as $part): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($part['part_number']) ?></strong>
                            <?php if (!$part['active']): ?>
                                <span class="badge bg-secondary ms-1">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($part['customer_part_number'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($part['description'] ?: '-') ?></td>
                        <td><span class="badge bg-primary"><?= $part['qpc'] ?></span></td>
                        <td><?= htmlspecialchars($part['uom']) ?></td>
                        <td><?= htmlspecialchars($part['product_family'] ?: '-') ?></td>
                        <td>
                            <?php if ($part['auto_detected']): ?>
                                <span class="badge bg-warning">Yes</span>
                            <?php else: ?>
                                <span class="badge bg-success">No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?page=part_master&action=edit&part=<?= urlencode($part['part_number']) ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button onclick="deletePart('<?= htmlspecialchars($part['part_number']) ?>')" 
                                        class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=part_master&p=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- Add Part Modal -->
<div class="modal fade" id="addPartModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Part</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Part Number *</label>
                                <input type="text" name="part_number" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Customer Part Number</label>
                                <input type="text" name="customer_part_number" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">QPC *</label>
                                <input type="number" name="qpc" class="form-control" value="1" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">UOM</label>
                                <select name="uom" class="form-control">
                                    <option value="EACH">EACH</option>
                                    <option value="LB">LB</option>
                                    <option value="KG">KG</option>
                                    <option value="FT">FT</option>
                                    <option value="M">M</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Weight</label>
                                <input type="number" name="weight" class="form-control" step="0.0001">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Dimensions</label>
                                <input type="text" name="dimensions" class="form-control" placeholder="L x W x H">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Material</label>
                                <input type="text" name="material" class="form-control" list="materials">
                                <datalist id="materials">
                                    <?php foreach ($materials as $material): ?>
                                        <option value="<?= htmlspecialchars($material) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Product Family</label>
                                <input type="text" name="product_family" class="form-control" list="families">
                                <datalist id="families">
                                    <?php foreach ($productFamilies as $family): ?>
                                        <option value="<?= htmlspecialchars($family) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" name="active" value="1" class="form-check-input" id="activeAdd" checked>
                        <label class="form-check-label" for="activeAdd">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Part</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Import Parts from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="import">
                    
                    <div class="mb-3">
                        <label class="form-label">CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        <div class="form-text">
                            CSV format: Part Number, Customer Part, Description, QPC, UOM, Weight, Dimensions, Material, Color, Product Family, Notes
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>CSV Format:</strong><br>
                        Column 1: Part Number (required)<br>
                        Column 2: Customer Part Number<br>
                        Column 3: Description<br>
                        Column 4: QPC (Quantity Per Container)<br>
                        Column 5: UOM (Unit of Measure)<br>
                        Column 6: Weight<br>
                        Column 7: Dimensions<br>
                        Column 8: Material<br>
                        Column 9: Color<br>
                        Column 10: Product Family<br>
                        Column 11: Notes
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Part Modal -->
<?php if ($editPart): ?>
<div class="modal fade show" id="editPartModal" style="display: block;" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Part: <?= htmlspecialchars($editPart['part_number']) ?></h5>
                    <a href="?page=part_master" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="original_part_number" value="<?= htmlspecialchars($editPart['part_number']) ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Part Number *</label>
                                <input type="text" name="part_number" class="form-control" 
                                       value="<?= htmlspecialchars($editPart['part_number']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Customer Part Number</label>
                                <input type="text" name="customer_part_number" class="form-control"
                                       value="<?= htmlspecialchars($editPart['customer_part_number']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control"
                               value="<?= htmlspecialchars($editPart['description']) ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">QPC *</label>
                                <input type="number" name="qpc" class="form-control" min="1" required
                                       value="<?= $editPart['qpc'] ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">UOM</label>
                                <select name="uom" class="form-control">
                                    <option value="EACH" <?= $editPart['uom'] === 'EACH' ? 'selected' : '' ?>>EACH</option>
                                    <option value="LB" <?= $editPart['uom'] === 'LB' ? 'selected' : '' ?>>LB</option>
                                    <option value="KG" <?= $editPart['uom'] === 'KG' ? 'selected' : '' ?>>KG</option>
                                    <option value="FT" <?= $editPart['uom'] === 'FT' ? 'selected' : '' ?>>FT</option>
                                    <option value="M" <?= $editPart['uom'] === 'M' ? 'selected' : '' ?>>M</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Weight</label>
                                <input type="number" name="weight" class="form-control" step="0.0001"
                                       value="<?= $editPart['weight'] ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Dimensions</label>
                                <input type="text" name="dimensions" class="form-control" placeholder="L x W x H"
                                       value="<?= htmlspecialchars($editPart['dimensions']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Material</label>
                                <input type="text" name="material" class="form-control"
                                       value="<?= htmlspecialchars($editPart['material']) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" class="form-control"
                                       value="<?= htmlspecialchars($editPart['color']) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Product Family</label>
                                <input type="text" name="product_family" class="form-control"
                                       value="<?= htmlspecialchars($editPart['product_family']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($editPart['notes']) ?></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" name="active" value="1" class="form-check-input" id="activeEdit" 
                               <?= $editPart['active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activeEdit">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="?page=part_master" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Part</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<script>
function deletePart(partNumber) {
    if (confirm('Are you sure you want to deactivate part "' + partNumber + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="part_number" value="${partNumber}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>