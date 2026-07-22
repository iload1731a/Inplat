<?php declare(strict_types=1);
$requirements = is_array($requirements ?? null) ? $requirements : [];
$csrf = \App\Libraries\Csrf::token();
$byLevel = [];
foreach ($requirements as $r) {
    $byLevel[(int)$r['kyc_level']][] = $r;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-list-check me-2 text-warning"></i>KYC Requirements</h1>
        <p class="text-secondary mb-0">Configure document requirements for each KYC level.</p>
    </div>
    <a href="/admin/kyc" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to KYC</a>
</div>

<?php require app_path('app/views/admin/_nav.php'); ?>

<?php foreach ($byLevel as $lvl => $reqs): ?>
<div class="glass rounded-3 p-4 mb-4">
    <h5 class="mb-3"><i class="fas fa-shield-alt me-2 text-info"></i>Level <?= $lvl ?> Requirements</h5>
    <div class="table-responsive">
        <table class="table table-dark table-sm align-middle small mb-0">
            <thead class="text-secondary">
                <tr><th>Document Type</th><th>Display Name</th><th>Description</th><th>Required?</th><th>Enabled?</th><th class="text-end">Save</th></tr>
            </thead>
            <tbody>
            <?php foreach ($reqs as $req): ?>
            <tr>
                <td class="font-monospace"><?= e((string)($req['document_type'] ?? '')) ?></td>
                <td>
                    <input type="text" class="form-control form-control-sm req-display-name"
                           data-id="<?= (int)$req['id'] ?>"
                           value="<?= e((string)($req['display_name'] ?? '')) ?>">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm req-description"
                           data-id="<?= (int)$req['id'] ?>"
                           value="<?= e((string)($req['description'] ?? '')) ?>">
                </td>
                <td>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input req-required" type="checkbox" role="switch"
                               data-id="<?= (int)$req['id'] ?>" <?= (bool)$req['is_required'] ? 'checked' : '' ?>>
                    </div>
                </td>
                <td>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input req-enabled" type="checkbox" role="switch"
                               data-id="<?= (int)$req['id'] ?>" <?= (bool)$req['is_enabled'] ? 'checked' : '' ?>>
                    </div>
                </td>
                <td class="text-end">
                    <button class="btn btn-xs btn-outline-success btn-save-req" data-id="<?= (int)$req['id'] ?>">
                        <i class="fas fa-save"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($requirements)): ?>
<div class="alert alert-secondary text-center">No requirements configured. Run the schema migration to seed defaults.</div>
<?php endif; ?>

<script>
const CSRF = '<?= e($csrf) ?>';

document.querySelectorAll('.btn-save-req').forEach(btn => {
    btn.addEventListener('click', function () {
        const id = this.dataset.id;
        const row = this.closest('tr');
        const data = {
            _token:       CSRF,
            id:           id,
            display_name: row.querySelector('.req-display-name').value,
            description:  row.querySelector('.req-description').value,
            is_required:  row.querySelector('.req-required').checked  ? 1 : 0,
            is_enabled:   row.querySelector('.req-enabled').checked   ? 1 : 0,
        };
        $.post('/admin/kyc/requirements/update', data, r => {
            if (r.ok) {
                this.classList.replace('btn-outline-success', 'btn-success');
                setTimeout(() => this.classList.replace('btn-success', 'btn-outline-success'), 1500);
            } else {
                Swal.fire({ icon: 'error', text: r.message });
            }
        }, 'json').fail(xhr => Swal.fire({ icon: 'error', text: xhr.responseJSON?.message || 'Failed' }));
    });
});
</script>
