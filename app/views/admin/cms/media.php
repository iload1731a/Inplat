<?php declare(strict_types=1); ?>
<?php
$items   = is_array($items   ?? null) ? $items   : [];
$total   = (int)($total      ?? 0);
$page    = (int)($page       ?? 1);
$perPage = (int)($perPage    ?? 40);
$folders = is_array($folders ?? null) ? $folders : [];
$filters = is_array($filters ?? null) ? $filters : [];
$csrf    = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-images me-2 text-info"></i>Media Library</h1>
        <p class="text-secondary mb-0"><?= number_format($total) ?> files</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="fas fa-upload me-1"></i> Upload Files
    </button>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<div class="row g-4">
    <!-- Sidebar Folders -->
    <div class="col-xl-2">
        <div class="glass rounded-4 p-3">
            <h6 class="mb-3 small text-secondary">Folders</h6>
            <a href="/admin/cms/media" class="d-block small py-1 text-decoration-none <?= ($filters['folder'] ?? '') === '' ? 'text-light fw-semibold' : 'text-secondary' ?>">
                <i class="fas fa-folder me-1"></i> All Files (<?= number_format($total) ?>)
            </a>
            <?php foreach ($folders as $f): ?>
            <a href="?folder=<?= urlencode((string)$f['folder']) ?>"
               class="d-block small py-1 text-decoration-none <?= ($filters['folder'] ?? '') === (string)$f['folder'] ? 'text-light fw-semibold' : 'text-secondary' ?>">
                <i class="fas fa-folder me-1"></i> <?= e((string)$f['folder']) ?> (<?= (int)$f['cnt'] ?>)
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Media Grid -->
    <div class="col-xl-10">
        <!-- Filters -->
        <div class="glass rounded-4 p-2 mb-3">
            <form method="GET" action="/admin/cms/media" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm bg-dark text-light border-secondary"
                           placeholder="Search files..." value="<?= e((string)($filters['search'] ?? '')) ?>">
                </div>
                <div class="col-md-3">
                    <select name="mime" class="form-select form-select-sm bg-dark text-light border-secondary">
                        <option value="">All Types</option>
                        <option value="image" <?= str_starts_with((string)($filters['mime'] ?? ''), 'image') ? 'selected' : '' ?>>Images</option>
                        <option value="application/pdf" <?= ($filters['mime'] ?? '') === 'application/pdf' ? 'selected' : '' ?>>PDF</option>
                        <option value="video" <?= str_starts_with((string)($filters['mime'] ?? ''), 'video') ? 'selected' : '' ?>>Video</option>
                    </select>
                </div>
                <?php if ($filters['folder'] ?? ''): ?>
                    <input type="hidden" name="folder" value="<?= e((string)$filters['folder']) ?>">
                <?php endif; ?>
                <div class="col-auto">
                    <button class="btn btn-sm btn-secondary" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>
        </div>

        <div class="row g-2">
            <?php foreach ($items as $file): ?>
            <?php $isImg = str_starts_with((string)($file['mime_type'] ?? ''), 'image/'); ?>
            <div class="col-xl-2 col-md-3 col-4">
                <div class="glass rounded-3 p-2 h-100 media-item" data-file='<?= e(json_encode($file)) ?>'>
                    <div class="ratio ratio-1x1 mb-1 rounded overflow-hidden bg-secondary d-flex align-items-center justify-content-center" style="background:#1a1a2e">
                        <?php if ($isImg): ?>
                            <img src="<?= e((string)($file['file_url'] ?? '')) ?>" class="w-100 h-100 object-fit-cover" alt="<?= e((string)($file['alt_text'] ?? '')) ?>"
                                 style="object-fit:cover">
                        <?php elseif (($file['mime_type'] ?? '') === 'application/pdf'): ?>
                            <div class="d-flex align-items-center justify-content-center h-100">
                                <i class="fas fa-file-pdf fa-2x text-danger"></i>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100">
                                <i class="fas fa-file fa-2x text-secondary"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="small text-secondary text-truncate" title="<?= e((string)($file['original_name'] ?? '')) ?>" style="font-size:.7rem">
                        <?= e((string)($file['original_name'] ?? '')) ?>
                    </div>
                    <div class="text-secondary" style="font-size:.65rem"><?= round((int)($file['file_size'] ?? 0) / 1024, 1) ?> KB</div>
                    <div class="d-flex gap-1 mt-1">
                        <button class="btn btn-xs btn-outline-info flex-grow-1" onclick="copyUrl('<?= e((string)($file['file_url'] ?? '')) ?>')" title="Copy URL">
                            <i class="fas fa-copy" style="font-size:.7rem"></i>
                        </button>
                        <button class="btn btn-xs btn-outline-danger" onclick="deleteFile(<?= (int)$file['id'] ?>)" title="Delete">
                            <i class="fas fa-trash" style="font-size:.7rem"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
            <div class="col-12 py-5 text-center text-secondary">
                <i class="fas fa-images fa-3x mb-3 d-block opacity-25"></i>
                No media files found. Upload your first file.
            </div>
            <?php endif; ?>
        </div>

        <?php if (ceil($total / $perPage) > 1): ?>
        <div class="d-flex justify-content-center mt-4">
            <?php
            $totalPages = (int)ceil($total / $perPage);
            for ($p = 1; $p <= min($totalPages, 10); $p++):
            ?>
                <a href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"
                   class="btn btn-xs <?= $p === $page ? 'btn-info' : 'btn-outline-light' ?> me-1"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Upload File</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small text-secondary">File (max 20 MB)</label>
                    <input type="file" id="uploadFile" class="form-control bg-dark text-light border-secondary"
                           accept="image/*,application/pdf,video/*">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Folder</label>
                    <input type="text" id="uploadFolder" class="form-control bg-dark text-light border-secondary"
                           value="general" list="folderList">
                    <datalist id="folderList">
                        <?php foreach ($folders as $f): ?>
                            <option value="<?= e((string)$f['folder']) ?>">
                        <?php endforeach; ?>
                        <option value="blog"><option value="pages"><option value="testimonials">
                    </datalist>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Alt Text</label>
                    <input type="text" id="uploadAlt" class="form-control bg-dark text-light border-secondary">
                </div>
                <div id="uploadProgress" class="d-none">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
                    </div>
                    <div class="text-center small mt-1 text-secondary">Uploading...</div>
                </div>
                <div id="uploadResult" class="d-none alert mt-2"></div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="doUpload()">
                    <i class="fas fa-upload me-1"></i> Upload
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const csrf = '<?= e($csrf) ?>';
function copyUrl(url) {
    navigator.clipboard.writeText(window.location.origin + url)
        .then(() => {
            const toast = document.createElement('div');
            toast.className = 'position-fixed bottom-0 end-0 p-3'; toast.style.zIndex = 9999;
            toast.innerHTML = `<div class="toast show bg-success text-white"><div class="toast-body">URL copied!</div></div>`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        });
}
function deleteFile(id) {
    if (!confirm('Delete this file?')) return;
    fetch('/admin/cms/media/delete', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, _token: csrf})
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
function doUpload() {
    const file = document.getElementById('uploadFile').files[0];
    if (!file) { alert('Please select a file.'); return; }
    const fd = new FormData();
    fd.append('file', file);
    fd.append('_token', csrf);
    fd.append('folder', document.getElementById('uploadFolder').value);
    fd.append('alt_text', document.getElementById('uploadAlt').value);

    document.getElementById('uploadProgress').classList.remove('d-none');
    document.getElementById('uploadResult').classList.add('d-none');

    fetch('/admin/cms/media/upload', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            document.getElementById('uploadProgress').classList.add('d-none');
            const res = document.getElementById('uploadResult');
            res.classList.remove('d-none', 'alert-success', 'alert-danger');
            if (d.ok) {
                res.classList.add('alert-success');
                res.textContent = 'Upload successful!';
                setTimeout(() => location.reload(), 1000);
            } else {
                res.classList.add('alert-danger');
                res.textContent = d.message || 'Upload failed.';
            }
        });
}
</script>
