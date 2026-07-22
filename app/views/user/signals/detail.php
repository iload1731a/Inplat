<?php declare(strict_types=1);
$signal      = (array)($signal ?? []);
$interactions = (array)($interactions ?? []);
$performance = (array)($performance ?? []);
$csrfToken   = \App\Libraries\Csrf::token();

$typeColor = match($signal['signal_type'] ?? '') {
    'buy','close_short'  => 'success',
    'sell','close_long'  => 'danger',
    'hold'               => 'warning',
    default              => 'secondary',
};
$statusColor = match($signal['status'] ?? '') {
    'hit_tp'   => 'success',
    'hit_sl'   => 'danger',
    'active'   => 'info',
    default    => 'secondary',
};
$isLiked     = !empty($interactions['like']);
$isBookmarked = !empty($interactions['bookmark']);
?>

<?php require app_path('app/views/user/_nav.php'); ?>

<?php if (empty($signal)): ?>
<div class="alert alert-warning">Signal not found.</div>
<?php else: ?>
<div class="row g-4">
    <!-- Main signal card -->
    <div class="col-lg-8">
        <div class="glass rounded-3">
            <!-- Header -->
            <div class="p-4 border-bottom border-secondary">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-<?= $typeColor ?> fs-6 fw-bold text-uppercase px-3 py-2">
                                <?= e($signal['signal_type']) ?>
                            </span>
                            <h3 class="mb-0 fw-bold"><?= e($signal['pair_symbol'] ?? $signal['pair_symbol_live'] ?? '') ?></h3>
                        </div>
                        <div class="d-flex flex-wrap gap-2 text-secondary small">
                            <span><i class="fas fa-user me-1"></i><?= e($signal['provider_name'] ?? '') ?></span>
                            <span><i class="fas fa-clock me-1"></i><?= e($signal['timeframe']) ?></span>
                            <span><i class="fas fa-chart-line me-1"></i><?= e(ucfirst($signal['market_type'])) ?></span>
                            <?php if (!empty($signal['leverage'])): ?>
                            <span><i class="fas fa-bolt me-1"></i><?= (int)$signal['leverage'] ?>x</span>
                            <?php endif; ?>
                            <?php if (!empty($signal['confidence_score'])): ?>
                            <span class="text-warning"><i class="fas fa-signal me-1"></i><?= (int)$signal['confidence_score'] ?>% confidence</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <span class="badge bg-<?= $statusColor ?> fs-6"><?= e($signal['status']) ?></span>
                        <?php if ($signal['profit_pct'] !== null): ?>
                        <div class="fw-bold fs-5 <?= $signal['profit_pct'] >= 0 ? 'text-profit' : 'text-loss' ?> mt-1">
                            <?= $signal['profit_pct'] >= 0 ? '+' : '' ?><?= number_format((float)$signal['profit_pct'], 2) ?>%
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Price levels -->
            <div class="p-4 border-bottom border-secondary">
                <h6 class="text-secondary text-uppercase fw-semibold mb-3" style="font-size:.75rem;letter-spacing:.05em">Price Levels</h6>
                <div class="row g-3">
                    <?php
                    $levels = [
                        ['Entry',       $signal['entry_price'] ?? null,       ''],
                        ['Entry Zone H',$signal['entry_price_high'] ?? null,  ''],
                        ['Entry Zone L',$signal['entry_price_low'] ?? null,   ''],
                        ['Take Profit 1',$signal['take_profit_1'] ?? null,   'text-success'],
                        ['Take Profit 2',$signal['take_profit_2'] ?? null,   'text-success'],
                        ['Take Profit 3',$signal['take_profit_3'] ?? null,   'text-success'],
                        ['Stop Loss',   $signal['stop_loss'] ?? null,         'text-danger'],
                        ['R:R Ratio',   $signal['risk_reward_ratio'] ?? null, 'text-info'],
                    ];
                    foreach ($levels as [$label, $val, $col]):
                        if ($val === null) continue;
                    ?>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="p-2 rounded-2 bg-dark bg-opacity-50">
                            <div class="text-secondary small"><?= $label ?></div>
                            <div class="font-monospace fw-semibold <?= $col ?>"><?= number_format((float)$val, 8) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Analysis -->
            <?php if (!empty($signal['analysis_text'])): ?>
            <div class="p-4 border-bottom border-secondary">
                <h6 class="text-secondary text-uppercase fw-semibold mb-3" style="font-size:.75rem">Analysis</h6>
                <p class="mb-0 lh-base"><?= nl2br(e($signal['analysis_text'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Tags -->
            <?php
            $tags = is_string($signal['tags'] ?? null) ? json_decode($signal['tags'], true) : ($signal['tags'] ?? []);
            if (!empty($tags)):
            ?>
            <div class="p-4 border-bottom border-secondary">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ((array)$tags as $tag): ?>
                    <span class="badge bg-info-subtle text-info"><?= e($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Chart link -->
            <?php if (!empty($signal['chart_url'])): ?>
            <div class="p-4 border-bottom border-secondary">
                <a href="<?= e($signal['chart_url']) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-external-link-alt me-1"></i>View Chart
                </a>
            </div>
            <?php endif; ?>

            <!-- Footer: interactions -->
            <div class="p-3 d-flex align-items-center justify-content-between">
                <div class="text-secondary small">
                    Published <?= e(date('M d, Y H:i', strtotime($signal['published_at']))) ?>
                    <?php if ($signal['hit_at']): ?>
                    · Resolved <?= e(date('M d, Y H:i', strtotime($signal['hit_at']))) ?>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button id="likeBtn" class="btn btn-sm <?= $isLiked ? 'btn-danger' : 'btn-outline-danger' ?>"
                            onclick="toggleLike(<?= (int)$signal['id'] ?>)">
                        <i class="fas fa-heart me-1"></i><span id="likesCount"><?= (int)$signal['likes_count'] ?></span>
                    </button>
                    <button id="bookmarkBtn" class="btn btn-sm <?= $isBookmarked ? 'btn-primary' : 'btn-outline-primary' ?>"
                            onclick="toggleBookmark(<?= (int)$signal['id'] ?>)">
                        <i class="fas fa-bookmark"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar: Provider stats -->
    <div class="col-lg-4">
        <div class="glass rounded-3 mb-4 p-3">
            <h6 class="fw-semibold mb-3"><i class="fas fa-satellite-dish me-2 text-info"></i><?= e($signal['provider_name'] ?? '') ?></h6>
            <?php if (!empty($performance)): ?>
            <div class="row g-2 text-center">
                <?php
                $pCells = [
                    ['Win Rate', number_format((float)($performance['win_rate'] ?? 0), 1) . '%', $performance['win_rate'] >= 60 ? 'text-success' : 'text-warning'],
                    ['Signals',  $performance['total_signals'] ?? '—', ''],
                    ['Avg Profit', isset($performance['avg_profit_pct']) ? '+'.number_format((float)$performance['avg_profit_pct'],2).'%' : '—', 'text-success'],
                    ['Avg Loss',   isset($performance['avg_loss_pct'])   ? number_format((float)$performance['avg_loss_pct'],2).'%' : '—', 'text-danger'],
                ];
                foreach ($pCells as [$label, $val, $col]):
                ?>
                <div class="col-6">
                    <div class="glass rounded-2 p-2">
                        <div class="text-secondary" style="font-size:.65rem"><?= $label ?></div>
                        <div class="fw-semibold <?= $col ?>"><?= $val ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="mt-3 d-flex gap-2">
                <a href="/user/signals/feed?provider_id=<?= (int)$signal['provider_id'] ?>" class="btn btn-xs btn-outline-info flex-grow-1">
                    More from this provider
                </a>
            </div>
        </div>

        <!-- Similar signals placeholder -->
        <div class="glass rounded-3 p-3">
            <h6 class="fw-semibold mb-3"><i class="fas fa-link me-2 text-secondary"></i>Signal Stats</h6>
            <div class="d-flex justify-content-between small py-1 border-bottom border-secondary">
                <span class="text-secondary">Views</span>
                <span><?= (int)$signal['views_count'] ?></span>
            </div>
            <div class="d-flex justify-content-between small py-1 border-bottom border-secondary">
                <span class="text-secondary">Likes</span>
                <span><?= (int)$signal['likes_count'] ?></span>
            </div>
            <div class="d-flex justify-content-between small py-1 border-bottom border-secondary">
                <span class="text-secondary">Market Type</span>
                <span><?= e(ucfirst($signal['market_type'])) ?></span>
            </div>
            <?php if ($signal['expires_at']): ?>
            <div class="d-flex justify-content-between small py-1">
                <span class="text-secondary">Expires</span>
                <span><?= e(date('M d H:i', strtotime($signal['expires_at']))) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

function toggleLike(signalId) {
    fetch('/user/signals/like', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${encodeURIComponent(CSRF)}&signal_id=${signalId}`
    })
    .then(r => r.json())
    .then(d => {
        document.getElementById('likeBtn').className = d.liked ? 'btn btn-sm btn-danger' : 'btn btn-sm btn-outline-danger';
        document.getElementById('likesCount').textContent = d.likes_count;
    });
}

function toggleBookmark(signalId) {
    fetch('/user/signals/bookmark', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${encodeURIComponent(CSRF)}&signal_id=${signalId}`
    })
    .then(r => r.json())
    .then(d => {
        document.getElementById('bookmarkBtn').className = d.bookmarked ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-primary';
    });
}
</script>
