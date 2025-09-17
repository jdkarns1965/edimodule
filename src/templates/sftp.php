<?php
use Greenfield\EDI\SFTPClient;
use Greenfield\EDI\EDIFileMonitor;

$sftpClient = new SFTPClient();
$fileMonitor = new EDIFileMonitor();

$action = $_POST['action'] ?? null;
$message = '';
$messageType = '';

if ($action) {
    switch ($action) {
        case 'test_connection':
            $result = $sftpClient->testConnection();
            if ($result['success']) {
                $message = 'SFTP connection successful! Connected to ' . $result['server'] . ' as ' . $result['username'];
                $messageType = 'success';
            } else {
                $message = 'SFTP connection failed: ' . $result['error'];
                $messageType = 'danger';
            }
            break;
            
        case 'download_files':
            $result = $sftpClient->downloadFiles();
            if ($result['success']) {
                $message = "Downloaded {$result['count']} files: " . implode(', ', $result['downloaded']);
                $messageType = 'success';
                if (!empty($result['errors'])) {
                    $message .= '<br>Warnings: ' . implode(', ', $result['errors']);
                }
            } else {
                $message = 'Download failed: ' . $result['error'];
                $messageType = 'danger';
            }
            break;
            
        case 'process_files':
            $result = $fileMonitor->runSingleCycle();
            if ($result['success']) {
                $processed = $result['process_result']['processed'] ?? 0;
                $downloaded = $result['download_result']['count'] ?? 0;
                $message = "Cycle completed! Downloaded: $downloaded files, Processed: $processed files";
                $messageType = 'success';
                
                if (!empty($result['process_result']['errors'])) {
                    $message .= '<br>Errors: ' . implode(', ', $result['process_result']['errors']);
                    $messageType = 'warning';
                }
            } else {
                $message = 'Processing failed: ' . $result['error'];
                $messageType = 'danger';
            }
            break;
            
        case 'test_cron':
            // Test cron monitor script
            $cronScript = __DIR__ . '/../../cron_monitor.php';
            if (file_exists($cronScript)) {
                $output = [];
                $returnCode = 0;
                exec("php $cronScript 2>&1", $output, $returnCode);
                
                if ($returnCode === 0) {
                    $message = "Cron monitor test successful!<br>" . implode('<br>', array_slice($output, -5));
                    $messageType = 'success';
                } else {
                    $message = "Cron monitor test failed!<br>" . implode('<br>', array_slice($output, -5));
                    $messageType = 'danger';
                }
            } else {
                $message = 'Cron monitor script not found';
                $messageType = 'danger';
            }
            break;
    }
}

$connectionTest = $sftpClient->testConnection();
$monitorStatus = $fileMonitor->getStatus();
$inboxFiles = $fileMonitor->listInboxFiles();
$recentLogs = $fileMonitor->getRecentLogs(20);
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-cloud-arrow-down me-2"></i>SFTP Integration Dashboard</h2>
        <p class="text-muted">Monitor and control SFTP file operations and EDI processing</p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card h-100 <?= $connectionTest['success'] ? 'success' : 'danger' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">SFTP Status</h6>
                        <h5 class="card-title mb-0"><?= $connectionTest['success'] ? 'Connected' : 'Disconnected' ?></h5>
                        <small class="text-muted"><?= $sftpClient->getConfig()['host'] ?>:<?= $sftpClient->getConfig()['port'] ?></small>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-<?= $connectionTest['success'] ? 'wifi text-success' : 'wifi-off text-danger' ?>" style="font-size: 2rem;"></i>
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
                        <h6 class="card-subtitle mb-2 text-muted">Monitor Status</h6>
                        <h5 class="card-title mb-0"><?= $monitorStatus['config']['enabled'] ? 'Enabled' : 'Disabled' ?></h5>
                        <small class="text-muted">Check every <?= $monitorStatus['config']['interval'] ?>s</small>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-<?= $monitorStatus['config']['enabled'] ? 'play-circle text-warning' : 'pause-circle text-muted' ?>" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Local Files</h6>
                        <h2 class="card-title mb-0"><?= count($inboxFiles['local']) ?></h2>
                        <small class="text-muted">In inbox</small>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-files text-primary" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Remote Files</h6>
                        <h2 class="card-title mb-0"><?= $inboxFiles['remote']['success'] ? $inboxFiles['remote']['count'] : '?' ?></h2>
                        <small class="text-muted">On SFTP server</small>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-cloud-fill text-info" style="font-size: 2rem;"></i>
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
                <h5 class="mb-0"><i class="bi bi-terminal me-2"></i>SFTP Operations</h5>
                <small class="text-muted">Manual file operations and testing</small>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <form method="POST" class="d-grid">
                            <input type="hidden" name="action" value="test_connection">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-wifi me-2"></i>Test Connection
                            </button>
                        </form>
                    </div>
                    <div class="col-md-4 mb-3">
                        <form method="POST" class="d-grid">
                            <input type="hidden" name="action" value="download_files">
                            <button type="submit" class="btn btn-outline-success" <?= !$connectionTest['success'] ? 'disabled' : '' ?>>
                                <i class="bi bi-cloud-download me-2"></i>Download Files
                            </button>
                        </form>
                    </div>
                    <div class="col-md-4 mb-3">
                        <form method="POST" class="d-grid">
                            <input type="hidden" name="action" value="process_files">
                            <button type="submit" class="btn btn-outline-warning">
                                <i class="bi bi-gear me-2"></i>Run Processing Cycle
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- GreenGeeks Cron Monitor Controls -->
                <div class="alert alert-info">
                    <h6><i class="bi bi-clock me-2"></i>GreenGeeks Cron Monitor</h6>
                    <p class="mb-2">For production deployment, use cron-based monitoring (15-minute intervals).</p>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <form method="POST" class="d-grid">
                                <input type="hidden" name="action" value="test_cron">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-stopwatch me-2"></i>Test Cron Monitor
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6 mb-2">
                            <a href="?page=cron_setup" class="btn btn-sm btn-outline-info d-grid">
                                <i class="bi bi-gear-wide me-2"></i>Cron Setup Guide
                            </a>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="bi bi-inbox me-2"></i>Local Inbox Files</h6>
                        <?php if (empty($inboxFiles['local'])): ?>
                            <p class="text-muted">No files in local inbox</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($inboxFiles['local'] as $file): ?>
                                <li class="list-group-item px-0 py-1">
                                    <i class="bi bi-file-earmark-text me-2"></i><?= htmlspecialchars($file) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-cloud me-2"></i>Remote SFTP Files</h6>
                        <?php if (!$inboxFiles['remote']['success']): ?>
                            <p class="text-muted text-danger">Error: <?= htmlspecialchars($inboxFiles['remote']['error']) ?></p>
                        <?php elseif (empty($inboxFiles['remote']['files'])): ?>
                            <p class="text-muted">No files on SFTP server</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($inboxFiles['remote']['files'] as $file): ?>
                                <li class="list-group-item px-0 py-1 d-flex justify-content-between">
                                    <span><i class="bi bi-file-earmark-text me-2"></i><?= htmlspecialchars($file['name']) ?></span>
                                    <small class="text-muted"><?= number_format($file['size']) ?> bytes</small>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Recent Activity Log</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($recentLogs)): ?>
                    <p class="text-muted">No recent activity logged</p>
                <?php else: ?>
                    <pre class="bg-light p-3 rounded" style="font-size: 0.875rem;"><?php
                    foreach ($recentLogs as $log) {
                        echo htmlspecialchars($log) . "\n";
                    }
                    ?></pre>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Configuration</h5>
            </div>
            <div class="card-body">
                <?php $config = $sftpClient->getConfig(); ?>
                <table class="table table-sm">
                    <tr>
                        <td class="fw-bold">SFTP Host:</td>
                        <td><code><?= htmlspecialchars($config['host']) ?>:<?= $config['port'] ?></code></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Username:</td>
                        <td><code><?= htmlspecialchars($config['username']) ?></code></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Timeout:</td>
                        <td><?= $config['timeout'] ?> seconds</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Inbox Path:</td>
                        <td><small><?= htmlspecialchars($config['inbox_path']) ?></small></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Outbox Path:</td>
                        <td><small><?= htmlspecialchars($config['outbox_path']) ?></small></td>
                    </tr>
                </table>
                
                <hr>
                
                <h6><i class="bi bi-gear me-2"></i>Monitor Settings</h6>
                <table class="table table-sm">
                    <tr>
                        <td class="fw-bold">Enabled:</td>
                        <td>
                            <span class="badge bg-<?= $monitorStatus['config']['enabled'] ? 'success' : 'secondary' ?>">
                                <?= $monitorStatus['config']['enabled'] ? 'Yes' : 'No' ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Interval:</td>
                        <td><?= $monitorStatus['config']['interval'] ?> seconds</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Last Check:</td>
                        <td><small><?= $monitorStatus['last_check'] ?></small></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-question-circle me-2"></i>Quick Help</h5>
            </div>
            <div class="card-body">
                <p><strong>Test Connection:</strong> Verify SFTP server connectivity</p>
                <p><strong>Download Files:</strong> Get new 862 EDI files from remote server</p>
                <p><strong>Run Processing Cycle:</strong> Download + process files + update database</p>
                <hr>
                <p class="text-muted"><small>Files are automatically moved to processed/error folders after processing.</small></p>
            </div>
        </div>
    </div>
</div>

<script>
function refreshStatus() {
    window.location.reload();
}

setInterval(function() {
    // Auto-refresh every 30 seconds
    refreshStatus();
}, 30000);
</script>