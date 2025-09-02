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
                <a class="nav-link <?= $page == 'import' ? 'active' : '' ?>" href="?page=import">
                    <i class="bi bi-upload me-1"></i>Import Data
                </a>
                <a class="nav-link <?= $page == 'schedules' ? 'active' : '' ?>" href="?page=schedules">
                    <i class="bi bi-calendar me-1"></i>Schedules
                </a>
                <a class="nav-link <?= $page == 'transactions' ? 'active' : '' ?>" href="?page=transactions">
                    <i class="bi bi-file-text me-1"></i>Transactions
                </a>
                <a class="nav-link <?= $page == 'sftp' ? 'active' : '' ?>" href="?page=sftp">
                    <i class="bi bi-cloud-arrow-down me-1"></i>SFTP
                </a>
                <a class="nav-link <?= $page == 'part_master' ? 'active' : '' ?>" href="?page=part_master">
                    <i class="bi bi-gear me-1"></i>Part Master
                </a>
                <a class="nav-link <?= $page == 'delivery_matrix' ? 'active' : '' ?>" href="?page=delivery_matrix">
                    <i class="bi bi-table me-1"></i>Delivery Matrix
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
            case 'import':
                include '../templates/import.php';
                break;
            case 'schedules':
                include '../templates/schedules.php';
                break;
            case 'transactions':
                include '../templates/transactions.php';
                break;
            case 'sftp':
                include '../templates/sftp.php';
                break;
            case 'part_master':
                include '../templates/part_master.php';
                break;
            case 'delivery_matrix':
                include '../templates/delivery_matrix.php';
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