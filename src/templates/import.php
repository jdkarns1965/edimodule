<?php
$importResult = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['import_sample'])) {
            $sampleFile = dirname(dirname(__DIR__)) . '/sample_data.tsv';
            if (file_exists($sampleFile)) {
                $importer = new TSVImporter();
                $importResult = $importer->importFromFile($sampleFile);
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
                
                $importer = new TSVImporter();
                $importResult = $importer->importFromFile($uploadedFile);
                
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
    $recentImports = $conn->query("
        SELECT et.*, tp.name as partner_name
        FROM edi_transactions et
        LEFT JOIN trading_partners tp ON et.partner_id = tp.id
        WHERE et.transaction_type = 'TSV'
        ORDER BY et.created_at DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $recentImports = [];
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
                    <div class="upload-area mb-3" id="uploadArea">
                        <i class="bi bi-cloud-upload display-1 text-muted"></i>
                        <h5 class="mt-3">Drop your TSV or CSV file here or click to browse</h5>
                        <p class="text-muted">Supported formats: Tab-separated (.tsv) or Comma-separated (.csv)</p>
                        <input type="file" class="form-control d-none" id="tsv_file" name="tsv_file" accept=".tsv,.csv" required>
                        <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('tsv_file').click()">
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
                        <button type="submit" name="import_sample" class="btn btn-outline-secondary">
                            <i class="bi bi-play-circle me-2"></i>Import Sample Data
                        </button>
                    </form>
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
document.getElementById('tsv_file').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('selectedFile').classList.remove('d-none');
        document.getElementById('uploadBtn').disabled = false;
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