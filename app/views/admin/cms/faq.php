<?php declare(strict_types=1); ?>
<?php
$categories = is_array($categories ?? null) ? $categories : [];
$faqs       = is_array($faqs       ?? null) ? $faqs       : [];
$csrf       = \App\Libraries\Csrf::token();
// Group FAQs by category
$grouped = [];
foreach ($faqs as $faq) {
    $grouped[(int)($faq['category_id'] ?? 0)][] = $faq;
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-question-circle me-2 text-warning"></i>FAQ Management</h1>
        <p class="text-secondary mb-0"><?= number_format(count($faqs)) ?> FAQ items</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#catModal" onclick="openCatModal(null)">
            <i class="fas fa-folder-plus me-1"></i> New Category
        </button>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#faqModal" onclick="openFaqModal(null)">
            <i class="fas fa-plus me-1"></i> New FAQ
        </button>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<!-- Categories -->
<div class="glass rounded-4 p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0">FAQ Categories</h6>
    </div>
    <div class="row g-2">
        <?php foreach ($categories as $cat): ?>
        <div class="col-md-3 col-6">
            <div class="glass rounded-3 p-2 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas <?= e((string)($cat['icon'] ?? 'fa-circle')) ?> text-warning small"></i>
                    <div>
                        <div class="small fw-semibold"><?= e((string)($cat['name'] ?? '')) ?></div>
                        <div class="text-secondary" style="font-size:.7rem"><?= (int)($cat['faq_count'] ?? 0) ?> FAQs</div>
                    </div>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-light" onclick="openCatModal(<?= e(json_encode($cat)) ?>)"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-xs btn-outline-danger" onclick="deleteCat(<?= (int)$cat['id'] ?>)"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- FAQ list grouped by category -->
<?php foreach ($categories as $cat):
    $catFaqs = $grouped[(int)$cat['id']] ?? [];
    if ($catFaqs === []) continue;
?>
<div class="glass rounded-4 p-3 mb-3">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="fas <?= e((string)($cat['icon'] ?? 'fa-circle')) ?> text-warning"></i>
        <h6 class="mb-0"><?= e((string)($cat['name'] ?? '')) ?></h6>
        <span class="badge text-bg-secondary"><?= count($catFaqs) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead><tr><th style="width:40%">Question</th><th>Active</th><th>Views</th><th>Helpful</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($catFaqs as $faq): ?>
                <tr>
                    <td class="fw-semibold small"><?= e((string)($faq['question'] ?? '-')) ?></td>
                    <td><span class="badge text-bg-<?= (int)($faq['is_active'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($faq['is_active'] ?? 0) ? 'Yes' : 'No' ?></span></td>
                    <td><?= number_format((int)($faq['view_count'] ?? 0)) ?></td>
                    <td class="small text-secondary">
                        <i class="fas fa-thumbs-up text-success me-1"></i><?= (int)($faq['helpful_yes'] ?? 0) ?>
                        / <i class="fas fa-thumbs-down text-danger me-1"></i><?= (int)($faq['helpful_no'] ?? 0) ?>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-outline-light me-1" onclick="openFaqModal(<?= e(json_encode($faq)) ?>)">Edit</button>
                        <button class="btn btn-xs btn-outline-danger" onclick="deleteFaq(<?= (int)$faq['id'] ?>)">Del</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<!-- FAQ Modal -->
<div class="modal fade" id="faqModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="faqModalTitle">FAQ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="faqId">
                <div class="mb-3">
                    <label class="form-label small text-secondary">Category</label>
                    <select id="faqCat" class="form-select bg-dark text-light border-secondary">
                        <option value="">— Uncategorised —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= e((string)$cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Question *</label>
                    <input type="text" id="faqQ" class="form-control bg-dark text-light border-secondary">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Answer *</label>
                    <textarea id="faqA" class="form-control bg-dark text-light border-secondary" rows="6"></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small text-secondary">Sort Order</label>
                        <input type="number" id="faqSort" class="form-control bg-dark text-light border-secondary" value="0">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="faqActive" checked>
                            <label class="form-check-label small" for="faqActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveFaq()">Save FAQ</button>
            </div>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">FAQ Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="catId">
                <div class="mb-3">
                    <label class="form-label small text-secondary">Name *</label>
                    <input type="text" id="catName" class="form-control bg-dark text-light border-secondary">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Slug</label>
                    <input type="text" id="catSlug" class="form-control bg-dark text-light border-secondary">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Icon (FontAwesome class)</label>
                    <input type="text" id="catIcon" class="form-control bg-dark text-light border-secondary" placeholder="fa-question-circle">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small text-secondary">Sort Order</label>
                        <input type="number" id="catSort" class="form-control bg-dark text-light border-secondary" value="0">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="catActive" checked>
                            <label class="form-check-label small" for="catActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveCat()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
const csrf = '<?= e($csrf) ?>';
function openFaqModal(faq) {
    document.getElementById('faqModalTitle').textContent = faq ? 'Edit FAQ' : 'New FAQ';
    document.getElementById('faqId').value   = faq?.id ?? '';
    document.getElementById('faqCat').value  = faq?.category_id ?? '';
    document.getElementById('faqQ').value    = faq?.question ?? '';
    document.getElementById('faqA').value    = faq?.answer ?? '';
    document.getElementById('faqSort').value = faq?.sort_order ?? 0;
    document.getElementById('faqActive').checked = faq ? !!+faq.is_active : true;
    new bootstrap.Modal(document.getElementById('faqModal')).show();
}
function saveFaq() {
    fetch('/admin/cms/faq/save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            _token: csrf, id: document.getElementById('faqId').value,
            category_id: document.getElementById('faqCat').value,
            question: document.getElementById('faqQ').value,
            answer: document.getElementById('faqA').value,
            sort_order: document.getElementById('faqSort').value,
            is_active: document.getElementById('faqActive').checked ? 1 : 0,
        })
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
function deleteFaq(id) {
    if (!confirm('Delete FAQ?')) return;
    fetch('/admin/cms/faq/delete', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, _token: csrf})
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
function openCatModal(cat) {
    document.getElementById('catId').value   = cat?.id ?? '';
    document.getElementById('catName').value = cat?.name ?? '';
    document.getElementById('catSlug').value = cat?.slug ?? '';
    document.getElementById('catIcon').value = cat?.icon ?? 'fa-question-circle';
    document.getElementById('catSort').value = cat?.sort_order ?? 0;
    document.getElementById('catActive').checked = cat ? !!+cat.is_active : true;
    new bootstrap.Modal(document.getElementById('catModal')).show();
}
function saveCat() {
    fetch('/admin/cms/faq/category/save', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            _token: csrf, id: document.getElementById('catId').value,
            name: document.getElementById('catName').value, slug: document.getElementById('catSlug').value,
            icon: document.getElementById('catIcon').value, sort_order: document.getElementById('catSort').value,
            is_active: document.getElementById('catActive').checked ? 1 : 0,
        })
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
function deleteCat(id) {
    if (!confirm('Delete category? FAQs will become uncategorised.')) return;
    fetch('/admin/cms/faq/category/delete', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, _token: csrf})
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.message); });
}
</script>
