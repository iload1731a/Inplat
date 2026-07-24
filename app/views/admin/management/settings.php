<?php declare(strict_types=1); ?>
<?php $categories = is_array($categories ?? null) ? $categories : []; $settings = is_array($settings ?? null) ? $settings : []; $selectedCategory = (string)($selectedCategory ?? ''); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">System Settings</h1>
        <p class="text-secondary mb-0">Update global configuration safely with type-aware value validation.</p>
    </div>
    <a href="/admin/platform" class="btn btn-outline-warning btn-sm">Modules</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/settings">
        <div class="col-md-4">
            <select class="form-select" name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string)$category) ?>" <?= $selectedCategory === (string)$category ? 'selected' : '' ?>><?= e((string)$category) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
        <div class="col-md-2"><a class="btn btn-outline-light w-100" href="/admin/settings">Reset</a></div>
    </form>
</div>
<div class="glass rounded-4 p-3">
    <div class="table-responsive">
        <table class="table table-dark align-middle mb-0">
            <thead><tr><th>Key</th><th>Category</th><th>Type</th><th>Description</th><th style="min-width: 360px;">Value</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($settings as $row): ?>
                <tr>
                    <td><?= e((string)($row['setting_key'] ?? '-')) ?></td>
                    <td><?= e((string)($row['category'] ?? '-')) ?></td>
                    <td><?= e((string)($row['value_type'] ?? '-')) ?></td>
                    <td><?= e((string)($row['description'] ?? '-')) ?></td>
                    <td>
                        <form action="/admin/settings/update" method="post" data-ajax="true" class="d-flex gap-2">
                            <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                            <input type="hidden" name="setting_id" value="<?= (int)($row['id'] ?? 0) ?>">
                            <input type="hidden" name="value_type" value="<?= e((string)($row['value_type'] ?? 'string')) ?>">
                            <input type="hidden" name="category" value="<?= e((string)($selectedCategory !== '' ? $selectedCategory : ($row['category'] ?? ''))) ?>">
                            <textarea class="form-control form-control-sm" name="setting_value" rows="2"><?= e((string)($row['setting_value'] ?? '')) ?></textarea>
                            <button class="btn btn-sm btn-outline-info" type="submit">Save</button>
                        </form>
                    </td>
                    <td class="small text-secondary"><?= ((int)($row['is_public'] ?? 0) === 1) ? 'Public' : 'Private' ?><br><?= e((string)($row['updated_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($settings === []): ?><tr><td colspan="6" class="text-center text-secondary">No settings found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
