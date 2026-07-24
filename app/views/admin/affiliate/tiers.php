<?php declare(strict_types=1); ?>
<?php
$tiers = is_array($tiers ?? null) ? $tiers : [];
require app_path('app/views/admin/_nav.php');
?>

<div class="glass rounded-4 p-4 mb-4">
    <h6 class="mb-4"><i class="fas fa-layer-group me-2 text-success"></i>Commission Tier Configuration</h6>
    <p class="text-secondary small mb-4">
        Configure multi-level commission rates. Level 1 is for direct referrals. 
        Higher levels reward you for referrals made by your referrals.
    </p>

    <form method="post" action="/admin/affiliate/tiers">
        <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">

        <?php if ($tiers === []): ?>
        <div class="alert alert-info rounded-3 mb-4">No tiers configured. Add tiers below.</div>
        <?php endif; ?>

        <div class="table-responsive mb-4">
            <table class="table table-user">
                <thead>
                    <tr><th>Level</th><th>Label</th><th>Commission Rate (%)</th><th>Min Direct Referrals</th><th>Active</th></tr>
                </thead>
                <tbody>
                <?php foreach ($tiers as $i => $tier): ?>
                <tr>
                    <td>
                        <span class="badge bg-info">L<?= (int)$tier['level'] ?></span>
                        <input type="hidden" name="tiers[<?= $i ?>][level]" value="<?= (int)$tier['level'] ?>">
                    </td>
                    <td>
                        <input type="text" name="tiers[<?= $i ?>][label]"
                               value="<?= e((string)$tier['label']) ?>"
                               class="form-control form-control-sm bg-transparent text-light border-secondary"
                               maxlength="50" style="width:180px">
                    </td>
                    <td>
                        <div class="input-group input-group-sm" style="width:160px">
                            <input type="number" name="tiers[<?= $i ?>][commission_rate]"
                                   value="<?= number_format((float)$tier['commission_rate'], 4, '.', '') ?>"
                                   class="form-control bg-transparent text-light border-secondary"
                                   step="0.0001" min="0" max="100" required>
                            <span class="input-group-text bg-transparent text-secondary border-secondary">%</span>
                        </div>
                    </td>
                    <td>
                        <input type="number" name="tiers[<?= $i ?>][min_referred]"
                               value="<?= (int)$tier['min_referred'] ?>"
                               class="form-control form-control-sm bg-transparent text-light border-secondary"
                               min="0" style="width:120px">
                    </td>
                    <td>
                        <input type="hidden" name="tiers[<?= $i ?>][is_active]" value="0">
                        <input type="checkbox" name="tiers[<?= $i ?>][is_active]" value="1"
                               class="form-check-input" <?= (int)$tier['is_active'] ? 'checked' : '' ?>>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex gap-3 align-items-center">
            <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Save Tiers</button>
            <a href="/admin/affiliate" class="btn btn-outline-secondary">Back to Dashboard</a>
            <div class="text-secondary small ms-auto">
                <i class="fas fa-info-circle me-1"></i>
                Set rate to 0% to disable a level without removing it.
            </div>
        </div>
    </form>
</div>

<div class="glass rounded-4 p-4">
    <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-info"></i>Multi-Level Commission Guide</h6>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="glass rounded-3 p-3 border border-success border-opacity-25 h-100">
                <h6 class="text-success small mb-2"><i class="fas fa-user me-1"></i>Level 1 (Direct)</h6>
                <p class="text-secondary small mb-0">Commission earned on every trade executed by your direct referrals. Usually the highest rate (e.g. 20%).</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass rounded-3 p-3 border border-info border-opacity-25 h-100">
                <h6 class="text-info small mb-2"><i class="fas fa-users me-1"></i>Level 2</h6>
                <p class="text-secondary small mb-0">Commission earned when users referred by your Level 1 referrals trade. Usually a lower rate (e.g. 10%). Set min_referred to unlock.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass rounded-3 p-3 border border-warning border-opacity-25 h-100">
                <h6 class="text-warning small mb-2"><i class="fas fa-network-wired me-1"></i>Level 3+</h6>
                <p class="text-secondary small mb-0">Deeper network commissions. Usually the lowest rate (e.g. 5%). Requires more direct referrals to unlock.</p>
            </div>
        </div>
    </div>
</div>
