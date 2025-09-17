<?php
require_once '../../bootstrap.php';
require_once '../config/database.php';
require_once '../config/app.php';
require_once '../classes/TSVImporter.php';

$db = DatabaseConfig::getInstance();
$page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= AppConfig::APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .navbar-brand { font-weight: bold; }
        .stats-card { border-left: 4px solid #007bff; }
        .stats-card.success { border-left-color: #28a745; }
        .stats-card.warning { border-left-color: #ffc107; }
        .stats-card.danger { border-left-color: #dc3545; }
        .upload-area { 
            border: 2px dashed #dee2e6; 
            padding: 2rem; 
            text-align: center; 
            border-radius: 0.375rem; 
        }
        .upload-area:hover { border-color: #007bff; }
        .table-responsive { max-height: 500px; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="?page=dashboard">
                <i class="bi bi-arrow-left-right me-2"></i><?= AppConfig::APP_NAME ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link <?= $page == 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                </a>
                
                <!-- ERP Management Section -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($page, ['customers', 'inventory', 'build_shipment']) ? 'active' : '' ?>" 
                       href="#" id="erpDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-building me-1"></i>ERP Management
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?= $page == 'customers' ? 'active' : '' ?>" href="?page=customers">
                            <i class="bi bi-people me-1"></i>Customer Management</a></li>
                        <li><a class="dropdown-item <?= $page == 'inventory' ? 'active' : '' ?>" href="?page=inventory">
                            <i class="bi bi-boxes me-1"></i>Inventory Management</a></li>
                        <li><a class="dropdown-item <?= $page == 'build_shipment' ? 'active' : '' ?>" href="?page=build_shipment">
                            <i class="bi bi-truck me-1"></i>Shipment Builder</a></li>
                    </ul>
                </div>
                
                <!-- EDI Processing Section -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($page, ['schedules', 'transactions', 'import', 'part_master', 'delivery_matrix', 'customer_config']) ? 'active' : '' ?>" 
                       href="#" id="ediDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-arrow-left-right me-1"></i>EDI Processing
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?= $page == 'schedules' ? 'active' : '' ?>" href="?page=schedules">
                            <i class="bi bi-calendar me-1"></i>Delivery Schedules</a></li>
                        <li><a class="dropdown-item <?= $page == 'import' ? 'active' : '' ?>" href="?page=import">
                            <i class="bi bi-upload me-1"></i>Import Data</a></li>
                        <li><a class="dropdown-item <?= $page == 'part_master' ? 'active' : '' ?>" href="?page=part_master">
                            <i class="bi bi-gear me-1"></i>Part Master</a></li>
                        <li><a class="dropdown-item <?= $page == 'delivery_matrix' ? 'active' : '' ?>" href="?page=delivery_matrix">
                            <i class="bi bi-table me-1"></i>Delivery Matrix</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item <?= $page == 'customer_config' ? 'active' : '' ?>" href="?page=customer_config">
                            <i class="bi bi-gear-wide-connected me-1"></i>Customer EDI Config</a></li>
                        <li><a class="dropdown-item <?= $page == 'transactions' ? 'active' : '' ?>" href="?page=transactions">
                            <i class="bi bi-file-text me-1"></i>EDI Transactions</a></li>
                    </ul>
                </div>
                
                <a class="nav-link <?= $page == 'sftp' ? 'active' : '' ?>" href="?page=sftp">
                    <i class="bi bi-cloud-arrow-down me-1"></i>SFTP
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php
        switch($page) {
            case 'dashboard':
                include '../templates/dashboard.php';
                break;
            
            // ERP Management Pages
            case 'customers':
                include '../templates/customers.php';
                break;
            case 'inventory':
                include '../templates/inventory.php';
                break;
            case 'build_shipment':
                include '../templates/build_shipment.php';
                break;
                
            // EDI Processing Pages
            case 'schedules':
                include '../templates/schedules.php';
                break;
            case 'import':
                include '../templates/import.php';
                break;
            case 'transactions':
                include '../templates/transactions.php';
                break;
            case 'part_master':
                include '../templates/part_master.php';
                break;
            case 'delivery_matrix':
                include '../templates/delivery_matrix.php';
                break;
            case 'customer_config':
                include '../templates/customer_config.php';
                break;
                
            // System Pages
            case 'sftp':
                include '../templates/sftp.php';
                break;
            case 'cron_setup':
                include '../templates/cron_setup.php';
                break;
                
            default:
                include '../templates/dashboard.php';
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function refreshPage() {
            window.location.reload();
        }
        
        function confirmAction(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }
    </script>
</body>
</html>