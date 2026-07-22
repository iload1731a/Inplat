<?php declare(strict_types=1); ?>
<?php $sessions = is_array($sessions ?? null) ? $sessions : []; ?>
<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="glass rounded-4 p-4 shadow">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 mb-0">Session Management</h1>
                <a href="/dashboard" class="btn btn-outline-light btn-sm">Back to Dashboard</a>
            </div>
            <div class="table-responsive">
                <table class="table table-dark align-middle table-sm mb-0">
                    <thead><tr><th>IP</th><th>Device</th><th>Expires</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td><?= e((string)($session['ip_address'] ?? '-')) ?></td>
                            <td><?= e((string)($session['user_agent'] ?? '-')) ?></td>
                            <td><?= e((string)($session['expires_at'] ?? '-')) ?></td>
                            <td><?= ((int)($session['is_active'] ?? 0) === 1) ? 'Active' : 'Revoked' ?></td>
                            <td>
                                <?php if ((int)($session['is_active'] ?? 0) === 1): ?>
                                    <form action="/security/sessions/revoke" method="post" data-ajax="true">
                                        <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                                        <input type="hidden" name="session_token" value="<?= e((string)($session['session_token'] ?? '')) ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Revoke</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($sessions === []): ?><tr><td colspan="5" class="text-center text-secondary">No active sessions found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
