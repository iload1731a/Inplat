<?php declare(strict_types=1); ?>
<?php
$pairs        = is_array($pairs ?? null)        ? $pairs        : [];
$quotes       = is_array($quotes ?? null)       ? $quotes       : [];
$watchlistIds = is_array($watchlistIds ?? null) ? $watchlistIds : [];
$topGainers   = is_array($topGainers ?? null)   ? $topGainers   : [];
$topLosers    = is_array($topLosers ?? null)    ? $topLosers    : [];
$topByVolume  = is_array($topByVolume ?? null)  ? $topByVolume  : [];
$filters      = is_array($filters ?? null)      ? $filters      : [];
$csrf         = \App\Libraries\Csrf::token();
?>
<style>
.market-tabs .nav-link { color: #94a3b8; border-radius: 6px; padding: .35rem .8rem; font-size: .82rem; }
.market-tabs .nav-link.active { background: rgba(56,189,248,.15); color: #38bdf8; }
.market-card { transition: background .15s; cursor: pointer; }
.market-card:hover { background: rgba(56,189,248,.05) !important; }
.change-positive { color: #34d399; }
.change-negative { color: #f87171; }
.wl-btn { background: none; border: none; padding: 0; font-size: .9rem; line-height: 1; }
.wl-btn .fa-star.active { color: #f59e0b; }
.wl-btn .fa-star:not(.active) { color: #374151; }
.search-input { background: rgba(30,41,59,.8); border-color: rgba(148,163,184,.15); color: #e2e8f0; }
</style>

<?php require app_path('app/views/user/_nav.php'); ?>

<!-- Ticker Marquee -->
<div class="glass rounded-3 p-2 mb-4" style="overflow:hidden;white-space:nowrap">
    <div id="tickerMarquee" class="d-inline-block">
        <?php foreach (array_slice($pairs, 0, 12) as $p): ?>
            <?php $chg = (float)($p['change_24h_percent'] ?? 0); ?>
            <span class="me-4 small">
                <strong><?= e((string)$p['symbol']) ?></strong>
                <span class="ms-1"><?= number_format((float)($p['last_price'] ?? 0), (int)($p['price_precision'] ?? 2)) ?></span>
                <span class="ms-1 <?= $chg >= 0 ? 'change-positive' : 'change-negative' ?>"><?= $chg >= 0 ? '+' : '' ?><?= number_format($chg, 2) ?>%</span>
            </span>
        <?php endforeach; ?>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Top Gainers -->
    <div class="col-md-4">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 text-success mb-3"><i class="fas fa-fire me-1"></i>Top Gainers</h2>
            <?php foreach ($topGainers as $g): ?>
            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-secondary">
                <a href="/markets/detail?id=<?= (int)$g['id'] ?>" class="text-decoration-none">
                    <span class="fw-semibold text-white small"><?= e((string)$g['symbol']) ?></span>
                </a>
                <div class="text-end small">
                    <div class="change-positive">+<?= number_format((float)($g['change_24h_percent'] ?? 0), 2) ?>%</div>
                    <div class="text-secondary" style="font-size:.7rem">Vol: <?= number_format((float)($g['volume_24h'] ?? 0), 0) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$topGainers): ?><p class="text-secondary small mb-0 text-center py-2">No data.</p><?php endif; ?>
        </div>
    </div>
    <!-- Top Losers -->
    <div class="col-md-4">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 text-danger mb-3"><i class="fas fa-arrow-trend-down me-1"></i>Top Losers</h2>
            <?php foreach ($topLosers as $l): ?>
            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-secondary">
                <a href="/markets/detail?id=<?= (int)$l['id'] ?>" class="text-decoration-none">
                    <span class="fw-semibold text-white small"><?= e((string)$l['symbol']) ?></span>
                </a>
                <div class="text-end small">
                    <div class="change-negative"><?= number_format((float)($l['change_24h_percent'] ?? 0), 2) ?>%</div>
                    <div class="text-secondary" style="font-size:.7rem">Vol: <?= number_format((float)($l['volume_24h'] ?? 0), 0) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$topLosers): ?><p class="text-secondary small mb-0 text-center py-2">No data.</p><?php endif; ?>
        </div>
    </div>
    <!-- Most Active -->
    <div class="col-md-4">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 text-info mb-3"><i class="fas fa-bolt me-1"></i>Most Active</h2>
            <?php foreach ($topByVolume as $v): ?>
            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-secondary">
                <a href="/markets/detail?id=<?= (int)$v['id'] ?>" class="text-decoration-none">
                    <span class="fw-semibold text-white small"><?= e((string)$v['symbol']) ?></span>
                </a>
                <div class="text-end small">
                    <?php $vchg = (float)($v['change_24h_percent'] ?? 0); ?>
                    <div class="<?= $vchg >= 0 ? 'change-positive' : 'change-negative' ?>"><?= $vchg >= 0 ? '+' : '' ?><?= number_format($vchg, 2) ?>%</div>
                    <div class="text-secondary" style="font-size:.7rem">$<?= number_format((float)($v['volume_24h'] ?? 0), 0) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$topByVolume): ?><p class="text-secondary small mb-0 text-center py-2">No data.</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- Market Table -->
<div class="glass rounded-4 p-3">
    <!-- Search + Quote Tabs + Sort -->
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <input type="text" id="marketSearch" class="form-control search-input" placeholder="Search pairs..." style="max-width:220px"
               value="<?= e((string)($filters['search'] ?? '')) ?>">
        <ul class="nav market-tabs ms-2">
            <li class="nav-item">
                <a class="nav-link <?= empty($filters['quote']) ? 'active' : '' ?>" href="/markets">All</a>
            </li>
            <?php foreach ($quotes as $q): ?>
            <li class="nav-item">
                <a class="nav-link <?= strtoupper($filters['quote'] ?? '') === $q['code'] ? 'active' : '' ?>"
                   href="/markets?quote=<?= urlencode($q['code']) ?>"><?= e($q['code']) ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="ms-auto d-flex gap-2">
            <select id="sortSelect" class="form-select form-select-sm" style="width:auto;background:rgba(30,41,59,.8);color:#e2e8f0;border-color:rgba(148,163,184,.15)">
                <option value="volume" <?= ($filters['sort'] ?? 'volume') === 'volume' ? 'selected' : '' ?>>Volume</option>
                <option value="change" <?= ($filters['sort'] ?? '') === 'change' ? 'selected' : '' ?>>Change</option>
                <option value="price"  <?= ($filters['sort'] ?? '') === 'price'  ? 'selected' : '' ?>>Price</option>
                <option value="symbol" <?= ($filters['sort'] ?? '') === 'symbol' ? 'selected' : '' ?>>Symbol</option>
            </select>
            <select id="typeFilter" class="form-select form-select-sm" style="width:auto;background:rgba(30,41,59,.8);color:#e2e8f0;border-color:rgba(148,163,184,.15)">
                <option value="">All Markets</option>
                <option value="spot"    <?= ($filters['market_type'] ?? '') === 'spot'    ? 'selected' : '' ?>>Spot</option>
                <option value="margin"  <?= ($filters['market_type'] ?? '') === 'margin'  ? 'selected' : '' ?>>Margin</option>
                <option value="futures" <?= ($filters['market_type'] ?? '') === 'futures' ? 'selected' : '' ?>>Futures</option>
            </select>
            <a href="/markets/watchlist" class="btn btn-sm btn-outline-warning"><i class="fas fa-star me-1"></i>Watchlist</a>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-user align-middle mb-0" id="marketsTable">
            <thead>
                <tr>
                    <th style="width:32px"></th>
                    <th>Pair</th>
                    <th>Price</th>
                    <th>24H Change</th>
                    <th>24H High</th>
                    <th>24H Low</th>
                    <th>Volume (24H)</th>
                    <th>Type</th>
                    <th class="text-end">Trade</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pairs as $p): ?>
                <?php
                $chg   = (float)($p['change_24h_percent'] ?? 0);
                $prec  = (int)($p['price_precision'] ?? 2);
                $inWl  = in_array((int)$p['id'], $watchlistIds, true);
                ?>
                <tr class="market-card" data-pair-id="<?= (int)$p['id'] ?>" data-symbol="<?= e((string)$p['symbol']) ?>" data-market-type="<?= e((string)$p['market_type']) ?>">
                    <td>
                        <button class="wl-btn" onclick="toggleWatchlist(<?= (int)$p['id'] ?>, this)" title="<?= $inWl ? 'Remove from watchlist' : 'Add to watchlist' ?>">
                            <i class="fas fa-star <?= $inWl ? 'active' : '' ?>"></i>
                        </button>
                    </td>
                    <td>
                        <a href="/markets/detail?id=<?= (int)$p['id'] ?>" class="d-flex align-items-center gap-2 text-decoration-none text-white">
                            <?php if (!empty($p['base_icon'])): ?>
                                <img src="<?= e($p['base_icon']) ?>" alt="" style="width:22px;height:22px;border-radius:50%">
                            <?php else: ?>
                                <div style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#38bdf8,#6366f1);display:flex;align-items:center;justify-content:center;font-size:.55rem;font-weight:700;"><?= e(mb_substr((string)$p['base_code'], 0, 2)) ?></div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?= e((string)$p['symbol']) ?></div>
                                <div class="text-secondary" style="font-size:.7rem"><?= e((string)$p['base_name']) ?></div>
                            </div>
                        </a>
                    </td>
                    <td class="fw-semibold"><?= number_format((float)($p['last_price'] ?? 0), $prec) ?> <span class="text-secondary small"><?= e((string)$p['quote_code']) ?></span></td>
                    <td>
                        <span class="badge <?= $chg >= 0 ? 'text-bg-success' : 'text-bg-danger' ?>">
                            <?= $chg >= 0 ? '+' : '' ?><?= number_format($chg, 2) ?>%
                        </span>
                    </td>
                    <td class="small text-secondary"><?= number_format((float)($p['high_24h'] ?? 0), $prec) ?></td>
                    <td class="small text-secondary"><?= number_format((float)($p['low_24h'] ?? 0), $prec) ?></td>
                    <td class="small"><?= number_format((float)($p['volume_24h'] ?? 0), 2) ?></td>
                    <td>
                        <span class="badge text-bg-<?= ['spot'=>'info','margin'=>'warning','futures'=>'danger'][$p['market_type'] ?? ''] ?? 'secondary' ?>">
                            <?= e(ucfirst((string)$p['market_type'])) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="/trade?pair=<?= urlencode((string)$p['symbol']) ?>" class="btn btn-xs btn-primary">Trade</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pairs): ?>
                <tr><td colspan="9" class="text-center text-secondary py-4">No markets found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Hidden CSRF form for watchlist toggle -->
<input type="hidden" id="csrfToken" value="<?= e($csrf) ?>">

<script>
// Client-side filter
document.getElementById('marketSearch').addEventListener('input', applyFilters);
document.getElementById('sortSelect').addEventListener('change', function() {
    const u = new URL(location.href);
    u.searchParams.set('sort', this.value);
    location.href = u.toString();
});
document.getElementById('typeFilter').addEventListener('change', function() {
    const u = new URL(location.href);
    if (this.value) u.searchParams.set('market_type', this.value);
    else u.searchParams.delete('market_type');
    location.href = u.toString();
});

function applyFilters() {
    const q = document.getElementById('marketSearch').value.toLowerCase();
    document.querySelectorAll('#marketsTable tbody tr').forEach(function(row) {
        const sym = (row.dataset.symbol || '').toLowerCase();
        row.style.display = (!q || sym.includes(q)) ? '' : 'none';
    });
}

function toggleWatchlist(pairId, btn) {
    const csrf = document.getElementById('csrfToken').value;
    const star = btn.querySelector('.fa-star');
    fetch('/markets/watchlist/toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
        body: '_token=' + encodeURIComponent(csrf) + '&pair_id=' + pairId
    }).then(r => r.json()).then(function(data) {
        if (data.ok) {
            if (data.in_watchlist) {
                star.classList.add('active');
                btn.title = 'Remove from watchlist';
            } else {
                star.classList.remove('active');
                btn.title = 'Add to watchlist';
            }
        } else {
            alert(data.message || 'Error toggling watchlist.');
        }
    }).catch(function() { alert('Network error.'); });
}

// Ticker marquee animation
(function() {
    const m = document.getElementById('tickerMarquee');
    if (!m) return;
    let pos = 0;
    const speed = 0.5;
    function tick() {
        pos -= speed;
        if (Math.abs(pos) >= m.scrollWidth / 2) pos = 0;
        m.style.transform = 'translateX(' + pos + 'px)';
        requestAnimationFrame(tick);
    }
    // Duplicate content for seamless loop
    m.innerHTML += m.innerHTML;
    tick();
})();

// Auto-refresh tickers every 15s
setInterval(function() {
    const quote = '<?= e((string)($filters['quote'] ?? '')) ?>';
    fetch('/markets/tickers' + (quote ? '?quote=' + encodeURIComponent(quote) : ''))
        .then(r => r.json())
        .then(function(data) {
            if (!data.ok) return;
            data.data.forEach(function(p) {
                const row = document.querySelector('[data-pair-id="' + p.id + '"]');
                if (!row) return;
                const cells = row.querySelectorAll('td');
                if (cells.length < 8) return;
                const chg = parseFloat(p.change_24h_percent);
                const prec = parseInt(p.price_precision) || 2;
                cells[2].innerHTML = parseFloat(p.last_price).toFixed(prec) + ' <span class="text-secondary small">' + p.quote_code + '</span>';
                cells[3].innerHTML = '<span class="badge ' + (chg >= 0 ? 'text-bg-success' : 'text-bg-danger') + '">' + (chg >= 0 ? '+' : '') + chg.toFixed(2) + '%</span>';
                cells[6].textContent = parseFloat(p.volume_24h).toFixed(2);
            });
        }).catch(function(){});
}, 15000);
</script>
