<?php
// Cron Setup Guide for GreenGeeks Shared Hosting
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-clock me-2"></i>GreenGeeks Cron Setup Guide</h2>
        <p class="text-muted">Configure automated EDI file monitoring for shared hosting</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Step 1: cPanel Cron Jobs Setup</h5>
            </div>
            <div class="card-body">
                <ol>
                    <li><strong>Login to your GreenGeeks cPanel</strong></li>
                    <li><strong>Navigate to "Cron Jobs"</strong> under the "Advanced" section</li>
                    <li><strong>Add new cron job</strong> with these settings:</li>
                </ol>
                
                <div class="alert alert-success">
                    <h6>Recommended Cron Schedule</h6>
                    <table class="table table-sm mb-2">
                        <tr><td class="fw-bold">Minute:</td><td><code>*/15</code></td><td>Every 15 minutes</td></tr>
                        <tr><td class="fw-bold">Hour:</td><td><code>*</code></td><td>Every hour</td></tr>
                        <tr><td class="fw-bold">Day:</td><td><code>*</code></td><td>Every day</td></tr>
                        <tr><td class="fw-bold">Month:</td><td><code>*</code></td><td>Every month</td></tr>
                        <tr><td class="fw-bold">Weekday:</td><td><code>*</code></td><td>Every weekday</td></tr>
                    </table>
                    
                    <h6>Command:</h6>
                    <div class="form-group">
                        <input type="text" class="form-control font-monospace" 
                               value="/usr/bin/php <?= realpath(__DIR__ . '/../../cron_monitor.php') ?>" 
                               readonly onclick="this.select()">
                        <small class="text-muted">Click to select and copy</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-earmark-code me-2"></i>Step 2: Alternative Cron Schedules</h5>
            </div>
            <div class="card-body">
                <p>Choose the schedule that best fits your EDI traffic volume:</p>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Schedule</th>
                                <th>Cron Expression</th>
                                <th>Best For</th>
                                <th>Command</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Every 15 minutes</strong><br><small class="text-success">Recommended</small></td>
                                <td><code>*/15 * * * *</code></td>
                                <td>Active EDI traffic, real-time needs</td>
                                <td><small class="font-monospace">php cron_monitor.php</small></td>
                            </tr>
                            <tr>
                                <td><strong>Every 30 minutes</strong></td>
                                <td><code>*/30 * * * *</code></td>
                                <td>Moderate EDI traffic</td>
                                <td><small class="font-monospace">php cron_monitor.php</small></td>
                            </tr>
                            <tr>
                                <td><strong>Every hour</strong></td>
                                <td><code>0 * * * *</code></td>
                                <td>Light EDI traffic, batch processing</td>
                                <td><small class="font-monospace">php cron_monitor.php</small></td>
                            </tr>
                            <tr>
                                <td><strong>Business hours only</strong></td>
                                <td><code>*/15 8-17 * * 1-5</code></td>
                                <td>Weekday business hours</td>
                                <td><small class="font-monospace">php cron_monitor.php</small></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Step 3: Production Configuration</h5>
            </div>
            <div class="card-body">
                <p>Update your <code>.env</code> file with production SFTP credentials:</p>
                
                <pre class="bg-light p-3 rounded"><code># Production SFTP Settings (GreenGeeks)
SFTP_HOST=edi.nifco.com
SFTP_USERNAME=greenfield_plastics_edi
SFTP_PASSWORD=your_production_password
SFTP_REMOTE_INBOX=/greenfield/inbox
SFTP_REMOTE_OUTBOX=/greenfield/outbox

# Enable cron monitoring
FILE_MONITOR_ENABLED=true
LOG_FILE_PATH=/home/yourusername/public_html/edimodule/logs/cron_monitor.log

# Notifications (optional)
NOTIFICATIONS_ENABLED=true
NOTIFICATION_EMAIL=admin@greenfieldplastics.com</code></pre>

                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> Replace <code>yourusername</code> with your actual GreenGeeks username and update the SFTP credentials provided by Nifco.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Current Configuration</h5>
            </div>
            <div class="card-body">
                <?php
                $envFile = __DIR__ . '/../../.env';
                $cronScript = __DIR__ . '/../../cron_monitor.php';
                ?>
                
                <table class="table table-sm">
                    <tr>
                        <td class="fw-bold">.env file:</td>
                        <td>
                            <?php if (file_exists($envFile)): ?>
                                <span class="badge bg-success">✓ Found</span>
                            <?php else: ?>
                                <span class="badge bg-danger">✗ Missing</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Cron script:</td>
                        <td>
                            <?php if (file_exists($cronScript)): ?>
                                <span class="badge bg-success">✓ Ready</span>
                            <?php else: ?>
                                <span class="badge bg-danger">✗ Missing</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Script path:</td>
                        <td><small class="font-monospace"><?= realpath($cronScript) ?: 'Not found' ?></small></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Log directory:</td>
                        <td>
                            <?php 
                            $logDir = __DIR__ . '/../../logs';
                            if (is_dir($logDir) && is_writable($logDir)): ?>
                                <span class="badge bg-success">✓ Writable</span>
                            <?php else: ?>
                                <span class="badge bg-warning">⚠ Check permissions</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Pre-deployment Checklist</h5>
            </div>
            <div class="card-body">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check1">
                    <label class="form-check-label" for="check1">
                        cPanel cron job configured
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check2">
                    <label class="form-check-label" for="check2">
                        Production .env file updated
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check3">
                    <label class="form-check-label" for="check3">
                        SFTP credentials tested
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check4">
                    <label class="form-check-label" for="check4">
                        File permissions verified (755)
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check5">
                    <label class="form-check-label" for="check5">
                        Log directory writable
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check6">
                    <label class="form-check-label" for="check6">
                        Manual cron test successful
                    </label>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-terminal me-2"></i>Testing Commands</h5>
            </div>
            <div class="card-body">
                <p><strong>Manual test:</strong></p>
                <code>php cron_monitor.php</code>
                
                <p class="mt-3"><strong>View logs:</strong></p>
                <code>tail -f logs/cron_monitor.log</code>
                
                <p class="mt-3"><strong>Check file permissions:</strong></p>
                <code>ls -la cron_monitor.php</code>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-question-circle me-2"></i>Troubleshooting</h5>
            </div>
            <div class="card-body">
                <p><strong>Common issues:</strong></p>
                <ul class="list-unstyled">
                    <li><i class="bi bi-dot text-primary"></i> <strong>Permission denied:</strong> Check file permissions (755)</li>
                    <li><i class="bi bi-dot text-primary"></i> <strong>Path not found:</strong> Use absolute paths in cron</li>
                    <li><i class="bi bi-dot text-primary"></i> <strong>Memory errors:</strong> PHP memory limits on shared hosting</li>
                    <li><i class="bi bi-dot text-primary"></i> <strong>SFTP timeout:</strong> Network restrictions</li>
                </ul>
                
                <p class="mt-3"><strong>Getting help:</strong></p>
                <p>Check the cron monitor log file for detailed error messages and execution history.</p>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h6><i class="bi bi-lightbulb me-2"></i>Next Steps</h6>
            <p class="mb-0">After setting up the cron job, monitor the first few executions through the log files. The system will automatically download EDI 862 files from Nifco, process them into the database, and generate 856 ship notices as needed.</p>
        </div>
    </div>
</div>