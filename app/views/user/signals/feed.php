<?php declare(strict_types=1);
$items       = (array)($items ?? []);
$total       = (int)($total ?? 0);
$page        = (int)($page ?? 1);
$perPage     = (int)($per_page ?? 20);
$totalPages  = (int)($total_pages ?? 1);
$filters     = (array)($filters ?? []);
$providers   = (array)($providers ?? []);
$csrfToken   = \App\Libraries\Csrf::token();
?>

<?php require app_path('app/views/user/_nav.php'); ?>

<!-- Filters -->
<form method="get" action="/user/signals/feed" class="glass rounded-3 p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small text-secondary mb-1">Provider</label>
            <select name="provider_id" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All Providers</option>
                <?php foreach ($providers as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (($filters['provider_id'] ?? '') == $p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-secondary mb-1">Type</label>
            <select name="signal_type" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All</option>
                <?php foreach (['buy','sell','hold','close_long','close_short','watch'] as $t): ?>
                <option value="<?= $t ?>" <?= ($filters['signal_type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-secondary mb-1">Market</label>
            <select name="market_type" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All</option>
                <?php foreach (['spot','futures','margin'] as $m): ?>
                <option value="<?= $m ?>" <?= ($filters['market_type'] ?? '') === $m ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-secondary mb-1">Status</label>
            <select name="status" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All</option>
                <?php foreach (['active','hit_tp','hit_sl','cancelled','expired'] as $s): ?>
                <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-secondary mb-1">Timeframe</label>
            <select name="timeframe" class="form-select form-select-sm bg-dark text-light border-secondary">
                <option value="">All</option>
                <?php foreach (['1m','5m','15m','30m','1h','4h','1d','1w'] as $tf): ?>
                <option value="<?= $tf ?>" <?= ($filters['timeframe'] ?? '') === $tf ? 'selected' : '' ?>><?= $tf ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-info btn-sm w-100">Filter</button>
        </div>
    </div>
</form>

<!-- Signal Cards -->
<?php if (empty($items)): ?>
<div class="glass rounded-3 p-5 text-center text-secondary">
    <i class="fas fa-chart-bar fa-2x mb-2 d-block"></i>
    No signals match your filters.
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($items as $sig):
        $typeColor = match($sig['signal_type']) {
            'buy','close_short'  => 'success',
            'sell','close_long'  => 'danger',
            'hold'               => 'warning',
            default              => 'secondary',
        };
        $statusColor = match($sig['status']) {
            'hit_tp'   => 'success',
            'hit_sl'   => 'danger',
            'active'   => 'info',
            default    => 'secondary',
        };
        $confColor = !empty($sig['confidence_score'])
            ? ($sig['confidence_score'] >= 75 ? 'success' : ($sig['confidence_score'] >= 50 ? 'warning' : 'secondary'))
            : 'secondary';
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="glass rounded-3 h-100 d-flex flex-column">
            <div class="p-3 border-bottom border-secondary">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-<?= $typeColor ?> fw-bold text-uppercase"><?= e($sig['signal_type']) ?></span>
                            <span class="fw-bold"><?= e($sig['pair_symbol'] ?? $sig['pair_symbol_live'] ?? '') ?></span>
                            <span class="badge bg-outline border border-secondary text-secondary small"><?= e($sig['market_type']) ?></span>
                        </div>
                        <div class="text-secondary small">
                            <?= e($sig['provider_name']) ?> · <?= e($sig['timeframe']) ?>
                            <?php if (!empty($sig['confidence_score'])): ?>
                            · <span class="text-<?= $confColor ?>"><i class="fas fa-signal me-1"></i><?= (int)$sig['confidence_score'] ?>%</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge bg-<?= $statusColor ?> flex-shrink-0"><?= e($sig['status']) ?></span>
                </div>
            </div>
            <div class="p-3 flex-grow-1">
                <?php if (!empty($sig['entry_price'])): ?>
                <div class="row g-2 mb-2 text-center">
                    <div class="col-4">
                        <div class="text-secondary" style="font-size:.65rem">ENTRY</div>
                        <div class="font-monospace small fw-semibold"><?= number_format((float)$sig['entry_price'], 6) ?></div>
                    </div>
                    <div class="col-4">
                        <div class="text-secondary" style="font-size:.65rem">TP1</div>
                        <div class="font-monospace small text-success"><?= !empty($sig['take_profit_1']) ? number_format((float)$sig['take_profit_1'], 6) : '—' ?></div>
                    </div>
                    <div class="col-4">
                        <div class="text-secondary" style="font-size:.65rem">SL</div>
                        <div class="font-monospace small text-danger"><?= !empty($sig['stop_loss']) ? number_format((float)$sig['stop_loss'], 6) : '—' ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($sig['analysis_text'])): ?>
                <p class="text-secondary small mb-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
                    <?= e($sig['analysis_text']) ?>
                </p>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center mt-auto">
                    <div class="text-secondary small">
                        <i class="fas fa-eye me-1"></i><?= (int)$sig['views_count'] ?>
                        <i class="fas fa-heart ms-2 me-1"></i><?= (int)$sig['likes_count'] ?>
                    </div>
                    <div class="text-secondary small"><?= e(date('M d H:i', strtotime($sig['published_at']))) ?></div>
                </div>
            </div>
            <div class="p-3 border-top border-secondary d-flex gap-2">
                <a href="/user/signals/detail?id=<?= (int)$sig['id'] ?>" class="btn btn-xs btn-outline-info flex-grow-1">
                    <i class="fas fa-eye me-1"></i>Detail
                </a>
                <?php if ($sig['profit_pct'] !== null): ?>
                <span class="badge <?= $sig['profit_pct'] >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> align-self-center">
                    <?= $sig['profit_pct'] >= 0 ? '+' : '' ?><?= number_format((float)$sig['profit_pct'], 2) ?>%
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
        <li class="page-item">
            <a class="page-link bg-dark border-secondary text-light" href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>">
                <i class="fas fa-chevron-left"></i>
            </a>
        </li>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link bg-dark border-secondary <?= $i === $page ? 'text-dark bg-info' : 'text-light' ?>"
               href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <li class="page-item">
            <a class="page-link bg-dark border-secondary text-light" href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>">
                <i class="fas fa-chevron-right"></i>
            </a>
        </li>
        <?php endif; ?>
    </ul>
    <div class="text-center text-secondary small">Showing <?= count($items) ?> of <?= $total ?> signals</div>
</nav>
<?php endif; ?>
<?php endif; ?>
