<?php declare(strict_types=1); ?>
<?php
$watchlist = is_array($watchlist ?? null) ? $watchlist : [];
$csrf      = \App\Libraries\Csrf::token();
?>
<style>
.wl-btn { background: none; border: none; padding: 0; font-size: .9rem; line-height: 1; }
.wl-btn .fa-star { color: #f59e0b; }
</style>

<?php require app_path('app/views/user/_nav.php'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <div class="small text-secondary mb-1"><?= count($watchlist) ?> pair<?= count($watchlist) !== 1 ? 's' : '' ?> in watchlist</div>
    </div>
    <div class="d-flex gap-2">
        <a href="/markets" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>All Markets</a>
    </div>
</div>

<?php if (!$watchlist): ?>
<div class="glass rounded-4 p-5 text-center">
    <i class="fas fa-star fa-3x text-warning mb-3 d-block"></i>
    <h3 class="h5 mb-2">Your watchlist is empty</h3>
    <p class="text-secondary mb-3">Star any trading pair on the Markets page to add it here.</p>
    <a href="/markets" class="btn btn-primary">Browse Markets</a>
</div>
<?php else: ?>
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-user align-middle mb-0" id="watchlistTable">
            <thead>
                <tr>
                    <th>Pair</th>
                    <th>Price</th>
                    <th>24H Change</th>
                    <th>24H High</th>
                    <th>24H Low</th>
                    <th>Volume (24H)</th>
                    <th>Type</th>
                    <th>Added</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($watchlist as $w): ?>
                <?php
                $chg  = (float)($w['change_24h_percent'] ?? 0);
                $prec = 4;
                ?>
                <tr data-pair-id="<?= (int)$w['trading_pair_id'] ?>">
                    <td>
                        <a href="/markets/detail?id=<?= (int)$w['trading_pair_id'] ?>" class="d-flex align-items-center gap-2 text-decoration-none text-white">
                            <?php if (!empty($w['base_icon'])): ?>
                                <img src="<?= e($w['base_icon']) ?>" alt="" style="width:22px;height:22px;border-radius:50%">
                            <?php else: ?>
                                <div style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#38bdf8,#6366f1);display:flex;align-items:center;justify-content:center;font-size:.55rem;font-weight:700;"><?= e(mb_substr((string)$w['base_code'], 0, 2)) ?></div>
                            <?php endif; ?>
                            <div class="fw-semibold"><?= e((string)$w['symbol']) ?></div>
                        </a>
                    </td>
                    <td class="fw-semibold"><?= number_format((float)($w['last_price'] ?? 0), $prec) ?></td>
                    <td>
                        <span class="badge <?= $chg >= 0 ? 'text-bg-success' : 'text-bg-danger' ?>">
                            <?= $chg >= 0 ? '+' : '' ?><?= number_format($chg, 2) ?>%
                        </span>
                    </td>
                    <td class="small text-secondary"><?= number_format((float)($w['high_24h'] ?? 0), $prec) ?></td>
                    <td class="small text-secondary"><?= number_format((float)($w['low_24h'] ?? 0), $prec) ?></td>
                    <td class="small"><?= number_format((float)($w['volume_24h'] ?? 0), 2) ?></td>
                    <td>
                        <span class="badge text-bg-<?= ['spot'=>'info','margin'=>'warning','futures'=>'danger'][$w['market_type'] ?? ''] ?? 'secondary' ?>">
                            <?= e(ucfirst((string)$w['market_type'])) ?>
                        </span>
                    </td>
                    <td class="small text-secondary"><?= e((string)($w['added_at'] ?? '-')) ?></td>
                    <td class="text-end">
                        <a href="/trade?pair=<?= urlencode((string)$w['symbol']) ?>" class="btn btn-xs btn-primary me-1">Trade</a>
                        <button class="wl-btn" onclick="removeFromWatchlist(<?= (int)$w['trading_pair_id'] ?>, this)" title="Remove from watchlist">
                            <i class="fas fa-star"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<input type="hidden" id="csrfToken" value="<?= e($csrf) ?>">

<script>
function removeFromWatchlist(pairId, btn) {
    const csrf = document.getElementById('csrfToken').value;
    fetch('/markets/watchlist/toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
        body: '_token=' + encodeURIComponent(csrf) + '&pair_id=' + pairId
    }).then(r => r.json()).then(function(data) {
        if (data.ok && !data.in_watchlist) {
            const row = document.querySelector('[data-pair-id="' + pairId + '"]');
            if (row) row.remove();
            const tbody = document.querySelector('#watchlistTable tbody');
            if (tbody && tbody.children.length === 0) {
                location.reload();
            }
        } else {
            alert(data.message || 'Error.');
        }
    }).catch(function() { alert('Network error.'); });
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#watchlistTable').DataTable({ order: [[2,'desc']], pageLength: 25, dom: 'lrtip' });
    }
});
</script>
