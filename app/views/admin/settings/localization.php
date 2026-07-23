<?php declare(strict_types=1); ?>
<?php
$settings  = is_array($settings ?? null) ? $settings : [];
$timezones = is_array($timezones ?? null) ? $timezones : [];
$languages = is_array($languages ?? null) ? $languages : [];
$s = fn(string $k, string $d = '') => (string)($settings[$k] ?? $d);
$csrf = \App\Libraries\Csrf::token();
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-language me-2 text-info"></i>Localization Settings</h1>
        <p class="text-secondary mb-0">Timezone, date/time formats, default currency display, and number formatting.</p>
    </div>
</div>

<form method="post" action="/admin/settings/localization" data-ajax="true">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-clock me-2 text-secondary"></i>Timezone & Language</h6>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Default Timezone</label>
                    <select name="default_timezone" class="form-select bg-transparent text-light border-secondary">
                        <?php foreach ($timezones as $tz): ?>
                            <option value="<?= e($tz) ?>" <?= $s('default_timezone', 'UTC') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text text-secondary">Server and display timezone for dates/times across the platform.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Default Language</label>
                    <select name="default_language" class="form-select bg-transparent text-light border-secondary">
                        <?php foreach ($languages as $lang): ?>
                            <option value="<?= e((string)$lang['code']) ?>"
                                    <?= $s('default_language', 'en') === (string)$lang['code'] ? 'selected' : '' ?>>
                                <?= e((string)$lang['name']) ?> (<?= e((string)$lang['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                        <?php if ($languages === []): ?>
                            <option value="en">English (en)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Default Display Currency</label>
                    <input type="text" name="default_currency_display" value="<?= e($s('default_currency_display', 'USD')) ?>"
                           class="form-control bg-transparent text-light border-secondary" maxlength="10" placeholder="USD">
                    <div class="form-text text-secondary">Fiat currency used for USD-equivalent display values across the UI.</div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-calendar me-2 text-secondary"></i>Date, Time & Number Formats</h6>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Date Format (PHP)</label>
                    <input type="text" name="default_date_format" value="<?= e($s('default_date_format', 'Y-m-d')) ?>"
                           class="form-control bg-transparent text-light border-secondary font-monospace" maxlength="30" placeholder="Y-m-d">
                    <div class="form-text text-secondary">
                        PHP date() format. Examples: <code>Y-m-d</code> → <?= date('Y-m-d') ?>; <code>d/m/Y</code> → <?= date('d/m/Y') ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Time Format (PHP)</label>
                    <input type="text" name="default_time_format" value="<?= e($s('default_time_format', 'H:i:s')) ?>"
                           class="form-control bg-transparent text-light border-secondary font-monospace" maxlength="20" placeholder="H:i:s">
                    <div class="form-text text-secondary">
                        Examples: <code>H:i:s</code> → <?= date('H:i:s') ?>; <code>h:i A</code> → <?= date('h:i A') ?>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small text-secondary">Decimal Separator</label>
                        <input type="text" name="number_decimal_separator" value="<?= e($s('number_decimal_separator', '.')) ?>"
                               class="form-control bg-transparent text-light border-secondary" maxlength="1" placeholder=".">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-secondary">Thousands Separator</label>
                        <input type="text" name="number_thousands_separator" value="<?= e($s('number_thousands_separator', ',')) ?>"
                               class="form-control bg-transparent text-light border-secondary" maxlength="1" placeholder=",">
                    </div>
                </div>
                <div class="form-text text-secondary mt-1">
                    Preview: <code id="numPreview">1,234,567.89</code>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary px-4" type="submit"><i class="fas fa-save me-1"></i>Save Localization</button>
        <a href="/admin/settings/hub" class="btn btn-outline-light">← Back to Hub</a>
    </div>
</form>
