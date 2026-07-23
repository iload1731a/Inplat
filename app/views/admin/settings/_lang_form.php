<?php declare(strict_types=1); ?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label small text-secondary">Language Code <span class="text-danger">*</span></label>
        <input type="text" name="code" value="" class="form-control bg-transparent text-light border-secondary" required maxlength="10" placeholder="en">
        <div class="form-text text-secondary">ISO 639-1 code, e.g. <code>en</code>, <code>fr</code>, <code>zh</code></div>
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">English Name <span class="text-danger">*</span></label>
        <input type="text" name="name" value="" class="form-control bg-transparent text-light border-secondary" required maxlength="100" placeholder="English">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-secondary">Native Name <span class="text-danger">*</span></label>
        <input type="text" name="native_name" value="" class="form-control bg-transparent text-light border-secondary" required maxlength="100" placeholder="English">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-secondary">Flag Country Code</label>
        <input type="text" name="flag_code" value="" class="form-control bg-transparent text-light border-secondary" maxlength="5" placeholder="us">
        <div class="form-text text-secondary">ISO 3166-1 alpha-2 e.g. <code>us</code>, <code>fr</code></div>
    </div>
    <div class="col-md-2">
        <label class="form-label small text-secondary">Sort</label>
        <input type="number" name="sort_order" value="99" class="form-control bg-transparent text-light border-secondary" min="0">
    </div>
    <div class="col-md-3">
        <div class="form-check form-switch mt-4">
            <input type="checkbox" class="form-check-input" name="is_rtl" value="1" id="langRtl">
            <label class="form-check-label" for="langRtl">RTL Language</label>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-check form-switch mt-4">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" class="form-check-input" name="is_active" value="1" id="langActive" checked>
            <label class="form-check-label" for="langActive">Active</label>
        </div>
    </div>
</div>
