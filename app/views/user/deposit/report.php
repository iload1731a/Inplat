<?php declare(strict_types=1); ?>
<?php
$deposits = is_array($deposits ?? null) ? $deposits : [];
$stats    = is_array($stats    ?? null) ? $stats    : [];
$monthly  = is_array($monthly  ?? null) ? $monthly  : [];
require app_path('app/views/user/_nav.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <a href="/user/deposit" class="text-secondary text-decoration-none small">
            <i class="fas fa-arrow-left me-1"></i>Back to Deposits
        </a>
        <h1 class="h4 fw-bold mb-0 mt-1"><i class="fas fa-chart-bar me-2 text-info"></i>Deposit Report</h1>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $items = [
        ['Total Submitted',   (int)($stats['total']    ?? 0), 'secondary'],
        ['Credited',          (int)($stats['credited'] ?? 0), 'success'],
        ['Pending',           (int)($stats['pending']  ?? 0), 'warning'],
        ['Failed/Flagged',    ((int)($stats['failed'] ?? 0) + (int)($stats['flagged'] ?? 0)), 'danger'],
    ];
    foreach ($items as [$lbl, $val, $col]):
    ?>
    <div class="col-sm-6 col-lg-3">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary"><?= e($lbl) ?></div>
            <div class="h4 fw-bold text-<?= $col ?> mb-0"><?= number_format($val) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-12 col-lg-4">
        <div class="glass rounded-4 p-3 text-center">
            <div class="small text-secondary">Total Credited Volume</div>
            <div class="h5 fw-bold text-success mb-0 font-monospace">
                <?= number_format((float)($stats['total_credited_amount'] ?? 0), 4) ?>
            </div>
        </div>
    </div>
</div>

<!-- Monthly chart -->
<div class="glass rounded-4 p-4 mb-4">
    <h6 class="mb-3 fw-bold"><i class="fas fa-chart-area me-2 text-success"></i>Monthly Deposit Volume</h6>
    <div id="monthlyDepositChart"></div>
</div>

<!-- Deposit history table -->
<div class="glass rounded-4 p-4">
    <h6 class="mb-3 fw-bold"><i class="fas fa-table me-2"></i>Complete Deposit History</h6>
    <div class="table-responsive">
        <table id="reportDepositTable" class="table table-user align-middle">
            <thead>
                <tr>
                    <th>#</th><th>Currency</th><th>Amount</th>
                    <th>TxHash / Ref</th><th>Status</th>
                    <th>Submitted</th><th>Credited</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($deposits as $dep): ?>
                <?php
                $ds = (string)($dep['status'] ?? 'pending');
                $dc = match($ds) {
                    'credited'  => 'success',
                    'confirmed' => 'info',
                    'failed'    => 'danger',
                    'flagged'   => 'warning',
                    default     => 'secondary',
                };
                ?>
                <tr>
                    <td class="small text-secondary"><?= (int)$dep['id'] ?></td>
                    <td>
                        <span class="badge bg-info"><?= e((string)($dep['currency_code'] ?? '-')) ?></span>
                    </td>
                    <td class="font-monospace small fw-semibold"><?= number_format((float)($dep['amount'] ?? 0), 8) ?></td>
                    <td class="font-monospace small text-secondary">
                        <?php if (!empty($dep['tx_hash'])): ?>
                            <span title="<?= e((string)$dep['tx_hash']) ?>"><?= e(substr((string)$dep['tx_hash'], 0, 20)) ?>…</span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= $dc ?>"><?= ucfirst($ds) ?></span></td>
                    <td class="small text-secondary"><?= e(substr((string)($dep['created_at'] ?? ''), 0, 16)) ?></td>
                    <td class="small text-secondary">
                        <?= !empty($dep['credited_at']) ? e(substr((string)$dep['credited_at'], 0, 16)) : '—' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($deposits)): ?>
            <tr><td colspan="7" class="text-center text-secondary py-4">No deposits yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const months  = <?= json_encode(array_column($monthly, 'month')) ?>;
    const amounts = <?= json_encode(array_map(fn($r) => round((float)($r['total'] ?? 0), 4), $monthly)) ?>;
    const counts  = <?= json_encode(array_map(fn($r) => (int)($r['count'] ?? 0), $monthly)) ?>;

    new ApexCharts(document.getElementById('monthlyDepositChart'), {
        series: [
            { name: 'Volume',  type: 'area', data: amounts },
            { name: 'Credits', type: 'bar',  data: counts  },
        ],
        chart:  { type: 'line', height: 280, toolbar: { show: false }, background: 'transparent' },
        stroke: { curve: 'smooth', width: [2, 0] },
        fill:   { type: ['gradient', 'solid'], gradient: { shade: 'dark', opacityFrom: 0.5, opacityTo: 0 } },
        colors: ['#00e396', '#008ffb'],
        xaxis:  { categories: months, labels: { style: { colors: '#aaa' } } },
        yaxis:  [
            { title: { text: 'Volume', style: { color: '#00e396' } }, labels: { style: { colors: '#aaa' } } },
            { opposite: true, title: { text: 'Credits', style: { color: '#008ffb' } }, labels: { style: { colors: '#aaa' } } },
        ],
        tooltip: { theme: 'dark' },
        grid:    { borderColor: '#333' },
        theme:   { mode: 'dark' },
        plotOptions: { bar: { borderRadius: 3, columnWidth: '40%' } },
    }).render();

    if (typeof $.fn.DataTable !== 'undefined') {
        $('#reportDepositTable').DataTable({
            order: [[0, 'desc']], pageLength: 25,
            dom: 'Bfrtip', buttons: ['excel', 'csv', 'print'],
        });
    }
})();
</script>
