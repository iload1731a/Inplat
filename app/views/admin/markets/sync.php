<?php declare(strict_types=1); ?>
<?php
$provider = is_array($provider ?? null) ? $provider : null;
$stats = is_array($stats ?? null) ? $stats : [];
$syncStats = is_array($syncStats ?? null) ? $syncStats : [];
$recentJobs = is_array($recentJobs ?? null) ? $recentJobs : [];
$pairs = is_array($pairs ?? null) ? $pairs : [];
$csrf = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Market Data Sync</h1>
        <p class="text-secondary mb-0">Binance exchange sync, ticker ingestion, and candlestick updates.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/markets" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Overview</a>
        <a href="/admin/markets/providers" class="btn btn-outline-info btn-sm"><i class="fas fa-server me-1"></i>Providers</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Provider</div><div class="h6 mb-0"><?= e((string)($provider['name'] ?? 'Not Configured')) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Feed Pairs</div><div class="h5 mb-0 text-info"><?= count($pairs) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">24H Sync Success</div><div class="h5 mb-0 text-success"><?= number_format((int)($syncStats['successes'] ?? 0)) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">24H Sync Failures</div><div class="h5 mb-0 text-danger"><?= number_format((int)($syncStats['failures'] ?? 0)) ?></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Exchange & Pair Sync</h2>
            <form data-ajax="true" action="/admin/markets/sync/exchange-info" method="post">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-download me-1"></i>Import / Update Binance Symbols
                </button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="glass rounded-4 p-3 h-100">
            <h2 class="h6 mb-3">Live Ticker Sync</h2>
            <form data-ajax="true" action="/admin/markets/sync/tickers" method="post">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-bolt me-1"></i>Sync Tickers From Binance
                </button>
            </form>
        </div>
    </div>
</div>

<div class="glass rounded-4 p-3 mb-4">
    <h2 class="h6 mb-3">Candlestick Sync</h2>
    <form class="row g-2" data-ajax="true" action="/admin/markets/sync/candles" method="post">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <div class="col-md-3">
            <select class="form-select" name="interval">
                <?php foreach (['1m','5m','15m','30m','1h','4h','1d'] as $iv): ?>
                <option value="<?= e($iv) ?>" <?= $iv === '1h' ? 'selected' : '' ?>><?= e($iv) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input class="form-control" type="number" name="limit" min="50" max="1000" value="300">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-warning w-100"><i class="fas fa-chart-bar me-1"></i>Sync Candles</button>
        </div>
    </form>
</div>

<div class="glass rounded-4 p-3 mb-4">
    <h2 class="h6 mb-3">Recent Pair Import Jobs</h2>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr><th>ID</th><th>Status</th><th>Found</th><th>Created</th><th>Updated</th><th>Skipped</th><th>Started</th><th>Completed</th></tr></thead>
            <tbody>
            <?php foreach ($recentJobs as $job): ?>
            <tr>
                <td>#<?= (int)$job['id'] ?></td>
                <td><span class="badge text-bg-<?= ($job['status'] ?? '') === 'completed' ? 'success' : (($job['status'] ?? '') === 'failed' ? 'danger' : 'warning') ?>"><?= e((string)$job['status']) ?></span></td>
                <td><?= number_format((int)($job['pairs_found'] ?? 0)) ?></td>
                <td><?= number_format((int)($job['pairs_created'] ?? 0)) ?></td>
                <td><?= number_format((int)($job['pairs_updated'] ?? 0)) ?></td>
                <td><?= number_format((int)($job['pairs_skipped'] ?? 0)) ?></td>
                <td class="small text-secondary"><?= e((string)($job['started_at'] ?? '')) ?></td>
                <td class="small text-secondary"><?= e((string)($job['completed_at'] ?? '—')) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$recentJobs): ?><tr><td colspan="8" class="text-center text-secondary">No import jobs yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
