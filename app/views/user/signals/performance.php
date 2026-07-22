<?php declare(strict_types=1);
$subscriptions = (array)($subscriptions ?? []);
$performance   = (array)($performance ?? []);
$bookmarks     = (array)($bookmarks ?? []);
?>

<?php require app_path('app/views/user/_nav.php'); ?>

<div class="row g-4">
    <!-- Provider Performance Cards -->
    <div class="col-12">
        <h5 class="fw-semibold mb-3"><i class="fas fa-trophy me-2 text-warning"></i>Provider Performance</h5>
        <?php if (empty($subscriptions)): ?>
        <div class="glass rounded-3 p-5 text-center text-secondary">
            <i class="fas fa-satellite-dish fa-3x mb-3 d-block"></i>
            Subscribe to signal providers to track their performance.
            <div class="mt-3"><a href="/user/signals/subscribe" class="btn btn-info btn-sm">Browse Providers</a></div>
        </div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($subscriptions as $sub):
                $pid  = (int)$sub['provider_id'];
                $p30  = $performance[$pid]['30d'] ?? [];
                $p90  = $performance[$pid]['90d'] ?? [];
                $pAll = $performance[$pid]['all'] ?? [];
                $wr30 = (float)($p30['win_rate'] ?? 0);
                $wrColor = $wr30 >= 60 ? 'success' : ($wr30 >= 40 ? 'warning' : 'danger');
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="glass rounded-3 h-100">
                    <div class="p-3 border-bottom border-secondary d-flex align-items-center gap-3">
                        <div class="user-avatar" style="width:40px;height:40px;font-size:.85rem">
                            <?= e(mb_strtoupper(mb_substr($sub['provider_name'], 0, 2))) ?>
                        </div>
                        <div>
                            <div class="fw-semibold"><?= e($sub['provider_name']) ?></div>
                            <div class="text-secondary small"><?= e($sub['description'] ?? '') ?></div>
                        </div>
                    </div>
                    <div class="p-3">
                        <!-- Win rate gauge -->
                        <div class="text-center mb-3">
                            <div class="text-secondary small mb-1">30d Win Rate</div>
                            <div class="fw-bold fs-2 text-<?= $wrColor ?>"><?= number_format($wr30, 1) ?>%</div>
                            <div class="progress mx-auto mt-1" style="height:6px;max-width:120px">
                                <div class="progress-bar bg-<?= $wrColor ?>" style="width:<?= min(100, $wr30) ?>%"></div>
                            </div>
                        </div>
                        <!-- Stats grid -->
                        <div class="row g-2 text-center">
                            <?php
                            $cells = [
                                ['Signals (30d)', $p30['total_signals'] ?? '—', ''],
                                ['Hit TP',        $p30['hit_tp_count']  ?? '—', 'text-success'],
                                ['Hit SL',        $p30['hit_sl_count']  ?? '—', 'text-danger'],
                                ['Avg Profit',    isset($p30['avg_profit_pct']) ? '+'.number_format((float)$p30['avg_profit_pct'],2).'%' : '—', 'text-success'],
                                ['Avg Loss',      isset($p30['avg_loss_pct'])   ? number_format((float)$p30['avg_loss_pct'],2).'%' : '—', 'text-danger'],
                                ['Avg R:R',       isset($p30['avg_rr_ratio'])   ? number_format((float)$p30['avg_rr_ratio'],2) : '—', ''],
                            ];
                            foreach ($cells as [$label, $val, $col]):
                            ?>
                            <div class="col-4">
                                <div class="text-secondary" style="font-size:.65rem;text-transform:uppercase"><?= $label ?></div>
                                <div class="small fw-semibold <?= $col ?>"><?= $val ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- All-time return -->
                        <?php if (isset($pAll['total_return_pct'])): ?>
                        <div class="mt-3 text-center">
                            <span class="text-secondary small">All-time return: </span>
                            <span class="fw-semibold <?= $pAll['total_return_pct'] >= 0 ? 'text-profit' : 'text-loss' ?>">
                                <?= $pAll['total_return_pct'] >= 0 ? '+' : '' ?><?= number_format((float)$pAll['total_return_pct'], 2) ?>%
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="mt-2 text-center">
                            <a href="/user/signals/feed?provider_id=<?= $pid ?>" class="btn btn-xs btn-outline-info">View Signals</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bookmarked Signals -->
    <?php if (!empty($bookmarks)): ?>
    <div class="col-12">
        <h5 class="fw-semibold mb-3"><i class="fas fa-bookmark me-2 text-primary"></i>Bookmarked Signals</h5>
        <div class="glass rounded-3">
            <div class="table-responsive">
                <table class="table table-user mb-0">
                    <thead>
                        <tr><th>Pair</th><th>Type</th><th>Provider</th><th>Entry</th><th>TP1</th><th>SL</th><th>Status</th><th>Result</th><th>Bookmarked</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bookmarks as $sig):
                        $typeColor = match($sig['signal_type']) {
                            'buy','close_short'  => 'success',
                            'sell','close_long'  => 'danger',
                            default              => 'secondary',
                        };
                        $statusColor = match($sig['status']) {
                            'hit_tp'   => 'success',
                            'hit_sl'   => 'danger',
                            'active'   => 'info',
                            default    => 'secondary',
                        };
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= e($sig['pair_symbol'] ?? '—') ?></td>
                        <td><span class="badge bg-<?= $typeColor ?>"><?= e($sig['signal_type']) ?></span></td>
                        <td><?= e($sig['provider_name']) ?></td>
                        <td class="font-monospace small"><?= !empty($sig['entry_price']) ? number_format((float)$sig['entry_price'], 8) : '—' ?></td>
                        <td class="font-monospace small text-success"><?= !empty($sig['take_profit_1']) ? number_format((float)$sig['take_profit_1'], 8) : '—' ?></td>
                        <td class="font-monospace small text-danger"><?= !empty($sig['stop_loss']) ? number_format((float)$sig['stop_loss'], 8) : '—' ?></td>
                        <td><span class="badge bg-<?= $statusColor ?>"><?= e($sig['status']) ?></span></td>
                        <td>
                            <?php if ($sig['profit_pct'] !== null): ?>
                            <span class="fw-semibold <?= $sig['profit_pct'] >= 0 ? 'text-profit' : 'text-loss' ?>">
                                <?= $sig['profit_pct'] >= 0 ? '+' : '' ?><?= number_format((float)$sig['profit_pct'], 2) ?>%
                            </span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="small text-secondary"><?= e(date('M d', strtotime($sig['bookmarked_at']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let dtCounter = 0;
    document.querySelectorAll('table.table-user').forEach(t => {
        if (!t.id) t.id = 'dt_perf_' + (++dtCounter);
        new DataTable('#' + t.id, {pageLength: 25});
    });
});
</script>
