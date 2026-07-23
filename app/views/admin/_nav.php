<?php declare(strict_types=1); ?>
<?php $adminSection = (string)($adminSection ?? 'dashboard'); ?>
<nav class="glass rounded-4 p-3 mb-4">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
        <div>
            <div class="small text-secondary text-uppercase fw-semibold">Admin Panel</div>
            <div class="small text-secondary"><?= e((string)($username ?? 'Admin')) ?></div>
        </div>
        <form action="/logout" method="post" class="d-inline">
            <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
            <button class="btn btn-xs btn-outline-danger" type="submit"><i class="fas fa-sign-out-alt me-1"></i>Logout</button>
        </form>
    </div>
    <div class="d-flex flex-wrap gap-1">
        <?php
        $groups = [
            'Core' => [
                'dashboard'      => ['/admin/dashboard',   '<i class="fas fa-tachometer-alt me-1"></i>Dashboard'],
                'platform'       => ['/admin/platform',    '<i class="fas fa-th me-1"></i>Modules'],
            ],
            'Users' => [
                'users'          => ['/admin/users',       '<i class="fas fa-users me-1"></i>Users'],
                'kyc'            => ['/admin/kyc',         '<i class="fas fa-id-card me-1"></i>KYC'],
                'roles'          => ['/admin/roles',       '<i class="fas fa-user-shield me-1"></i>Roles'],
            ],
            'Markets' => [
                'markets'         => ['/admin/markets',         '<i class="fas fa-chart-area me-1"></i>Markets'],
                'assets'          => ['/admin/assets',          '<i class="fas fa-coins me-1"></i>Assets'],
                'charts'          => ['/admin/charts',          '<i class="fas fa-chart-line me-1"></i>Analytics'],
                'orders'          => ['/admin/orders',          '<i class="fas fa-list-ol me-1"></i>Orders'],
                'trading'         => ['/admin/trading',         '<i class="fas fa-chart-line me-1"></i>Trading'],
                'trading-engine'  => ['/admin/trading-engine',  '<i class="fas fa-exchange-alt me-1"></i>Engine'],
                'signals'         => ['/admin/signals',         '<i class="fas fa-broadcast-tower me-1"></i>Signals'],
                'wallets'         => ['/admin/wallets',         '<i class="fas fa-wallet me-1"></i>Wallets'],
                'withdrawals'     => ['/admin/withdrawals',     '<i class="fas fa-arrow-circle-up me-1"></i>Withdrawals'],
                'finance'         => ['/admin/finance',         '<i class="fas fa-dollar-sign me-1"></i>Finance'],
            ],
            'Operations' => [
                'affiliate'      => ['/admin/affiliate',         '<i class="fas fa-network-wired me-1"></i>Affiliate'],
                'risk'           => ['/admin/risk',              '<i class="fas fa-shield-alt me-1"></i>Risk'],
                'tickets'        => ['/admin/tickets',           '<i class="fas fa-headset me-1"></i>Support'],
                'notifications'  => ['/admin/notifications',     '<i class="fas fa-bell me-1"></i>Notifications'],
                'communications' => ['/admin/communications',    '<i class="fas fa-paper-plane me-1"></i>Comms'],
                'cms'            => ['/admin/cms',               '<i class="fas fa-globe me-1"></i>CMS'],
                'content'        => ['/admin/content',           '<i class="fas fa-file-alt me-1"></i>Content'],
                'logs'           => ['/admin/logs',              '<i class="fas fa-history me-1"></i>Logs'],
                'system'         => ['/admin/system',            '<i class="fas fa-cog me-1"></i>System'],
                'settings'       => ['/admin/settings',          '<i class="fas fa-sliders-h me-1"></i>Settings'],
                'settings-hub'   => ['/admin/settings/hub',      '<i class="fas fa-layer-group me-1"></i>Config Hub'],
            ],
        ];
        foreach ($groups as $groupName => $items):
        ?>
            <span class="badge text-bg-secondary me-1 align-self-center"><?= e($groupName) ?></span>
            <?php foreach ($items as $key => [$href, $label]):
                $active = $adminSection === $key;
            ?>
                <a class="btn btn-xs <?= $active ? 'btn-warning text-dark' : 'btn-outline-light' ?>" href="<?= e($href) ?>"><?= $label ?></a>
            <?php endforeach; ?>
            <span class="mx-1 text-secondary">|</span>
        <?php endforeach; ?>
    </div>
</nav>
