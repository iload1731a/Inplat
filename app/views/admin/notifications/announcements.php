<?php declare(strict_types=1); ?>
<?php
$list       = is_array($list    ?? null) ? $list    : [];
$rows       = is_array($list['rows'] ?? null) ? $list['rows'] : [];
$total      = (int)($list['total']       ?? 0);
$totalPages = (int)($list['total_pages'] ?? 1);
$currentPage= (int)($list['page']        ?? 1);

$categories = [
    'maintenance' => ['label' => 'Maintenance',  'color' => 'warning'],
    'new_listing' => ['label' => 'New Listing',  'color' => 'success'],
    'delisting'   => ['label' => 'Delisting',    'color' => 'danger'],
    'promotion'   => ['label' => 'Promotion',    'color' => 'primary'],
    'security'    => ['label' => 'Security',     'color' => 'danger'],
    'general'     => ['label' => 'General',      'color' => 'info'],
];
?>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- CREATE MODAL TRIGGER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-bullhorn me-2 text-warning"></i>Announcements</h4>
        <p class="text-secondary small mb-0">Platform-wide announcements displayed in the user notification center.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="fas fa-plus me-1"></i>New Announcement
    </button>
</div>

<!-- ANNOUNCEMENT TABLE -->
<div class="glass rounded-4 p-4">
    <?php if ($rows === []): ?>
    <div class="text-center py-5 text-secondary">
        <i class="fas fa-bullhorn fa-3x mb-3 d-block"></i>
        <div>No announcements yet. Create your first one!</div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle small">
            <thead class="table-dark">
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Pinned</th>
                    <th>Reads</th>
                    <th>Published</th>
                    <th>Published At</th>
                    <th>Created</th>
                    <th style="width:140px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $ann):
                $cat      = (string)($ann['category'] ?? 'general');
                $catColor = $categories[$cat]['color']  ?? 'info';
                $catLabel = $categories[$cat]['label']  ?? ucfirst($cat);
                $isPub    = (bool)($ann['is_published'] ?? false);
            ?>
            <tr>
                <td>
                    <div class="fw-medium"><?= e((string)($ann['title'] ?? '')) ?></div>
                    <div class="text-secondary" style="font-size:.7rem"><?= e((string)($ann['created_by_name'] ?? '')) ?></div>
                </td>
                <td><span class="badge text-bg-<?= $catColor ?>"><?= e($catLabel) ?></span></td>
                <td>
                    <?= (bool)($ann['is_pinned'] ?? false)
                        ? '<i class="fas fa-thumbtack text-warning"></i>'
                        : '<i class="fas fa-thumbtack text-secondary opacity-25"></i>' ?>
                </td>
                <td><?= number_format((int)($ann['read_count'] ?? 0)) ?></td>
                <td>
                    <button class="btn btn-xs <?= $isPub ? 'btn-success' : 'btn-outline-secondary' ?> toggle-publish-btn"
                            data-id="<?= (int)$ann['id'] ?>">
                        <?= $isPub ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>' ?>
                    </button>
                </td>
                <td class="text-secondary">
                    <?= !empty($ann['published_at']) ? e(date('M d, Y', strtotime((string)$ann['published_at']))) : '—' ?>
                </td>
                <td class="text-secondary"><?= e(date('M d, Y', strtotime((string)($ann['created_at'] ?? 'now')))) ?></td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn btn-xs btn-outline-info edit-ann-btn" data-ann='<?= json_encode($ann, JSON_HEX_APOS | JSON_HEX_TAG) ?>'
                                data-bs-toggle="modal" data-bs-target="#editModal">
                            <i class="fas fa-edit fa-xs"></i>
                        </button>
                        <button class="btn btn-xs btn-outline-danger delete-ann-btn" data-id="<?= (int)$ann['id'] ?>">
                            <i class="fas fa-trash fa-xs"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center mb-0">
            <?php if ($currentPage > 1): ?>
            <li class="page-item"><a class="page-link" href="?page=<?= $currentPage - 1 ?>">«</a></li>
            <?php endif; ?>
            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
            <li class="page-item"><a class="page-link" href="?page=<?= $currentPage + 1 ?>">»</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- CREATE MODAL -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-bullhorn me-2 text-warning"></i>New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="/admin/notifications/announcements/create" method="post" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="modal-body">
                    <?php require __DIR__ . '/_ann_form_fields.php'; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2 text-info"></i>Edit Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="/admin/notifications/announcements/update" method="post" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <input type="hidden" name="id" id="editAnnId">
                <div class="modal-body" id="editModalBody">
                    <?php require __DIR__ . '/_ann_form_fields.php'; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= e(\App\Libraries\Csrf::token()) ?>';

// Populate edit modal
$(document).on('click', '.edit-ann-btn', function () {
    const ann = JSON.parse(this.dataset.ann);
    const modal = document.getElementById('editModal');
    document.getElementById('editAnnId').value = ann.id;

    const body = document.getElementById('editModalBody');
    body.querySelector('[name="title"]').value        = ann.title ?? '';
    body.querySelector('[name="body"]').value         = ann.body ?? '';
    body.querySelector('[name="category"]').value     = ann.category ?? 'general';
    body.querySelector('[name="is_pinned"]').checked  = !!parseInt(ann.is_pinned);
    body.querySelector('[name="is_published"]').checked = !!parseInt(ann.is_published);
    body.querySelector('[name="published_at"]').value = ann.published_at ? ann.published_at.substring(0, 16) : '';
});

// Toggle publish
$(document).on('click', '.toggle-publish-btn', function () {
    const id  = $(this).data('id');
    const btn = $(this);
    $.post('/admin/notifications/announcements/toggle', { _token: csrfToken, id }, res => {
        if (res.ok) {
            btn.toggleClass('btn-success btn-outline-secondary');
            btn.html(res.published ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>');
            toastSuccess(res.message);
        }
    });
});

// Delete
$(document).on('click', '.delete-ann-btn', function () {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Delete this announcement?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#ef4444',
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post('/admin/notifications/announcements/delete', { _token: csrfToken, id }, res => {
            if (res.ok) location.reload();
        });
    });
});

function toastSuccess(msg) {
    Swal.fire({ icon: 'success', title: msg, timer: 1500, showConfirmButton: false });
}
</script>
