<?php declare(strict_types=1);
$stats         = (array)($stats ?? []);
$activeAlerts  = (array)($active_alerts ?? []);
?>

<?php require app_path('app/views/admin/_nav.php'); ?>

<?php if (!empty($error)): ?>
<div class="alert alert-warning py-2"><?= e((string)$error) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Alerts',   $stats['total_alerts']   ?? 0, 'fa-bell',          '#38bdf8'],
        ['Active',         $stats['active_alerts']  ?? 0, 'fa-bell-on',       '#34d399'],
        ['Triggered Today',$stats['triggers_24h']   ?? 0, 'fa-bolt',          '#f59e0b'],
        ['Users w/ Alerts',$stats['users_with_alerts'] ?? 0, 'fa-users',      '#a78bfa'],
        ['Total Triggers', $stats['total_triggers'] ?? 0, 'fa-play-circle',   '#fb923c'],
    ];
    foreach ($cards as [$label, $val, $icon, $color]):
    ?>
    <div class="col-6 col-md-2">
        <div class="glass rounded-3 p-3 text-center">
            <i class="fas <?= $icon ?>" style="color:<?= $color ?>"></i>
            <div class="fw-bold fs-5 mt-1"><?= $val ?></div>
            <div class="text-secondary" style="font-size:.7rem"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<h5 class="fw-semibold mb-3"><i class="fas fa-bell me-2 text-warning"></i>Active Price Alerts (<?= count($activeAlerts) ?>)</h5>
<div class="glass rounded-3">
    <div class="table-responsive">
        <table class="table table-user mb-0" id="alertsTable">
            <thead>
                <tr>
                    <th>User</th><th>Pair</th><th>Alert Type</th><th>Threshold</th>
                    <th>Timeframe</th><th>Recurring</th><th>Triggers</th><th>Last Triggered</th><th>Created</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($activeAlerts)): ?>
            <tr><td colspan="9" class="text-center text-secondary py-3">No active alerts.</td></tr>
            <?php else: ?>
            <?php foreach ($activeAlerts as $al): ?>
            <tr>
                <td>
                    <div class="fw-semibold small"><?= e($al['username']) ?></div>
                    <div class="text-secondary" style="font-size:.7rem"><?= e($al['email']) ?></div>
                </td>
                <td class="fw-semibold"><?= e($al['pair_symbol_live'] ?? $al['pair_symbol']) ?></td>
                <td><span class="badge bg-info-subtle text-info"><?= e(str_replace('_', ' ', $al['alert_type'])) ?></span></td>
                <td class="font-monospace small"><?= number_format((float)$al['threshold_value'], 8) ?></td>
                <td><?= e($al['timeframe']) ?></td>
                <td><?= $al['is_recurring'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-minus text-secondary"></i>' ?></td>
                <td><?= (int)$al['trigger_count'] ?></td>
                <td><?= $al['last_triggered_at'] ? e(date('M d H:i', strtotime($al['last_triggered_at']))) : '—' ?></td>
                <td class="small text-secondary"><?= e(date('M d H:i', strtotime($al['created_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('alertsTable')) {
        new DataTable('#alertsTable', {pageLength: 50, order: [[7, 'desc']]});
    }
});
</script>
