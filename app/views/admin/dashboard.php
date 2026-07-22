<?php declare(strict_types=1); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Admin Dashboard</h1>
    <form action="/logout" method="post">
        <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
        <button class="btn btn-outline-danger btn-sm" type="submit">Logout</button>
    </form>
</div>
<div class="row g-3">
    <div class="col-md-4">
        <div class="glass rounded-4 p-3">
            <div class="text-secondary">Welcome</div>
            <div class="h5 mb-0"><?= e($username ?? 'Admin') ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass rounded-4 p-3">
            <div class="text-secondary">Platform Status</div>
            <div class="h5 mb-0">Operational</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass rounded-4 p-3">
            <div class="text-secondary">Phase</div>
            <div class="h5 mb-0">Foundation</div>
        </div>
    </div>
</div>
