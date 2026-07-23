<?php declare(strict_types=1); ?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label small text-secondary">Integration Name <span class="text-danger">*</span></label>
        <input type="text" name="name" value="" class="form-control bg-transparent text-light border-secondary" required maxlength="100" placeholder="e.g. Stripe Payments">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Provider Key <span class="text-danger">*</span></label>
        <input type="text" name="provider" value="" class="form-control bg-transparent text-light border-secondary" required maxlength="80" placeholder="e.g. stripe, sumsub, plaid">
        <div class="form-text text-secondary">Unique identifier slug, used in code.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Category</label>
        <select name="category" class="form-select bg-transparent text-light border-secondary">
            <option value="payment">Payment Gateway</option>
            <option value="kyc">KYC / Identity</option>
            <option value="analytics">Analytics</option>
            <option value="trading">Trading</option>
            <option value="social">Social / OAuth</option>
            <option value="other">Other</option>
        </select>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-3 mt-4">
            <div class="form-check form-switch">
                <input type="hidden" name="sandbox_mode" value="0">
                <input type="checkbox" class="form-check-input" name="sandbox_mode" value="1" id="apiSandbox" checked>
                <label class="form-check-label text-warning" for="apiSandbox">Sandbox / Test Mode</label>
            </div>
            <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" class="form-check-input" name="is_active" value="1" id="apiActive">
                <label class="form-check-label" for="apiActive">Active</label>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">API Key <span class="text-secondary small">(blank to keep existing)</span></label>
        <input type="password" name="api_key" value="" class="form-control bg-transparent text-light border-secondary" autocomplete="new-password" placeholder="pk_test_...">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">API Secret <span class="text-secondary small">(blank to keep existing)</span></label>
        <input type="password" name="api_secret" value="" class="form-control bg-transparent text-light border-secondary" autocomplete="new-password" placeholder="sk_test_...">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Webhook Secret <span class="text-secondary small">(blank to keep existing)</span></label>
        <input type="password" name="webhook_secret" value="" class="form-control bg-transparent text-light border-secondary" autocomplete="new-password">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Extra Config (JSON)</label>
        <textarea name="extra_config" class="form-control bg-transparent text-light border-secondary font-monospace" rows="2" placeholder='{"region":"us-east-1"}'></textarea>
    </div>
    <div class="col-md-12">
        <label class="form-label small text-secondary">Notes</label>
        <textarea name="notes" class="form-control bg-transparent text-light border-secondary" rows="2" maxlength="1000" placeholder="Internal notes about this integration..."></textarea>
    </div>
</div>
