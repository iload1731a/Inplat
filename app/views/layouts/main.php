<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(\App\Libraries\Csrf::token()) ?>">
    <title><?= e($title ?? config('app.name')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #e2e8f0; }
        .glass { background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(12px); border: 1px solid rgba(148, 163, 184, 0.15); }
        .container-narrow { max-width: 560px; }
        .navbar .btn { white-space: nowrap; }
        .btn-xs { padding: .1rem .4rem; font-size: .75rem; line-height: 1.4; }
    </style>
</head>
<body>
<?php require app_path('app/views/partials/demo_banner.php'); ?>
<?php
$authUserId = (int)(\App\Libraries\Session::get('auth.user_id') ?? 0);
$authAdminId = (int)(\App\Libraries\Session::get('auth.admin_id') ?? 0);
$authActorType = (string)(\App\Libraries\Session::get('auth.actor_type') ?? '');
$isAdmin = (bool)(\App\Libraries\Session::get('auth.is_admin') ?? false);
$isAuthenticated = $authUserId > 0 || $authAdminId > 0;
?>
<nav class="navbar navbar-expand-lg navbar-dark border-bottom border-secondary-subtle">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="/"><?= e(config('app.name')) ?></a>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <?php if ($isAuthenticated): ?>
                <?php if ($authActorType === 'admin'): ?>
                    <a href="/admin/dashboard" class="btn btn-outline-light btn-sm">Admin Dashboard</a>
                    <a href="/admin/platform" class="btn btn-outline-warning btn-sm">Admin Modules</a>
                <?php else: ?>
                    <a href="/dashboard" class="btn btn-outline-light btn-sm">User Dashboard</a>
                    <a href="/trading" class="btn btn-outline-info btn-sm">Trading</a>
                    <a href="/staking" class="btn btn-outline-success btn-sm">Staking</a>
                    <a href="/convert" class="btn btn-outline-warning btn-sm">Convert</a>
                    <?php if ($isAdmin): ?><a href="/admin/platform" class="btn btn-outline-warning btn-sm">Admin Modules</a><?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <a href="/login" class="btn btn-outline-light btn-sm">Login</a>
                <a href="/register" class="btn btn-primary btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="py-5">
    <div class="container">
        <?= $content ?>
    </div>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).on('submit', 'form[data-ajax="true"]', function (event) {
    event.preventDefault();

    const $form = $(this);
    const action = $form.attr('action');
    const method = ($form.attr('method') || 'POST').toUpperCase();

    $.ajax({
        url: action,
        method,
        data: $form.serialize(),
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success(response) {
            $form.trigger('ajax:success', [response]);
            if (response.message) {
                alert(response.message);
            }
            if (response.redirect) {
                window.location.href = response.redirect;
            }
        },
        error(xhr) {
            const payload = xhr.responseJSON || { message: 'Request failed' };
            $form.trigger('ajax:error', [payload]);
            alert(payload.message || JSON.stringify(payload.errors));
        }
    });
});
</script>
</body>
</html>
