<?php declare(strict_types=1); ?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label small text-secondary">Profile Name <span class="text-danger">*</span></label>
        <input type="text" name="name" value="" class="form-control bg-transparent text-light border-secondary" required maxlength="100" placeholder="e.g. Twilio Primary">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Provider</label>
        <select name="provider" class="form-select bg-transparent text-light border-secondary" id="smsProviderSelect" onchange="toggleSmsFields(this.value)">
            <option value="twilio">Twilio</option>
            <option value="nexmo">Vonage (Nexmo)</option>
            <option value="aws_sns">AWS SNS</option>
            <option value="msg91">MSG91</option>
            <option value="custom">Custom / HTTP API</option>
        </select>
    </div>
    <div class="col-md-6" id="smsSidRow">
        <label class="form-label small text-secondary">Account SID / API Key</label>
        <input type="text" name="account_sid" value="" class="form-control bg-transparent text-light border-secondary" autocomplete="off" placeholder="Account SID">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Auth Token / Secret <span class="text-secondary small">(blank to keep existing)</span></label>
        <input type="password" name="auth_token" value="" class="form-control bg-transparent text-light border-secondary" autocomplete="new-password">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-secondary">From Number</label>
        <input type="text" name="from_number" value="" class="form-control bg-transparent text-light border-secondary" placeholder="+15550000000">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-secondary">Sender ID</label>
        <input type="text" name="sender_id" value="" class="form-control bg-transparent text-light border-secondary" maxlength="15" placeholder="TRADING">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-secondary">Max Per Minute</label>
        <input type="number" name="max_per_minute" value="30" class="form-control bg-transparent text-light border-secondary" min="1" max="1000">
    </div>
    <div class="col-md-12" id="smsEndpointRow" style="display:none">
        <label class="form-label small text-secondary">Custom API Endpoint</label>
        <input type="url" name="api_endpoint" value="" class="form-control bg-transparent text-light border-secondary" placeholder="https://api.custom-sms.com/send">
    </div>
    <div class="col-md-3">
        <div class="form-check form-switch mt-4">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" class="form-check-input" name="is_active" value="1" id="smsActive" checked>
            <label class="form-check-label" for="smsActive">Active</label>
        </div>
    </div>
</div>
<script>
function toggleSmsFields(provider) {
    document.getElementById('smsEndpointRow').style.display = provider === 'custom' ? '' : 'none';
}
</script>
