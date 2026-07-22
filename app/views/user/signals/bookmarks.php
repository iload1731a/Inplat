<?php declare(strict_types=1);
$bookmarks = (array)($bookmarks ?? []);
$csrfToken = \App\Libraries\Csrf::token();
?>

<?php require app_path('app/views/user/_nav.php'); ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h5 class="mb-0 fw-semibold"><i class="fas fa-bookmark me-2 text-primary"></i>Bookmarked Signals</h5>
    <span class="badge bg-secondary"><?= count($bookmarks) ?> saved</span>
</div>

<?php if (empty($bookmarks)): ?>
<div class="glass rounded-3 p-5 text-center text-secondary">
    <i class="fas fa-bookmark fa-3x mb-3 d-block opacity-25"></i>
    No bookmarked signals yet. Browse the <a href="/user/signals/feed" class="text-info">signal feed</a> and bookmark signals you want to track.
</div>
<?php else: ?>
<div class="glass rounded-3">
    <div class="table-responsive">
        <table class="table table-user mb-0" id="bookmarksTable">
            <thead>
                <tr>
                    <th>Pair</th><th>Type</th><th>Provider</th><th>Entry</th>
                    <th>TP1</th><th>SL</th><th>Status</th><th>Result</th><th>Bookmarked</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bookmarks as $sig):
                $typeColor = match($sig['signal_type']) {
                    'buy','close_short'  => 'success',
                    'sell','close_long'  => 'danger',
                    'hold'               => 'warning',
                    default              => 'secondary',
                };
                $statusColor = match($sig['status']) {
                    'hit_tp'   => 'success', 'hit_sl' => 'danger', 'active' => 'info', default => 'secondary',
                };
            ?>
            <tr>
                <td class="fw-semibold"><?= e($sig['pair_symbol'] ?? '—') ?></td>
                <td><span class="badge bg-<?= $typeColor ?>"><?= e($sig['signal_type']) ?></span></td>
                <td><?= e($sig['provider_name']) ?></td>
                <td class="font-monospace small"><?= !empty($sig['entry_price']) ? number_format((float)$sig['entry_price'],8) : '—' ?></td>
                <td class="font-monospace small text-success"><?= !empty($sig['take_profit_1']) ? number_format((float)$sig['take_profit_1'],8) : '—' ?></td>
                <td class="font-monospace small text-danger"><?= !empty($sig['stop_loss']) ? number_format((float)$sig['stop_loss'],8) : '—' ?></td>
                <td><span class="badge bg-<?= $statusColor ?>"><?= e($sig['status']) ?></span></td>
                <td>
                    <?php if ($sig['profit_pct'] !== null): ?>
                    <span class="fw-semibold <?= $sig['profit_pct'] >= 0 ? 'text-profit' : 'text-loss' ?>">
                        <?= $sig['profit_pct'] >= 0 ? '+' : '' ?><?= number_format((float)$sig['profit_pct'],2) ?>%
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="small text-secondary"><?= e(date('M d', strtotime($sig['bookmarked_at']))) ?></td>
                <td>
                    <a href="/user/signals/detail?id=<?= (int)$sig['id'] ?>" class="btn btn-xs btn-outline-info">View</a>
                    <button class="btn btn-xs btn-outline-danger ms-1"
                            onclick="removeBookmark(<?= (int)$sig['id'] ?>, this)">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

function removeBookmark(signalId, btn) {
    btn.disabled = true;
    fetch('/user/signals/bookmark', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${encodeURIComponent(CSRF)}&signal_id=${signalId}`
    })
    .then(r => r.json())
    .then(d => {
        if (!d.bookmarked) {
            btn.closest('tr').remove();
        } else {
            btn.disabled = false;
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('bookmarksTable')) {
        new DataTable('#bookmarksTable', {pageLength: 25, order: [[8,'desc']]});
    }
});
</script>
