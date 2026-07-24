<?php declare(strict_types=1);
// Shared theme form fields — included in both create and edit modals.
// Edit-mode field values are populated dynamically by JS.
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label small text-secondary">Theme Name <span class="text-danger">*</span></label>
        <input type="text" name="name" value="" class="form-control bg-transparent text-light border-secondary" required placeholder="My Custom Theme">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Color Scheme</label>
        <select name="color_scheme" class="form-select bg-transparent text-light border-secondary">
            <option value="dark">Dark</option>
            <option value="light">Light</option>
            <option value="auto">Auto (follows system)</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label small text-secondary">Primary Color</label>
        <input type="color" name="primary_color" value="#3b82f6" class="form-control form-control-color w-100">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-secondary">Secondary Color</label>
        <input type="color" name="secondary_color" value="#64748b" class="form-control form-control-color w-100">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-secondary">Accent Color</label>
        <input type="color" name="accent_color" value="#f59e0b" class="form-control form-control-color w-100">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-secondary">Success Color</label>
        <input type="color" name="success_color" value="#10b981" class="form-control form-control-color w-100">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-secondary">Danger Color</label>
        <input type="color" name="danger_color" value="#ef4444" class="form-control form-control-color w-100">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-secondary">Background Color</label>
        <input type="color" name="bg_color" value="#0f172a" class="form-control form-control-color w-100">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-secondary">Surface Color</label>
        <input type="color" name="surface_color" value="#1e293b" class="form-control form-control-color w-100">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Font Family</label>
        <input type="text" name="font_family" value="Inter, system-ui, sans-serif" class="form-control bg-transparent text-light border-secondary" placeholder="Inter, system-ui, sans-serif">
    </div>
    <div class="col-md-3">
        <label class="form-label small text-secondary">Base Font Size</label>
        <input type="text" name="font_size_base" value="16px" class="form-control bg-transparent text-light border-secondary" placeholder="16px">
    </div>
    <div class="col-md-3">
        <label class="form-label small text-secondary">Border Radius</label>
        <input type="text" name="border_radius" value="0.5rem" class="form-control bg-transparent text-light border-secondary" placeholder="0.5rem">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Logo URL (override)</label>
        <input type="url" name="logo_url" value="" class="form-control bg-transparent text-light border-secondary" placeholder="Leave blank to use branding setting">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Favicon URL (override)</label>
        <input type="url" name="favicon_url" value="" class="form-control bg-transparent text-light border-secondary" placeholder="Leave blank to use branding setting">
    </div>
    <div class="col-12">
        <label class="form-label small text-secondary">Custom CSS</label>
        <textarea name="custom_css" class="form-control bg-transparent text-light border-secondary font-monospace" rows="5" placeholder="/* Custom CSS overrides for this theme */"></textarea>
    </div>
</div>
