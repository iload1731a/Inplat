<?php declare(strict_types=1); ?>
<?php
$pairs   = is_array($pairs ?? null)   ? $pairs   : [];
$filters = is_array($filters ?? null) ? $filters : [];
$quotes  = is_array($quotes ?? null)  ? $quotes  : [];
$csrf    = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Trading Pairs</h1>
        <p class="text-secondary mb-0">All active and inactive trading pairs with live price data.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/markets" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Overview</a>
        <a href="/admin/assets/pairs" class="btn btn-outline-warning btn-sm"><i class="fas fa-plus me-1"></i>Create Pair</a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Stats -->
<?php
$total       = count($pairs);
$active      = count(array_filter($pairs, fn($p) => (int)($p['is_active'] ?? 0) === 1));
$withTicker  = count(array_filter($pairs, fn($p) => (float)($p['last_price'] ?? 0) > 0));
$totalVol    = array_sum(array_column($pairs, 'volume_24h'));
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Total</div><div class="h5 mb-0"><?= $total ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">Active</div><div class="h5 mb-0 text-success"><?= $active ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">With Ticker</div><div class="h5 mb-0 text-info"><?= $withTicker ?></div></div></div>
    <div class="col-6 col-md-3"><div class="glass rounded-4 p-3 text-center"><div class="small text-secondary">24H Volume</div><div class="h5 mb-0 text-warning">$<?= number_format($totalVol, 0) ?></div></div></div>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/markets/pairs">
        <div class="col-lg-3"><input class="form-control" type="text" name="search" placeholder="Search pair/code" value="<?= e((string)($filters['search'] ?? '')) ?>"></div>
        <div class="col-lg-2">
            <select class="form-select" name="market_type">
                <option value="">All Types</option>
                <?php foreach (['spot','margin','futures'] as $mt): ?>
                    <option value="<?= e($mt) ?>" <?= (($filters['market_type'] ?? '') === $mt) ? 'selected' : '' ?>><?= ucfirst($mt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <select class="form-select" name="quote">
                <option value="">All Quotes</option>
                <?php foreach ($quotes as $q): ?>
                    <option value="<?= e($q['code']) ?>" <?= (strtoupper($filters['quote'] ?? '') === $q['code']) ? 'selected' : '' ?>><?= e($q['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <select class="form-select" name="is_active">
                <option value="">All Status</option>
                <option value="1" <?= (($filters['is_active'] ?? '') === '1') ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= (($filters['is_active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="col-lg-2">
            <select class="form-select" name="sort">
                <option value="volume" <?= (($filters['sort'] ?? '') === 'volume') ? 'selected' : '' ?>>Sort: Volume</option>
                <option value="change" <?= (($filters['sort'] ?? '') === 'change') ? 'selected' : '' ?>>Sort: Change</option>
                <option value="symbol" <?= (($filters['sort'] ?? '') === 'symbol') ? 'selected' : '' ?>>Sort: Symbol</option>
                <option value="price" <?= (($filters['sort'] ?? '') === 'price') ? 'selected' : '' ?>>Sort: Price</option>
            </select>
        </div>
        <div class="col-lg-1 d-flex gap-1">
            <button class="btn btn-primary flex-fill" type="submit">Go</button>
            <a class="btn btn-outline-light" href="/admin/markets/pairs"><i class="fas fa-times"></i></a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" id="pairsTable">
            <thead>
                <tr>
                    <th>Pair</th><th>Type</th><th>Last Price</th><th>24H Change</th>
                    <th>Volume 24H</th><th>High</th><th>Low</th>
                    <th>Fee M/T</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pairs as $p): ?>
                <?php
                $chg    = (float)($p['change_24h_percent'] ?? 0);
                $prec   = (int)($p['price_precision'] ?? 2);
                $active = (int)($p['is_active'] ?? 0);
                $trade  = (int)($p['trading_enabled'] ?? 0);
                ?>
                <tr>
                    <td>
                        <?php if (!empty($p['base_icon'])): ?>
                            <img src="<?= e($p['base_icon']) ?>" alt="" style="width:18px;height:18px;border-radius:50%;margin-right:4px">
                        <?php endif; ?>
                        <a href="/admin/markets/pair/detail?id=<?= (int)$p['id'] ?>" class="fw-semibold text-info text-decoration-none"><?= e((string)$p['symbol']) ?></a>
                    </td>
                    <td><span class="badge text-bg-<?= ['spot'=>'info','margin'=>'warning','futures'=>'danger'][$p['market_type'] ?? 'spot'] ?? 'secondary' ?>"><?= e(ucfirst((string)$p['market_type'])) ?></span></td>
                    <td class="fw-semibold"><?= number_format((float)($p['last_price'] ?? 0), $prec) ?></td>
                    <td class="<?= $chg >= 0 ? 'text-success' : 'text-danger' ?>"><?= $chg >= 0 ? '+' : '' ?><?= number_format($chg, 2) ?>%</td>
                    <td><?= number_format((float)($p['volume_24h'] ?? 0), 2) ?></td>
                    <td class="text-secondary"><?= number_format((float)($p['high_24h'] ?? 0), $prec) ?></td>
                    <td class="text-secondary"><?= number_format((float)($p['low_24h'] ?? 0), $prec) ?></td>
                    <td class="small"><?= number_format((float)($p['maker_fee_percent'] ?? 0), 2) ?>% / <?= number_format((float)($p['taker_fee_percent'] ?? 0), 2) ?>%</td>
                    <td>
                        <span class="badge text-bg-<?= $active ? 'success' : 'danger' ?> me-1"><?= $active ? 'Active' : 'Inactive' ?></span>
                        <?php if ($active): ?>
                        <span class="badge text-bg-<?= $trade ? 'info' : 'secondary' ?>"><?= $trade ? 'Trading' : 'Paused' ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/admin/markets/pair/detail?id=<?= (int)$p['id'] ?>" class="btn btn-xs btn-outline-info me-1">Detail</a>
                        <a href="/admin/assets/pairs?edit=<?= (int)$p['id'] ?>" class="btn btn-xs btn-outline-light">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pairs): ?>
                <tr><td colspan="10" class="text-center text-secondary py-4">No pairs found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#pairsTable').DataTable({ order: [], pageLength: 25, dom: 'lrtip' });
    }
});
</script>
