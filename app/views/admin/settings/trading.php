<?php declare(strict_types=1); ?>
<?php
$settings = is_array($settings ?? null) ? $settings : [];
$s = fn(string $k, string $d = '') => (string)($settings[$k] ?? $d);
$csrf = \App\Libraries\Csrf::token();
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-chart-line me-2 text-info"></i>Trading Configuration</h1>
        <p class="text-secondary mb-0">Enable/disable trading modes, set default fees, order limits, and precision.</p>
    </div>
</div>

<form method="post" action="/admin/settings/trading" data-ajax="true">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">

    <div class="row g-4">
        <!-- Market Toggles -->
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-toggle-on me-2 text-secondary"></i>Market Toggles</h6>
                <?php
                $toggles = [
                    ['trading_enabled',          'Global Trading',        'Master switch for all trading activity.'],
                    ['spot_trading_enabled',      'Spot Trading',          'Enable spot market buy/sell orders.'],
                    ['margin_trading_enabled',    'Margin Trading',        'Enable leveraged margin positions.'],
                    ['futures_trading_enabled',   'Futures Trading',       'Enable futures/perpetual contracts.'],
                ];
                foreach ($toggles as [$key, $label, $desc]):
                ?>
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 border border-secondary-subtle mb-2">
                    <div>
                        <div class="fw-semibold"><?= e($label) ?></div>
                        <div class="small text-secondary"><?= e($desc) ?></div>
                    </div>
                    <div class="form-check form-switch mb-0 ms-3">
                        <input type="hidden" name="<?= e($key) ?>" value="false">
                        <input class="form-check-input" type="checkbox" name="<?= e($key) ?>" value="true"
                               <?= $s($key, 'true') === 'true' ? 'checked' : '' ?>>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Fee Configuration -->
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-percentage me-2 text-secondary"></i>Default Fee Configuration</h6>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Default Maker Fee</label>
                    <div class="input-group">
                        <input type="number" name="default_fee_maker" value="<?= e($s('default_fee_maker', '0.001')) ?>"
                               class="form-control bg-transparent text-light border-secondary" step="0.0001" min="0" max="1">
                        <span class="input-group-text border-secondary text-secondary">
                            (<?= round((float)$s('default_fee_maker', '0.001') * 100, 4) ?>%)
                        </span>
                    </div>
                    <div class="form-text text-secondary">Rate as decimal: 0.001 = 0.1%</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Default Taker Fee</label>
                    <div class="input-group">
                        <input type="number" name="default_fee_taker" value="<?= e($s('default_fee_taker', '0.001')) ?>"
                               class="form-control bg-transparent text-light border-secondary" step="0.0001" min="0" max="1">
                        <span class="input-group-text border-secondary text-secondary">
                            (<?= round((float)$s('default_fee_taker', '0.001') * 100, 4) ?>%)
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Limits & Precision -->
        <div class="col-lg-12">
            <div class="glass rounded-4 p-4">
                <h6 class="mb-4"><i class="fas fa-sliders-h me-2 text-secondary"></i>Order Limits & Display Precision</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small text-secondary">Max Open Orders Per User</label>
                        <input type="number" name="max_open_orders_per_user" value="<?= e($s('max_open_orders_per_user', '100')) ?>"
                               class="form-control bg-transparent text-light border-secondary" min="1" max="10000">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-secondary">Order Book Depth (levels)</label>
                        <input type="number" name="order_book_depth" value="<?= e($s('order_book_depth', '50')) ?>"
                               class="form-control bg-transparent text-light border-secondary" min="5" max="500">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-secondary">Default Price Precision (decimals)</label>
                        <input type="number" name="price_precision_default" value="<?= e($s('price_precision_default', '8')) ?>"
                               class="form-control bg-transparent text-light border-secondary" min="0" max="18">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-secondary">Default Quantity Precision (decimals)</label>
                        <input type="number" name="quantity_precision_default" value="<?= e($s('quantity_precision_default', '8')) ?>"
                               class="form-control bg-transparent text-light border-secondary" min="0" max="18">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary px-4" type="submit"><i class="fas fa-save me-1"></i>Save Trading Configuration</button>
        <a href="/admin/settings/hub" class="btn btn-outline-light">← Back to Hub</a>
    </div>
</form>
