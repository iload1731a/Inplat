<?php declare(strict_types=1); ?>
<?php
$openPositions   = is_array($openPositions   ?? null) ? $openPositions   : [];
$closedPositions = is_array($closedPositions ?? null) ? $closedPositions : [];
$stats           = is_array($stats           ?? null) ? $stats           : [];
$pnlSeries       = is_array($pnlSeries       ?? null) ? $pnlSeries       : [];
$totalPositions  = (int)($stats['total_positions'] ?? 0);
$winRate         = $totalPositions > 0
    ? round((int)($stats['winning_positions'] ?? 0) / $totalPositions * 100, 1)
    : 0;
$pnlLabels       = json_encode(array_column($pnlSeries, 'day'));
$pnlValues       = json_encode(array_map('floatval', array_column($pnlSeries, 'pnl')));
require app_path('app/views/user/_nav.php');
?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <i class="fas fa-layer-group fa-xl text-info mb-2 d-block"></i>
            <div class="h4 fw-bold"><?= (int)($stats['open_positions'] ?? 0) ?></div>
            <div class="text-secondary small">Open Positions</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <i class="fas fa-trophy fa-xl text-warning mb-2 d-block"></i>
            <div class="h4 fw-bold"><?= $winRate ?>%</div>
            <div class="text-secondary small">Win Rate</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <i class="fas fa-coins fa-xl text-success mb-2 d-block"></i>
            <div class="h4 fw-bold <?= (float)($stats['total_realized_pnl'] ?? 0) >= 0 ? 'text-profit' : 'text-loss' ?>">
                <?= number_format((float)($stats['total_realized_pnl'] ?? 0), 4) ?>
            </div>
            <div class="text-secondary small">Realized PnL</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="glass rounded-4 p-3 text-center">
            <i class="fas fa-chart-line fa-xl text-primary mb-2 d-block"></i>
            <div class="h4 fw-bold <?= (float)($stats['total_unrealized_pnl'] ?? 0) >= 0 ? 'text-profit' : 'text-loss' ?>">
                <?= number_format((float)($stats['total_unrealized_pnl'] ?? 0), 4) ?>
            </div>
            <div class="text-secondary small">Unrealized PnL</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Open Positions -->
    <div class="col-lg-8">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-layer-group me-2 text-info"></i>Open Positions</h5>
            <div class="table-responsive">
                <table class="table table-user table-sm">
                    <thead>
                        <tr><th>Pair</th><th>Side</th><th>Entry</th><th>Current</th><th>Qty</th><th>Leverage</th><th>Margin</th><th>Unreal. PnL</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($openPositions as $pos): ?>
                        <?php $upnl = (float)($pos['unrealized_pnl'] ?? 0); ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string)($pos['pair_symbol'] ?? '-')) ?></td>
                            <td><span class="badge bg-<?= ($pos['side'] ?? '') === 'long' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($pos['side'] ?? '-'))) ?></span></td>
                            <td class="font-monospace small"><?= number_format((float)($pos['entry_price'] ?? 0), 6) ?></td>
                            <td class="font-monospace small"><?= number_format((float)($pos['current_price'] ?? 0), 6) ?></td>
                            <td class="font-monospace small"><?= number_format((float)($pos['quantity'] ?? 0), 6) ?></td>
                            <td class="small"><span class="badge bg-secondary"><?= (int)($pos['leverage'] ?? 1) ?>x</span></td>
                            <td class="font-monospace small"><?= number_format((float)($pos['margin_used'] ?? 0), 4) ?></td>
                            <td class="font-monospace fw-semibold <?= $upnl >= 0 ? 'text-profit' : 'text-loss' ?>">
                                <?= ($upnl >= 0 ? '+' : '') . number_format($upnl, 4) ?>
                            </td>
                            <td>
                                 <button class="btn btn-xs btn-outline-danger btn-close-position" data-position-id="<?= (int)$pos['id'] ?>">
                                    <i class="fas fa-times me-1"></i>Close
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($openPositions === []): ?>
                        <tr><td colspan="9" class="text-center text-secondary py-4">
                            <i class="fas fa-inbox fa-2x d-block mb-2"></i>No open positions
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PnL Chart -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-chart-area me-2 text-success"></i>Realized PnL (30d)</h5>
            <div id="pnlChart"></div>
        </div>
    </div>

    <!-- Closed Positions -->
    <div class="col-12">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-3"><i class="fas fa-history me-2 text-secondary"></i>Recent Closed Positions</h5>
            <div class="table-responsive">
                <table id="closedPositionsTable" class="table table-user table-sm">
                    <thead>
                        <tr><th>Pair</th><th>Side</th><th>Entry</th><th>Close</th><th>Qty</th><th>Leverage</th><th>Realized PnL</th><th>Opened</th><th>Closed</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($closedPositions as $pos): ?>
                        <?php $rpnl = (float)($pos['realized_pnl'] ?? 0); ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string)($pos['pair_symbol'] ?? '-')) ?></td>
                            <td><span class="badge bg-<?= ($pos['side'] ?? '') === 'long' ? 'success' : 'danger' ?>"><?= e(strtoupper((string)($pos['side'] ?? '-'))) ?></span></td>
                            <td class="font-monospace small"><?= number_format((float)($pos['entry_price'] ?? 0), 6) ?></td>
                            <td class="font-monospace small"><?= number_format((float)($pos['current_price'] ?? 0), 6) ?></td>
                            <td class="font-monospace small"><?= number_format((float)($pos['quantity'] ?? 0), 6) ?></td>
                            <td class="small"><span class="badge bg-secondary"><?= (int)($pos['leverage'] ?? 1) ?>x</span></td>
                            <td class="font-monospace fw-semibold <?= $rpnl >= 0 ? 'text-profit' : 'text-loss' ?>">
                                <?= ($rpnl >= 0 ? '+' : '') . number_format($rpnl, 4) ?>
                            </td>
                            <td class="small"><?= e(date('M d H:i', strtotime((string)($pos['opened_at'] ?? 'now')))) ?></td>
                            <td class="small"><?= e(date('M d H:i', strtotime((string)($pos['closed_at'] ?? 'now')))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$('#closedPositionsTable').DataTable({ order: [[8,'desc']], pageLength: 20 });

const pnlLabels = <?= $pnlLabels ?: '[]' ?>;
const pnlValues = <?= $pnlValues ?: '[]' ?>;

if (pnlLabels.length > 0) {
    new ApexCharts(document.getElementById('pnlChart'), {
        series: [{ name: 'PnL', data: pnlValues }],
        chart: { type: 'area', height: 220, background: 'transparent', toolbar: { show: false } },
        xaxis: { categories: pnlLabels, labels: { style: { colors: '#94a3b8' } } },
        yaxis: { labels: { style: { colors: '#94a3b8' } } },
        theme: { mode: 'dark' },
        colors: ['#34d399'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,0.1)' },
    }).render();
}

$(document).on('click', '.btn-close-position', function () {
    const posId = $(this).data('position-id');
    Swal.fire({
        title: 'Close Position #' + posId + '?',
        text: 'This will close your position at market price.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Close Position'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post('/user/positions/close', { _token: csrfToken, position_id: posId }, res => {
            if (res.ok) {
                Swal.fire({ icon: 'success', title: 'Closed', timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', text: res.message });
            }
        }).fail(() => Swal.fire({ icon: 'error', text: 'Request failed' }));
    });
}
</script>
