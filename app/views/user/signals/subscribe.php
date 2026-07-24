<?php declare(strict_types=1);
$providers      = (array)($providers ?? []);
$performance    = (array)($performance ?? []);
$mySubscriptions = (array)($my_subscriptions ?? []);
$subscribedIds  = array_map('intval', (array)($subscribed_ids ?? []));
$csrfToken      = \App\Libraries\Csrf::token();
?>

<?php require app_path('app/views/user/_nav.php'); ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h5 class="mb-0 fw-semibold"><i class="fas fa-satellite-dish me-2 text-info"></i>Signal Providers</h5>
    <span class="text-secondary small"><?= count($providers) ?> providers available</span>
</div>

<?php if (empty($providers)): ?>
<div class="glass rounded-3 p-5 text-center text-secondary">
    <i class="fas fa-satellite-dish fa-3x mb-3 d-block"></i>
    No active signal providers available. Check back later.
</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($providers as $p):
        $pid     = (int)$p['id'];
        $isSubed = in_array($pid, $subscribedIds, true);
        $perf30  = $performance[$pid]['30d'] ?? [];
        $wr      = (float)($perf30['win_rate'] ?? $p['win_rate'] ?? 0);
        $wrColor = $wr >= 60 ? 'success' : ($wr >= 40 ? 'warning' : 'danger');
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="glass rounded-3 h-100 d-flex flex-column">
            <div class="p-4 d-flex gap-3 align-items-start">
                <?php if (!empty($p['logo_url'])): ?>
                <img src="<?= e($p['logo_url']) ?>" alt="" style="width:48px;height:48px;border-radius:8px;object-fit:cover">
                <?php else: ?>
                <div class="user-avatar" style="width:48px;height:48px;font-size:1rem;border-radius:8px">
                    <?= e(mb_strtoupper(mb_substr($p['name'], 0, 2))) ?>
                </div>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <div class="fw-bold"><?= e($p['name']) ?></div>
                    <div class="text-secondary small mb-1"><?= e(ucfirst($p['provider_type'])) ?></div>
                    <?php if (!empty($p['description'])): ?>
                    <p class="text-secondary small mb-0" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
                        <?= e($p['description']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Stats -->
            <div class="px-4 pb-3 flex-grow-1">
                <div class="row g-3 text-center mb-3">
                    <div class="col-4">
                        <div class="text-secondary" style="font-size:.65rem;text-transform:uppercase">Win Rate</div>
                        <div class="fw-bold text-<?= $wrColor ?>"><?= number_format($wr, 1) ?>%</div>
                    </div>
                    <div class="col-4">
                        <div class="text-secondary" style="font-size:.65rem;text-transform:uppercase">Signals</div>
                        <div class="fw-bold"><?= (int)($p['signals_30d'] ?? $p['total_signals'] ?? 0) ?></div>
                    </div>
                    <div class="col-4">
                        <div class="text-secondary" style="font-size:.65rem;text-transform:uppercase">Subscribers</div>
                        <div class="fw-bold"><?= (int)($p['subscriber_count'] ?? 0) ?></div>
                    </div>
                </div>
                <?php if (!empty($perf30['avg_profit_pct'])): ?>
                <div class="text-center text-secondary small mb-3">
                    Avg profit/signal:
                    <span class="text-<?= $perf30['avg_profit_pct'] >= 0 ? 'profit' : 'loss' ?> fw-semibold">
                        <?= $perf30['avg_profit_pct'] >= 0 ? '+' : '' ?><?= number_format((float)$perf30['avg_profit_pct'], 2) ?>%
                    </span>
                </div>
                <?php endif; ?>
                <?php if ((float)($p['subscription_price'] ?? 0) > 0): ?>
                <div class="text-center">
                    <span class="badge bg-warning-subtle text-warning">
                        <?= number_format((float)$p['subscription_price'], 2) ?> <?= e($p['subscription_currency'] ?? '') ?>/mo
                    </span>
                </div>
                <?php else: ?>
                <div class="text-center">
                    <span class="badge bg-success-subtle text-success"><i class="fas fa-check me-1"></i>Free</span>
                </div>
                <?php endif; ?>
            </div>
            <!-- Action -->
            <div class="p-3 border-top border-secondary">
                <?php if ($isSubed): ?>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-danger flex-grow-1"
                            onclick="unsubscribe(<?= $pid ?>, this)">
                        <i class="fas fa-times me-1"></i>Unsubscribe
                    </button>
                    <a href="/user/signals/feed?provider_id=<?= $pid ?>" class="btn btn-sm btn-outline-info">
                        <i class="fas fa-chart-bar"></i>
                    </a>
                </div>
                <?php else: ?>
                <button class="btn btn-sm btn-info w-100"
                        onclick="subscribe(<?= $pid ?>, this)">
                    <i class="fas fa-satellite-dish me-1"></i>Subscribe
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

function subscribe(providerId, btn) {
    btn.disabled = true;
    fetch('/user/signals/subscribe', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${encodeURIComponent(CSRF)}&provider_id=${providerId}&notify_platform=1&notify_email=1`
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) location.reload();
        else { alert(d.message || 'Error'); btn.disabled = false; }
    });
}

function unsubscribe(providerId, btn) {
    if (!confirm('Unsubscribe from this provider?')) return;
    btn.disabled = true;
    fetch('/user/signals/unsubscribe', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${encodeURIComponent(CSRF)}&provider_id=${providerId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) location.reload();
        else { alert(d.message || 'Error'); btn.disabled = false; }
    });
}
</script>
