<?php declare(strict_types=1); ?>
<?php
$phpVersion    = (string)($phpVersion    ?? PHP_VERSION);
$phpSapi       = (string)($phpSapi       ?? PHP_SAPI);
$osInfo        = (string)($osInfo        ?? '');
$serverSoftware= (string)($serverSoftware?? 'N/A');
$mysqlVersion  = (string)($mysqlVersion  ?? 'unknown');
$extensions    = is_array($extensions    ?? null) ? $extensions : [];
$stats         = is_array($stats         ?? null) ? $stats : [];
$tableList     = is_array($tableList     ?? null) ? $tableList : [];
$memoryLimit   = (string)($memoryLimit   ?? ini_get('memory_limit'));
$maxExecTime   = (string)($maxExecTime   ?? ini_get('max_execution_time'));
$uploadMaxSize = (string)($uploadMaxSize ?? ini_get('upload_max_filesize'));
$postMaxSize   = (string)($postMaxSize   ?? ini_get('post_max_size'));
$timezone      = (string)($timezone      ?? date_default_timezone_get());
$diskFree      = (int)($diskFreeBytes    ?? 0);
$diskTotal     = (int)($diskTotalBytes   ?? 0);
$diskUsed      = $diskTotal - $diskFree;
$diskPct       = $diskTotal > 0 ? round($diskUsed / $diskTotal * 100) : 0;
$fmtSize = fn(int $b) => $b >= 1073741824 ? round($b/1073741824,1).' GB' : ($b >= 1048576 ? round($b/1048576,1).' MB' : round($b/1024,1).' KB');
sort($extensions);
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-info-circle me-2 text-secondary"></i>System Information</h1>
        <p class="text-secondary mb-0">Server environment, PHP configuration, database statistics, and installed extensions.</p>
    </div>
    <span class="badge bg-success">Live Data</span>
</div>

<div class="row g-4">
    <!-- Platform Statistics -->
    <div class="col-lg-12">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-chart-bar me-2 text-primary"></i>Platform Statistics</h6>
            <div class="row g-3">
                <?php
                $statCards = [
                    ['Total Users',          $stats['users_total'] ?? 0,          'fa-users',           'text-info'],
                    ['Active Users',         $stats['users_active'] ?? 0,          'fa-user-check',      'text-success'],
                    ['New Today',            $stats['users_today'] ?? 0,           'fa-user-plus',       'text-warning'],
                    ['Total Orders',         $stats['orders_total'] ?? 0,          'fa-list-ol',         'text-primary'],
                    ['Open Orders',          $stats['orders_open'] ?? 0,           'fa-clock',           'text-warning'],
                    ['Pending KYC',          $stats['kyc_pending'] ?? 0,           'fa-id-card',         'text-info'],
                    ['Open Tickets',         $stats['tickets_open'] ?? 0,          'fa-headset',         'text-danger'],
                    ['Pending Withdrawals',  $stats['withdrawals_pending'] ?? 0,   'fa-arrow-circle-up', 'text-warning'],
                ];
                foreach ($statCards as [$label, $value, $icon, $color]):
                ?>
                <div class="col-md-3 col-sm-6">
                    <div class="d-flex align-items-center gap-3 p-3 rounded-3 border border-secondary-subtle">
                        <i class="fas <?= e($icon) ?> fs-4 <?= e($color) ?>"></i>
                        <div>
                            <div class="fw-bold fs-5"><?= number_format((int)$value) ?></div>
                            <div class="small text-secondary"><?= e($label) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Server Environment -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3"><i class="fas fa-server me-2 text-warning"></i>Server Environment</h6>
            <table class="table table-dark table-sm mb-0">
                <tbody>
                <tr><td class="text-secondary">PHP Version</td><td><code><?= e($phpVersion) ?></code></td></tr>
                <tr><td class="text-secondary">PHP SAPI</td><td><code><?= e($phpSapi) ?></code></td></tr>
                <tr><td class="text-secondary">Web Server</td><td><?= e($serverSoftware) ?></td></tr>
                <tr><td class="text-secondary">OS</td><td class="small"><?= e(substr($osInfo, 0, 60)) ?></td></tr>
                <tr><td class="text-secondary">Timezone</td><td><code><?= e($timezone) ?></code></td></tr>
                <tr><td class="text-secondary">MySQL Version</td><td><code><?= e($mysqlVersion) ?></code></td></tr>
                <tr><td class="text-secondary">Memory Limit</td><td><code><?= e($memoryLimit) ?></code></td></tr>
                <tr><td class="text-secondary">Max Execution Time</td><td><code><?= e($maxExecTime) ?>s</code></td></tr>
                <tr><td class="text-secondary">Upload Max Size</td><td><code><?= e($uploadMaxSize) ?></code></td></tr>
                <tr><td class="text-secondary">POST Max Size</td><td><code><?= e($postMaxSize) ?></code></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Database -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3"><i class="fas fa-database me-2 text-success"></i>Database</h6>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <div class="p-3 rounded-3 border border-secondary-subtle text-center">
                        <div class="fs-4 fw-bold text-success"><?= e((string)($stats['db_size_mb'] ?? 0)) ?> MB</div>
                        <div class="small text-secondary">Database Size</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-3 border border-secondary-subtle text-center">
                        <div class="fs-4 fw-bold text-info"><?= (int)($stats['db_tables'] ?? 0) ?></div>
                        <div class="small text-secondary">Total Tables</div>
                    </div>
                </div>
            </div>

            <!-- Disk Usage -->
            <h6 class="mt-3 mb-2 text-secondary small text-uppercase">Disk Usage</h6>
            <?php if ($diskTotal > 0): ?>
                <div class="d-flex justify-content-between small text-secondary mb-1">
                    <span>Used: <?= e($fmtSize($diskUsed)) ?></span>
                    <span>Free: <?= e($fmtSize($diskFree)) ?></span>
                    <span>Total: <?= e($fmtSize($diskTotal)) ?></span>
                </div>
                <div class="progress" style="height:10px;">
                    <div class="progress-bar <?= $diskPct > 85 ? 'bg-danger' : ($diskPct > 60 ? 'bg-warning' : 'bg-success') ?>"
                         style="width:<?= $diskPct ?>%"><?= $diskPct ?>%</div>
                </div>
            <?php else: ?>
                <div class="text-secondary small">Disk info not available.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PHP Extensions -->
    <div class="col-lg-12">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-puzzle-piece me-2 text-info"></i>Loaded PHP Extensions (<?= count($extensions) ?>)</h6>
            <?php
            $required = ['pdo','pdo_mysql','mbstring','curl','json','openssl','fileinfo','gd','zip','intl'];
            $missing  = array_diff($required, array_map('strtolower', $extensions));
            ?>
            <?php if (!empty($missing)): ?>
                <div class="alert alert-warning py-2 small mb-3">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Recommended extensions not found: <strong><?= implode(', ', $missing) ?></strong>
                </div>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-1">
                <?php foreach ($extensions as $ext): ?>
                    <?php $isReq = in_array(strtolower((string)$ext), $required, true); ?>
                    <span class="badge <?= $isReq ? 'bg-primary' : 'bg-secondary' ?>" style="font-size:.7rem;">
                        <?= e((string)$ext) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($tableList)): ?>
    <!-- Table List -->
    <div class="col-lg-12">
        <div class="glass rounded-4 p-0 overflow-hidden">
            <div class="p-3 border-bottom border-secondary-subtle d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-table me-2 text-secondary"></i>Database Tables</h6>
                <span class="badge bg-secondary"><?= count($tableList) ?> tables</span>
            </div>
            <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                <table class="table table-dark table-hover table-sm align-middle mb-0 small">
                    <thead class="sticky-top" style="top:0;z-index:1;background:#1e293b;">
                        <tr><th>Table</th><th>Rows (est.)</th><th>Data Size</th><th>Index Size</th><th>Engine</th><th>Collation</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tableList as $t): ?>
                        <tr>
                            <td><code class="text-info small"><?= e((string)($t['Name'] ?? '')) ?></code></td>
                            <td><?= number_format((int)($t['Rows'] ?? 0)) ?></td>
                            <td><?= $fmtSize((int)($t['Data_length'] ?? 0)) ?></td>
                            <td><?= $fmtSize((int)($t['Index_length'] ?? 0)) ?></td>
                            <td class="text-secondary"><?= e((string)($t['Engine'] ?? '')) ?></td>
                            <td class="text-secondary small"><?= e((string)($t['Collation'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
