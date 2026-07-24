<?php declare(strict_types=1);
// Shared form fields for create/edit announcement modals — included by announcements.php
$editTitle    = '';
$editBody     = '';
$editCategory = 'general';
$editPinned   = false;
$editPub      = false;
$editPubAt    = '';
?>
<div class="row g-3">
    <div class="col-12">
        <label class="form-label">Title <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="title" value="<?= e($editTitle) ?>" required>
    </div>
    <div class="col-12">
        <label class="form-label">Body <span class="text-danger">*</span></label>
        <textarea class="form-control" name="body" rows="6" required><?= e($editBody) ?></textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label">Category</label>
        <select class="form-select" name="category">
            <?php foreach (['maintenance'=>'Maintenance','new_listing'=>'New Listing','delisting'=>'Delisting','promotion'=>'Promotion','security'=>'Security','general'=>'General'] as $val => $lbl): ?>
            <option value="<?= e($val) ?>" <?= $editCategory === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Publish At <small class="text-secondary">(optional)</small></label>
        <input type="datetime-local" class="form-control" name="published_at" value="<?= e($editPubAt) ?>">
    </div>
    <div class="col-md-4 d-flex align-items-end gap-3 pb-1">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_pinned" id="isPinnedCheck" <?= $editPinned ? 'checked' : '' ?>>
            <label class="form-check-label" for="isPinnedCheck">Pin to top</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_published" id="isPublishedCheck" <?= $editPub ? 'checked' : '' ?>>
            <label class="form-check-label" for="isPublishedCheck">Publish now</label>
        </div>
    </div>
</div>
