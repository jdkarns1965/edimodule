<?php
require_once dirname(__DIR__) . '/classes/CustomerConfig.php';

$importResult = null;
$error = null;
$selectedCustomer = $_POST['customer_id'] ?? ($_GET['customer'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get selected customer ID
        $customerId = $_POST['customer_id'] ?? null;
        
        if (isset($_POST['import_sample'])) {
            $sampleFile = dirname(dirname(__DIR__)) . '/sample_data.tsv';
            if (file_exists($sampleFile)) {
                $importer = new TSVImporter();
                $importResult = $importer->importFromFile($sampleFile, $customerId);
            } else {
                $error = "Sample data file not found.";
            }
        } elseif (isset($_FILES['tsv_file'])) {
            if ($_FILES['tsv_file']['error'] === UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES['tsv_file']['tmp_name'];
                $filename = $_FILES['tsv_file']['name'];
                
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($extension, ['tsv', 'csv'])) {
                    throw new Exception("Only TSV and CSV files are allowed.");
                }
                
                if (!$customerId) {
                    throw new Exception("Please select a customer before importing data.");
                }
                
                $importer = new TSVImporter();
                $importResult = $importer->importFromFile($uploadedFile, $customerId);
                
                $archivePath = AppConfig::getArchivePath() . '/' . date('Y-m-d_H-i-s') . '_' . $filename;
                move_uploaded_file($uploadedFile, $archivePath);
                
            } else {
                $error = "File upload failed. Error code: " . $_FILES['tsv_file']['error'];
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        AppConfig::logError("Import error: " . $e->getMessage());
    }
}

try {
    $conn = $db->getConnection();
    
    // Get recent imports
    $recentImports = $conn->query("
        SELECT et.*, tp.name as partner_name
        FROM edi_transactions et
        LEFT JOIN trading_partners tp ON et.partner_id = tp.id
        WHERE et.transaction_type = 'TSV'
        ORDER BY et.created_at DESC
        LIMIT 10
    ")->fetchAll();
    
    // Get active customers for dropdown
    $customers = $conn->query("
        SELECT id, partner_code, name, edi_standard
        FROM trading_partners 
        WHERE status = 'active'
        ORDER BY partner_code
    ")->fetchAll();
    
} catch (Exception $e) {
    $recentImports = [];
    $customers = [];
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-upload me-2"></i>Data Import</h2>
        <p class="text-muted">Import delivery schedule data from TSV files</p>
    </div>
</div>

<?php if ($importResult): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <strong>Import completed successfully!</strong><br>
    Processed: <?= $importResult['processed'] ?> records, 
    Inserted: <?= $importResult['inserted'] ?>, 
    Updated: <?= $importResult['updated'] ?>, 
    Skipped: <?= $importResult['skipped'] ?>
    <?php if (!empty($importResult['errors'])): ?>
        <br>Errors: <?= count($importResult['errors']) ?>
    <?php endif; ?>
    <br><small>Duration: <?= $importResult['duration'] ?> seconds</small>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<?php if (!empty($importResult['errors'])): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Import completed with errors:</strong>
    <ul class="mt-2 mb-0">
        <?php foreach (array_slice($importResult['errors'], 0, 5) as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
        <?php if (count($importResult['errors']) > 5): ?>
            <li><em>... and <?= count($importResult['errors']) - 5 ?> more errors</em></li>
        <?php endif; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Import failed:</strong> <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Upload TSV File</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <!-- Customer Selection -->
                    <div class="mb-4">
                        <label for="customer_id" class="form-label">
                            <i class="bi bi-building me-2"></i>Select Customer *
                        </label>
                        <select name="customer_id" id="customer_id" class="form-select" required onchange="updateTemplateInfo()">
                            <option value="">Choose a customer...</option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>" 
                                    <?= $selectedCustomer == $customer['id'] ? 'selected' : '' ?>
                                    data-standard="<?= $customer['edi_standard'] ?>"
                                    data-code="<?= $customer['partner_code'] ?>">
                                <?= htmlspecialchars($customer['partner_code']) ?> - <?= htmlspecialchars($customer['name']) ?>
                                (<?= $customer['edi_standard'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Select the customer/trading partner for this import. Different customers may use different field formats.
                        </div>
                        <div id="customerInfo" class="mt-2 d-none">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <span id="customerInfoText"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="upload-area mb-3" id="uploadArea">
                        <i class="bi bi-cloud-upload display-1 text-muted"></i>
                        <h5 class="mt-3">Drop your TSV or CSV file here or click to browse</h5>
                        <p class="text-muted">Supported formats: Tab-separated (.tsv) or Comma-separated (.csv)</p>
                        <input type="file" class="form-control d-none" id="tsv_file" name="tsv_file" accept=".tsv,.csv" required>
                        <button type="button" class="btn btn-outline-primary" onclick="event.stopPropagation(); document.getElementById('tsv_file').click()">
                            <i class="bi bi-folder2-open me-2"></i>Browse Files
                        </button>
                    </div>
                    <div id="selectedFile" class="mb-3 d-none">
                        <div class="alert alert-info">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            Selected: <span id="fileName"></span>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>
                            <i class="bi bi-upload me-2"></i>Import Data
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <h6 class="text-muted mb-3">Or use sample data for testing:</h6>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="customer_id" id="sampleCustomerId" value="">
                        <button type="submit" name="import_sample" class="btn btn-outline-secondary" id="sampleBtn" disabled>
                            <i class="bi bi-play-circle me-2"></i>Import Sample Data
                        </button>
                    </form>
                    <div class="form-text mt-2">Select a customer above to enable sample import</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Import Requirements</h5>
            </div>
            <div class="card-body">
                <h6>Required Columns:</h6>
                <ul class="list-unstyled">
                    <li><i class="bi bi-check text-success me-2"></i>PO Number</li>
                    <li><i class="bi bi-check text-success me-2"></i>Supplier Item</li>
                    <li><i class="bi bi-check text-success me-2"></i>Item Description</li>
                    <li><i class="bi bi-check text-success me-2"></i>Quantity Ordered</li>
                    <li><i class="bi bi-check text-success me-2"></i>Promised Date</li>
                    <li><i class="bi bi-check text-success me-2"></i>Ship-To Location</li>
                </ul>
                
                <h6 class="mt-3">Optional Columns:</h6>
                <ul class="list-unstyled">
                    <li><i class="bi bi-dash text-muted me-2"></i>Quantity Received</li>
                    <li><i class="bi bi-dash text-muted me-2"></i>Need-By Date</li>
                    <li><i class="bi bi-dash text-muted me-2"></i>UOM</li>
                    <li><i class="bi bi-dash text-muted me-2"></i>Organization</li>
                    <li><i class="bi bi-dash text-muted me-2"></i>Supplier</li>
                </ul>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-download me-2"></i>Import Template</h5>
            </div>
            <div class="card-body text-center">
                <p class="text-muted">Download a properly formatted CSV template with sample data</p>
                <a href="download_template.php" 
                   class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-spreadsheet me-2"></i>Download CSV Template
                </a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Imports</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentImports)): ?>
                    <div class="p-3 text-center text-muted">
                        <p class="mb-0">No import history available</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($recentImports, 0, 5) as $import): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($import['filename'] ?? 'Manual Import') ?></h6>
                                    <p class="mb-1 small text-muted">
                                        Status: 
                                        <span class="badge bg-<?= $import['status'] == 'processed' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($import['status']) ?>
                                        </span>
                                    </p>
                                </div>
                                <small class="text-muted"><?= date('M j, H:i', strtotime($import['created_at'])) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Customer selection handling
function updateTemplateInfo() {
    const customerSelect = document.getElementById('customer_id');
    const customerInfo = document.getElementById('customerInfo');
    const customerInfoText = document.getElementById('customerInfoText');
    const sampleCustomerId = document.getElementById('sampleCustomerId');
    const sampleBtn = document.getElementById('sampleBtn');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (customerSelect.value) {
        const selectedOption = customerSelect.selectedOptions[0];
        const customerCode = selectedOption.dataset.code;
        const ediStandard = selectedOption.dataset.standard;
        
        customerInfoText.textContent = `Selected: ${customerCode} (${ediStandard} format)`;
        customerInfo.classList.remove('d-none');
        
        sampleCustomerId.value = customerSelect.value;
        sampleBtn.disabled = false;
        
        // Enable upload button if file is also selected
        const fileInput = document.getElementById('tsv_file');
        if (fileInput.files.length > 0) {
            uploadBtn.disabled = false;
        }
    } else {
        customerInfo.classList.add('d-none');
        sampleCustomerId.value = '';
        sampleBtn.disabled = true;
        uploadBtn.disabled = true;
    }
}

document.getElementById('tsv_file').addEventListener('change', function() {
    const file = this.files[0];
    const customerSelect = document.getElementById('customer_id');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (file) {
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('selectedFile').classList.remove('d-none');
        
        // Only enable upload if customer is also selected
        if (customerSelect.value) {
            uploadBtn.disabled = false;
        }
    }
});

// Drag and drop functionality
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('tsv_file');

uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    uploadArea.style.borderColor = '#007bff';
    uploadArea.style.backgroundColor = '#f8f9fa';
});

uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    uploadArea.style.borderColor = '#dee2e6';
    uploadArea.style.backgroundColor = '';
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    uploadArea.style.borderColor = '#dee2e6';
    uploadArea.style.backgroundColor = '';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        fileInput.dispatchEvent(new Event('change'));
    }
});

uploadArea.addEventListener('click', function() {
    fileInput.click();
});
</script>