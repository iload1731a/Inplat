<?php declare(strict_types=1); ?>
<?php $adminSection = (string)($adminSection ?? 'dashboard'); ?>
<div class="glass rounded-4 p-3 mb-4">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div>
            <div class="small text-secondary text-uppercase">Admin Workspace</div>
            <div class="fw-semibold">Operational controls for marketplace-ready management</div>
            <div class="small text-secondary"><?= e((string)($username ?? 'Admin')) ?></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php
            $items = [
                'dashboard' => ['/admin/dashboard', 'Dashboard'],
                'platform' => ['/admin/platform', 'Modules'],
                'users' => ['/admin/users', 'Users'],
                'finance' => ['/admin/finance', 'Finance'],
                'trading' => ['/admin/trading', 'Trading'],
                'risk' => ['/admin/risk', 'Risk'],
                'communications' => ['/admin/communications', 'Comms'],
                'support' => ['/admin/support', 'Support'],
                'settings' => ['/admin/settings', 'Settings'],
            ];
            foreach ($items as $key => [$href, $label]):
                $active = $adminSection === $key;
            ?>
                <a class="btn btn-sm <?= $active ? 'btn-warning text-dark' : 'btn-outline-light' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <form action="/logout" method="post">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Logout</button>
            </form>
        </div>
    </div>
</div>
