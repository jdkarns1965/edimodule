<?php
// Customer Configuration Management Interface
$selectedCustomer = $_GET['customer'] ?? '';
$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = $db->getConnection();
        
        if (isset($_POST['update_config'])) {
            // Update customer configuration
            $customerId = $_POST['customer_id'];
            
            $updateSql = "UPDATE trading_partners SET
                edi_standard = ?,
                edi_version = ?,
                date_format = ?,
                po_number_format = ?,
                default_organization = ?,
                default_supplier = ?,
                default_uom = ?,
                field_mappings = ?,
                business_rules = ?,
                communication_config = ?,
                template_config = ?
            WHERE id = ?";
            
            $fieldMappings = json_encode([
                'po_number_field' => $_POST['po_number_field'],
                'supplier_item_field' => $_POST['supplier_item_field'],
                'customer_item_field' => $_POST['customer_item_field'],
                'description_field' => $_POST['description_field'],
                'quantity_field' => $_POST['quantity_field'],
                'promised_date_field' => $_POST['promised_date_field'],
                'ship_to_field' => $_POST['ship_to_field'],
                'uom_field' => $_POST['uom_field'],
                'organization_field' => $_POST['organization_field'],
                'supplier_field' => $_POST['supplier_field']
            ]);
            
            $businessRules = json_encode([
                'po_parsing_rule' => $_POST['po_parsing_rule'],
                'date_parsing_formats' => explode(',', $_POST['date_parsing_formats']),
                'location_mapping_type' => $_POST['location_mapping_type'],
                'container_calculation_rule' => $_POST['container_calculation_rule'],
                'default_lead_time_days' => (int)$_POST['default_lead_time_days']
            ]);
            
            $communicationConfig = json_encode([
                'protocol' => $_POST['protocol'],
                'host' => $_POST['host'],
                'port' => (int)$_POST['port'],
                'username' => $_POST['username'],
                'inbox_path' => $_POST['inbox_path'],
                'outbox_path' => $_POST['outbox_path'],
                'file_naming_convention' => $_POST['file_naming_convention'],
                'retry_attempts' => (int)$_POST['retry_attempts'],
                'timeout_seconds' => (int)$_POST['timeout_seconds']
            ]);
            
            $templateConfig = json_encode([
                'import_template_name' => $_POST['import_template_name'],
                'export_template_name' => $_POST['export_template_name'],
                'required_headers' => explode(',', $_POST['required_headers']),
                'optional_headers' => explode(',', $_POST['optional_headers'])
            ]);
            
            $stmt = $conn->prepare($updateSql);
            $stmt->execute([
                $_POST['edi_standard'],
                $_POST['edi_version'],
                $_POST['date_format'],
                $_POST['po_number_format'],
                $_POST['default_organization'],
                $_POST['default_supplier'],
                $_POST['default_uom'],
                $fieldMappings,
                $businessRules,
                $communicationConfig,
                $templateConfig,
                $customerId
            ]);
            
            $success = "Customer configuration updated successfully!";
        }
        
    } catch (Exception $e) {
        $error = "Error updating configuration: " . $e->getMessage();
    }
}

// Get all customers for dropdown
try {
    $conn = $db->getConnection();
    
    $customers = $conn->query("
        SELECT id, partner_code, name, edi_standard, edi_version, status
        FROM trading_partners 
        ORDER BY partner_code
    ")->fetchAll();
    
    // Get selected customer details if specified
    $customerDetails = null;
    if ($selectedCustomer && $action === 'edit') {
        $stmt = $conn->prepare("
            SELECT * FROM trading_partners 
            WHERE partner_code = ? OR id = ?
        ");
        $stmt->execute([$selectedCustomer, $selectedCustomer]);
        $customerDetails = $stmt->fetch();
    }
    
} catch (Exception $e) {
    $customers = [];
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-gear me-2"></i>Customer EDI Configuration</h2>
        <p class="text-muted">Manage EDI specifications and settings for each trading partner</p>
    </div>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <!-- Customer Selection -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Trading Partners</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($customers as $customer): ?>
                    <a href="?page=customer_config&customer=<?= $customer['id'] ?>&action=edit" 
                       class="list-group-item list-group-item-action <?= $selectedCustomer == $customer['id'] ? 'active' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($customer['partner_code']) ?></h6>
                                <p class="mb-1 small"><?= htmlspecialchars($customer['name']) ?></p>
                                <small class="text-muted">
                                    <?= $customer['edi_standard'] ?> v<?= $customer['edi_version'] ?>
                                </small>
                            </div>
                            <span class="badge bg-<?= $customer['status'] === 'active' ? 'success' : ($customer['status'] === 'testing' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst($customer['status']) ?>
                            </span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="testConnection()">
                        <i class="bi bi-wifi me-2"></i>Test Connection
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="validateConfig()">
                        <i class="bi bi-check2-square me-2"></i>Validate Config
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="exportConfig()">
                        <i class="bi bi-download me-2"></i>Export Config
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if ($customerDetails): ?>
        <!-- Configuration Form -->
        <form method="POST">
            <input type="hidden" name="customer_id" value="<?= $customerDetails['id'] ?>">
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-gear me-2"></i>
                        EDI Configuration - <?= htmlspecialchars($customerDetails['partner_code']) ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">EDI Standard</label>
                                <select name="edi_standard" class="form-select">
                                    <option value="X12" <?= $customerDetails['edi_standard'] === 'X12' ? 'selected' : '' ?>>X12</option>
                                    <option value="EDIFACT" <?= $customerDetails['edi_standard'] === 'EDIFACT' ? 'selected' : '' ?>>EDIFACT</option>
                                    <option value="TRADACOMS" <?= $customerDetails['edi_standard'] === 'TRADACOMS' ? 'selected' : '' ?>>TRADACOMS</option>
                                    <option value="CUSTOM" <?= $customerDetails['edi_standard'] === 'CUSTOM' ? 'selected' : '' ?>>Custom</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">EDI Version</label>
                                <input type="text" name="edi_version" class="form-control" 
                                       value="<?= htmlspecialchars($customerDetails['edi_version']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Date Format</label>
                                <input type="text" name="date_format" class="form-control" 
                                       value="<?= htmlspecialchars($customerDetails['date_format']) ?>"
                                       placeholder="MM/DD/YYYY">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">PO Number Format</label>
                                <input type="text" name="po_number_format" class="form-control" 
                                       value="<?= htmlspecialchars($customerDetails['po_number_format']) ?>"
                                       placeholder="NNNNNN-NNN">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Default Organization</label>
                                <input type="text" name="default_organization" class="form-control" 
                                       value="<?= htmlspecialchars($customerDetails['default_organization']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Default UOM</label>
                                <input type="text" name="default_uom" class="form-control" 
                                       value="<?= htmlspecialchars($customerDetails['default_uom']) ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Default Supplier</label>
                                <input type="text" name="default_supplier" class="form-control" 
                                       value="<?= htmlspecialchars($customerDetails['default_supplier']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Field Mappings -->
            <?php 
            $fieldMappings = json_decode($customerDetails['field_mappings'], true) ?? [];
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Field Mappings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">PO Number Field</label>
                                <input type="text" name="po_number_field" class="form-control" 
                                       value="<?= htmlspecialchars($fieldMappings['po_number_field'] ?? 'PO Number') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Supplier Item Field</label>
                                <input type="text" name="supplier_item_field" class="form-control" 
                                       value="<?= htmlspecialchars($fieldMappings['supplier_item_field'] ?? 'Supplier Item') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Customer Item Field</label>
                                <input type="text" name="customer_item_field" class="form-control" 
                                       value="<?= htmlspecialchars($fieldMappings['customer_item_field'] ?? 'Item Number') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Description Field</label>
                                <input type="text" name="description_field" class="form-control" 
                                       value="<?= htmlspecialchars($fieldMappings['description_field'] ?? 'Item Description') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Quantity Field</label>
                                <input type="text" name="quantity_field" class="form-control" 
                                       value="<?= htmlspecialchars($fieldMappings['quantity_field'] ?? 'Quantity Ordered') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Promised Date Field</label>
                                <input type="text" name="promised_date_field" class="form-control" 
                                       value="<?= htmlspecialchars($fieldMappings['promised_date_field'] ?? 'Promised Date') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ship-To Field</label>
                                <input type="text" name="ship_to_field" class="form-control" 
                                       value="<?= htmlspecialchars($fieldMappings['ship_to_field'] ?? 'Ship-To Location') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">UOM Field</label>
                                <input type="text" name="uom_field" class="form-control" 
                                       value="<?= htmlspecialchars($fieldMappings['uom_field'] ?? 'UOM') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Organization Field</label>
                                <input type="text" name="organization_field" class="form-control" 
                                       value="<?= htmlspecialchars($fieldMappings['organization_field'] ?? 'Organization') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Supplier Field</label>
                                <input type="text" name="supplier_field" class="form-control" 
                                       value="<?= htmlspecialchars($fieldMappings['supplier_field'] ?? 'Supplier') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Business Rules -->
            <?php 
            $businessRules = json_decode($customerDetails['business_rules'], true) ?? [];
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-gear-wide-connected me-2"></i>Business Rules</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">PO Parsing Rule</label>
                                <select name="po_parsing_rule" class="form-select">
                                    <option value="split_on_dash" <?= ($businessRules['po_parsing_rule'] ?? '') === 'split_on_dash' ? 'selected' : '' ?>>Split on Dash</option>
                                    <option value="no_split" <?= ($businessRules['po_parsing_rule'] ?? '') === 'no_split' ? 'selected' : '' ?>>No Split</option>
                                    <option value="split_on_period" <?= ($businessRules['po_parsing_rule'] ?? '') === 'split_on_period' ? 'selected' : '' ?>>Split on Period</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Location Mapping Type</label>
                                <select name="location_mapping_type" class="form-select">
                                    <option value="description_based" <?= ($businessRules['location_mapping_type'] ?? '') === 'description_based' ? 'selected' : '' ?>>Description Based</option>
                                    <option value="code_based" <?= ($businessRules['location_mapping_type'] ?? '') === 'code_based' ? 'selected' : '' ?>>Code Based</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Container Calculation Rule</label>
                                <select name="container_calculation_rule" class="form-select">
                                    <option value="round_up" <?= ($businessRules['container_calculation_rule'] ?? '') === 'round_up' ? 'selected' : '' ?>>Round Up</option>
                                    <option value="exact" <?= ($businessRules['container_calculation_rule'] ?? '') === 'exact' ? 'selected' : '' ?>>Exact</option>
                                    <option value="round_nearest" <?= ($businessRules['container_calculation_rule'] ?? '') === 'round_nearest' ? 'selected' : '' ?>>Round Nearest</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Default Lead Time (Days)</label>
                                <input type="number" name="default_lead_time_days" class="form-control" 
                                       value="<?= $businessRules['default_lead_time_days'] ?? 0 ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Date Parsing Formats (comma separated)</label>
                                <input type="text" name="date_parsing_formats" class="form-control" 
                                       value="<?= htmlspecialchars(implode(',', $businessRules['date_parsing_formats'] ?? ['M/D/YYYY', 'MM/DD/YYYY'])) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Communication Config -->
            <?php 
            $commConfig = json_decode($customerDetails['communication_config'], true) ?? [];
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-wifi me-2"></i>Communication Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Protocol</label>
                                <select name="protocol" class="form-select">
                                    <option value="SFTP" <?= ($commConfig['protocol'] ?? '') === 'SFTP' ? 'selected' : '' ?>>SFTP</option>
                                    <option value="AS2" <?= ($commConfig['protocol'] ?? '') === 'AS2' ? 'selected' : '' ?>>AS2</option>
                                    <option value="FTP" <?= ($commConfig['protocol'] ?? '') === 'FTP' ? 'selected' : '' ?>>FTP</option>
                                    <option value="API" <?= ($commConfig['protocol'] ?? '') === 'API' ? 'selected' : '' ?>>API</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Host</label>
                                <input type="text" name="host" class="form-control" 
                                       value="<?= htmlspecialchars($commConfig['host'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Port</label>
                                <input type="number" name="port" class="form-control" 
                                       value="<?= $commConfig['port'] ?? 22 ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" 
                                       value="<?= htmlspecialchars($commConfig['username'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Inbox Path</label>
                                <input type="text" name="inbox_path" class="form-control" 
                                       value="<?= htmlspecialchars($commConfig['inbox_path'] ?? '/inbox') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Outbox Path</label>
                                <input type="text" name="outbox_path" class="form-control" 
                                       value="<?= htmlspecialchars($commConfig['outbox_path'] ?? '/outbox') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Retry Attempts</label>
                                <input type="number" name="retry_attempts" class="form-control" 
                                       value="<?= $commConfig['retry_attempts'] ?? 3 ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Timeout (seconds)</label>
                                <input type="number" name="timeout_seconds" class="form-control" 
                                       value="<?= $commConfig['timeout_seconds'] ?? 30 ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">File Naming Convention</label>
                                <input type="text" name="file_naming_convention" class="form-control" 
                                       value="<?= htmlspecialchars($commConfig['file_naming_convention'] ?? 'EDI_{YYYYMMDD}_{HHMMSS}.edi') ?>"
                                       placeholder="EDI_{YYYYMMDD}_{HHMMSS}.edi">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Template Config -->
            <?php 
            $templateConfig = json_decode($customerDetails['template_config'], true) ?? [];
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Template Configuration</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Import Template Name</label>
                                <input type="text" name="import_template_name" class="form-control" 
                                       value="<?= htmlspecialchars($templateConfig['import_template_name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Export Template Name</label>
                                <input type="text" name="export_template_name" class="form-control" 
                                       value="<?= htmlspecialchars($templateConfig['export_template_name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Required Headers (comma separated)</label>
                                <textarea name="required_headers" class="form-control" rows="2"><?= htmlspecialchars(implode(',', $templateConfig['required_headers'] ?? [])) ?></textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Optional Headers (comma separated)</label>
                                <textarea name="optional_headers" class="form-control" rows="2"><?= htmlspecialchars(implode(',', $templateConfig['optional_headers'] ?? [])) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="?page=customer_config" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to List
                </a>
                <button type="submit" name="update_config" class="btn btn-primary">
                    <i class="bi bi-check2 me-2"></i>Update Configuration
                </button>
            </div>
        </form>
        
        <?php else: ?>
        <!-- No Customer Selected -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-gear display-1 text-muted mb-4"></i>
                <h4>Select a Trading Partner</h4>
                <p class="text-muted">Choose a trading partner from the list to view and edit their EDI configuration.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function testConnection() {
    alert('Connection test functionality coming soon!');
}

function validateConfig() {
    alert('Configuration validation functionality coming soon!');
}

function exportConfig() {
    alert('Configuration export functionality coming soon!');
}
</script>