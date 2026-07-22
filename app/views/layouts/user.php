<?php declare(strict_types=1); ?>
<?php
$authUserId   = (int)(\App\Libraries\Session::get('auth.user_id') ?? 0);
$authUsername = (string)(\App\Libraries\Session::get('auth.username') ?? 'Trader');
$userSection  = (string)($userSection ?? 'dashboard');
$pageTitle    = (string)($title ?? config('app.name'));
$csrfToken    = \App\Libraries\Csrf::token();
?>
<!doctype html>
<html lang="en" data-bs-theme="dark" id="htmlRoot">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= e($pageTitle) ?> — <?= e((string)config('app.name')) ?></title>
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-w: 260px;
            --top-bar-h: 56px;
            --bg-dark: #0f172a;
            --bg-card: rgba(15,23,42,0.7);
            --border-c: rgba(148,163,184,0.15);
            --accent: #38bdf8;
        }
        * { box-sizing: border-box; }
        body {
            background: var(--bg-dark);
            color: #e2e8f0;
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh;
        }
        /* Layout */
        #wrapper { display: flex; min-height: 100vh; }
        /* Sidebar */
        #sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: rgba(2,8,23,0.95);
            border-right: 1px solid var(--border-c);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 1040;
            transition: transform .25s ease;
        }
        #sidebar .sidebar-brand {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-c);
        }
        #sidebar .sidebar-user {
            padding: .75rem 1.25rem;
            border-bottom: 1px solid var(--border-c);
            display: flex; align-items: center; gap: .75rem;
        }
        #sidebar .user-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: linear-gradient(135deg,#38bdf8,#6366f1);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .9rem; flex-shrink: 0;
        }
        #sidebar .sidebar-nav { flex: 1; overflow-y: auto; padding: .5rem 0; }
        #sidebar .nav-section-title {
            padding: .5rem 1.25rem .25rem;
            font-size: .65rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .08em;
            color: #64748b;
        }
        #sidebar .nav-link {
            padding: .45rem 1.25rem;
            color: #94a3b8;
            border-radius: 0;
            display: flex; align-items: center; gap: .65rem;
            font-size: .85rem; transition: all .15s;
        }
        #sidebar .nav-link:hover { color: #e2e8f0; background: rgba(255,255,255,.05); }
        #sidebar .nav-link.active { color: var(--accent); background: rgba(56,189,248,.08); border-left: 3px solid var(--accent); }
        #sidebar .nav-link i { width: 16px; text-align: center; }
        #sidebar .sidebar-balance {
            padding: .75rem 1.25rem;
            border-top: 1px solid var(--border-c);
            font-size: .78rem;
        }
        /* Main area */
        #main-wrapper {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex; flex-direction: column;
            min-width: 0;
        }
        /* Top bar */
        #topbar {
            height: var(--top-bar-h);
            background: rgba(2,8,23,0.9);
            border-bottom: 1px solid var(--border-c);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky; top: 0; z-index: 1030;
            backdrop-filter: blur(12px);
        }
        /* Content */
        #page-content { padding: 1.5rem; flex: 1; }
        /* Glass card */
        .glass {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-c);
        }
        /* Status badges */
        .badge-pending   { background: #854d0e; color: #fde68a; }
        .badge-completed { background: #14532d; color: #86efac; }
        .badge-failed    { background: #7f1d1d; color: #fca5a5; }
        .badge-processing{ background: #1e3a5f; color: #93c5fd; }
        .badge-open      { background: #14532d; color: #86efac; }
        .badge-cancelled { background: #374151; color: #9ca3af; }
        .badge-approved  { background: #14532d; color: #86efac; }
        .badge-rejected  { background: #7f1d1d; color: #fca5a5; }
        /* Table dark */
        .table-user { --bs-table-bg: transparent; --bs-table-color: #e2e8f0; }
        .table-user thead th { color: #94a3b8; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; border-color: var(--border-c); }
        .table-user td { border-color: var(--border-c); vertical-align: middle; }
        /* PnL */
        .text-profit { color: #34d399; }
        .text-loss   { color: #f87171; }
        /* Mobile sidebar overlay */
        #sidebarOverlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.5); z-index: 1039;
        }
        .btn-xs { padding: .1rem .4rem; font-size: .75rem; }
        /* DataTables dark */
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            background: rgba(30,41,59,.8); color: #e2e8f0; border: 1px solid var(--border-c); border-radius: .375rem;
        }
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: #94a3b8 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--accent) !important; color: #0f172a !important; border-color: var(--accent) !important;
        }
        @media (max-width: 991.98px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.show { transform: translateX(0); }
            #sidebarOverlay { display: block; }
            #main-wrapper { margin-left: 0; }
        }
        /* Animated counter */
        .counter-animate { transition: all .3s ease; }
        /* Light mode overrides */
        html[data-bs-theme="light"] body { background: #f1f5f9; color: #1e293b; }
        html[data-bs-theme="light"] #sidebar { background: #fff; border-right-color: #e2e8f0; }
        html[data-bs-theme="light"] #topbar { background: rgba(255,255,255,.95); border-bottom-color: #e2e8f0; }
        html[data-bs-theme="light"] .glass { background: rgba(255,255,255,.8); border-color: #e2e8f0; }
        html[data-bs-theme="light"] #sidebar .nav-link { color: #475569; }
        html[data-bs-theme="light"] #sidebar .nav-link:hover { color: #1e293b; background: rgba(0,0,0,.04); }
        html[data-bs-theme="light"] #sidebar .nav-link.active { color: #0284c7; background: rgba(3,105,161,.08); }
    </style>
</head>
<body>
<div id="sidebarOverlay" onclick="toggleSidebar()"></div>
<div id="wrapper">
    <!-- ===== Sidebar ===== -->
    <nav id="sidebar">
        <div class="sidebar-brand">
            <a href="/dashboard" class="text-decoration-none d-flex align-items-center gap-2">
                <i class="fas fa-chart-line text-info fs-5"></i>
                <span class="fw-bold fs-6"><?= e((string)config('app.name')) ?></span>
            </a>
        </div>
        <div class="sidebar-user">
            <div class="user-avatar"><?= e(mb_strtoupper(mb_substr($authUsername, 0, 2))) ?></div>
            <div class="overflow-hidden">
                <div class="fw-semibold text-truncate small"><?= e($authUsername) ?></div>
                <div class="text-secondary" style="font-size:.7rem">Trader Account</div>
            </div>
        </div>
        <div class="sidebar-nav">
            <?php
            $nav = [
                'Main' => [
                    'dashboard'  => ['/dashboard',         'fa-tachometer-alt', 'Dashboard'],
                ],
                'Trading' => [
                    'trading'    => ['/trading',           'fa-chart-candlestick', 'Spot Trading'],
                    'orders'     => ['/user/orders',       'fa-list-ol',       'Open Orders'],
                    'orders-history' => ['/user/orders/history', 'fa-history', 'Order History'],
                    'staking'    => ['/staking',           'fa-lock',          'Staking'],
                    'convert'    => ['/convert',           'fa-exchange-alt',  'Convert'],
                ],
                'Portfolio' => [
                    'positions'  => ['/user/positions',    'fa-layer-group',   'Positions'],
                    'trades'     => ['/user/trades',       'fa-receipt',       'Trade History'],
                ],
                'Wallet' => [
                    'wallet'          => ['/user/wallet',           'fa-wallet',                  'Overview'],
                    'deposit'         => ['/user/wallet/deposit',   'fa-arrow-down-to-bracket',   'Deposit'],
                    'withdraw'        => ['/user/wallet/withdraw',  'fa-arrow-up-from-bracket',   'Withdraw'],
                    'transfer'        => ['/user/wallet/transfer',  'fa-exchange-alt',            'Transfer'],
                    'wallet-history'  => ['/user/wallet/history',   'fa-clock-rotate-left',       'History'],
                    'wallet-addresses'=> ['/user/wallet/addresses', 'fa-shield-alt',              'Addresses'],
                ],
                'Account' => [
                    'profile'    => ['/user/profile',      'fa-user-circle',   'Profile'],
                    'kyc'        => ['/user/kyc',          'fa-id-card',       'KYC Verification'],
                    'security'   => ['/user/security',     'fa-shield-halved', 'Security'],
                    'apikeys'    => ['/user/api-keys',     'fa-key',           'API Keys'],
                    'notifications' => ['/user/notifications','fa-bell',       'Notifications'],
                ],
                'Support' => [
                    'tickets'    => ['/user/tickets',      'fa-headset',       'Support Tickets'],
                    'referral'   => ['/user/referral',     'fa-users',         'Referral Program'],
                ],
            ];
            foreach ($nav as $sectionName => $items):
            ?>
            <div class="nav-section-title"><?= e($sectionName) ?></div>
            <?php foreach ($items as $key => [$href, $icon, $label]): ?>
            <a class="nav-link <?= $userSection === $key ? 'active' : '' ?>" href="<?= e($href) ?>">
                <i class="fas <?= e($icon) ?> fa-fw"></i>
                <?= e($label) ?>
            </a>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <div class="sidebar-balance text-secondary">
            <div class="d-flex justify-content-between align-items-center">
                <span><i class="fas fa-circle-dot text-success me-1" style="font-size:.5rem"></i>Connected</span>
                <form action="/logout" method="post" class="m-0">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button class="btn btn-xs btn-outline-danger" type="submit"><i class="fas fa-sign-out-alt"></i></button>
                </form>
            </div>
        </div>
    </nav>
    <!-- ===== Main ===== -->
    <div id="main-wrapper">
        <!-- Top Bar -->
        <header id="topbar">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="text-secondary small d-none d-md-inline">
                    <i class="fas fa-home me-1"></i>
                    <?= e((string)($title ?? 'Dashboard')) ?>
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <!-- Theme toggle -->
                <button class="btn btn-sm btn-outline-secondary" id="themeToggle" title="Toggle theme">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>
                <!-- Notifications -->
                <a href="/user/notifications" class="btn btn-sm btn-outline-secondary position-relative" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php
                    $notifCount = (int)(\App\Libraries\Session::get('auth.unread_notifications') ?? 0);
                    if ($notifCount > 0):
                    ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem">
                        <?= $notifCount > 99 ? '99+' : $notifCount ?>
                    </span>
                    <?php endif; ?>
                </a>
                <!-- User dropdown -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <span class="d-none d-sm-inline"><?= e($authUsername) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end glass border-0">
                        <li><a class="dropdown-item" href="/user/profile"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="/user/security"><i class="fas fa-shield-halved me-2"></i>Security</a></li>
                        <li><a class="dropdown-item" href="/user/api-keys"><i class="fas fa-key me-2"></i>API Keys</a></li>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li>
                            <form action="/logout" method="post">
                                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                <button class="dropdown-item text-danger" type="submit">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <!-- Page Content -->
        <main id="page-content">
            <?= $content ?>
        </main>
    </div>
</div>
<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.48.0/dist/apexcharts.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
// Theme persistence
(function () {
    const saved = localStorage.getItem('uiTheme') || 'dark';
    document.getElementById('htmlRoot').setAttribute('data-bs-theme', saved);
    const icon = document.getElementById('themeIcon');
    if (icon) icon.className = saved === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
})();

document.getElementById('themeToggle')?.addEventListener('click', function () {
    const html = document.getElementById('htmlRoot');
    const current = html.getAttribute('data-bs-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', next);
    localStorage.setItem('uiTheme', next);
    document.getElementById('themeIcon').className = next === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
});

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

// CSRF token helper
const csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } });

// Global AJAX form handler
$(document).on('submit', 'form[data-ajax="true"]', function (e) {
    e.preventDefault();
    const $form = $(this);
    $.ajax({
        url: $form.attr('action'),
        method: ($form.attr('method') || 'POST').toUpperCase(),
        data: $form.serialize(),
        success(r) {
            $form.trigger('ajax:success', [r]);
            if (r.message) Swal.fire({ icon: 'success', title: 'Success', text: r.message, timer: 2500, showConfirmButton: false });
            if (r.redirect) setTimeout(() => window.location.href = r.redirect, 1000);
        },
        error(xhr) {
            const p = xhr.responseJSON || { message: 'Request failed' };
            $form.trigger('ajax:error', [p]);
            Swal.fire({ icon: 'error', title: 'Error', text: p.message || JSON.stringify(p.errors) });
        }
    });
});

// DataTables default config
$.fn.dataTable.defaults.dom = '<"d-flex justify-content-between align-items-center mb-2"lB>frtip';
$.fn.dataTable.defaults.buttons = ['excel', 'csv', 'pdf', 'print'];
$.fn.dataTable.defaults.language = {
    search: '<i class="fas fa-search"></i>',
    searchPlaceholder: 'Search...',
    emptyTable: '<span class="text-secondary">No records found</span>'
};
</script>
</body>
</html>
