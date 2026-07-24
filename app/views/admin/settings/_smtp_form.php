<?php declare(strict_types=1); ?>
<div class="row g-3">
    <div class="col-md-12">
        <label class="form-label small text-secondary">Profile Name <span class="text-danger">*</span></label>
        <input type="text" name="name" value="" class="form-control bg-transparent text-light border-secondary" required maxlength="100" placeholder="e.g. Primary, Transactional, Marketing">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">SMTP Host <span class="text-danger">*</span></label>
        <input type="text" name="host" value="" class="form-control bg-transparent text-light border-secondary" required placeholder="smtp.gmail.com">
    </div>
    <div class="col-md-3">
        <label class="form-label small text-secondary">Port</label>
        <input type="number" name="port" value="587" class="form-control bg-transparent text-light border-secondary" min="1" max="65535">
    </div>
    <div class="col-md-3">
        <label class="form-label small text-secondary">Encryption</label>
        <select name="encryption" class="form-select bg-transparent text-light border-secondary">
            <option value="tls">TLS (STARTTLS)</option>
            <option value="ssl">SSL</option>
            <option value="starttls">STARTTLS</option>
            <option value="none">None</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Username</label>
        <input type="text" name="username" value="" class="form-control bg-transparent text-light border-secondary" placeholder="your@email.com" autocomplete="off">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Password <span class="text-secondary small">(leave blank to keep existing)</span></label>
        <input type="password" name="password" value="" class="form-control bg-transparent text-light border-secondary" autocomplete="new-password">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">From Email <span class="text-danger">*</span></label>
        <input type="email" name="from_email" value="" class="form-control bg-transparent text-light border-secondary" required placeholder="noreply@yourplatform.com">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">From Name</label>
        <input type="text" name="from_name" value="Trading Platform" class="form-control bg-transparent text-light border-secondary" maxlength="150">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Reply-To Email</label>
        <input type="email" name="reply_to" value="" class="form-control bg-transparent text-light border-secondary" placeholder="support@yourplatform.com">
    </div>
    <div class="col-md-3">
        <label class="form-label small text-secondary">Max Per Minute</label>
        <input type="number" name="max_per_minute" value="60" class="form-control bg-transparent text-light border-secondary" min="1" max="1000">
    </div>
    <div class="col-md-3">
        <div class="form-check form-switch mt-4">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" class="form-check-input" name="is_active" value="1" id="smtpActive" checked>
            <label class="form-check-label" for="smtpActive">Active</label>
        </div>
    </div>
</div>
