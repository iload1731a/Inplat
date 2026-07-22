<?php declare(strict_types=1); ?>
<?php
$announcements  = is_array($announcements ?? null) ? $announcements : [];
$banners        = is_array($banners ?? null) ? $banners : [];
$emailTemplates = is_array($emailTemplates ?? null) ? $emailTemplates : [];
$legalDocuments = is_array($legalDocuments ?? null) ? $legalDocuments : [];
$csrf           = \App\Libraries\Csrf::token();
// Group legal documents
$legalByType = [];
foreach ($legalDocuments as $doc) {
    $legalByType[(string)($doc['doc_type'] ?? 'page')][] = $doc;
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Content Management</h1>
        <p class="text-secondary mb-0">Manage announcements, banners, email templates, and pages.</p>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<ul class="nav nav-tabs border-secondary mb-4" role="tablist">
    <li class="nav-item"><button class="nav-link active text-light" data-bs-toggle="tab" data-bs-target="#tabAnnouncements">Announcements</button></li>
    <li class="nav-item"><button class="nav-link text-light" data-bs-toggle="tab" data-bs-target="#tabBanners">Banners</button></li>
    <li class="nav-item"><button class="nav-link text-light" data-bs-toggle="tab" data-bs-target="#tabEmailTemplates">Email Templates</button></li>
    <li class="nav-item"><button class="nav-link text-light" data-bs-toggle="tab" data-bs-target="#tabPages">Pages &amp; Docs</button></li>
</ul>

<div class="tab-content">

<!-- ANNOUNCEMENTS -->
<div class="tab-pane fade show active" id="tabAnnouncements">
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
            <i class="fas fa-plus me-1"></i> New Announcement
        </button>
    </div>
    <div class="glass rounded-4 p-3">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead><tr><th>Title</th><th>Type</th><th>Target</th><th>Active</th><th>Starts</th><th>Expires</th><th>Created By</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($announcements as $ann): ?>
                    <tr>
                        <td class="fw-semibold"><?= e((string)($ann['title'] ?? '-')) ?></td>
                        <td><span class="badge text-bg-<?= ['info'=>'info','warning'=>'warning','success'=>'success','danger'=>'danger','maintenance'=>'secondary'][$ann['type'] ?? ''] ?? 'secondary' ?>"><?= e(ucfirst((string)($ann['type'] ?? '-'))) ?></span></td>
                        <td><?= e(ucfirst((string)($ann['target_audience'] ?? '-'))) ?></td>
                        <td><span class="badge text-bg-<?= (int)($ann['is_active'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($ann['is_active'] ?? 0) ? 'Active' : 'Inactive' ?></span></td>
                        <td class="small text-secondary"><?= e((string)($ann['starts_at'] ?? '-')) ?></td>
                        <td class="small text-secondary"><?= e((string)($ann['expires_at'] ?? '-')) ?></td>
                        <td class="small"><?= e((string)($ann['created_by_name'] ?? '-')) ?></td>
                        <td class="text-end">
                            <button class="btn btn-xs btn-outline-light me-1" onclick="openEditAnnouncement(<?= e(json_encode($ann)) ?>)">Edit</button>
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteAnnouncement(<?= (int)$ann['id'] ?>)">Del</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($announcements === []): ?><tr><td colspan="8" class="text-center text-secondary">No announcements.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- BANNERS -->
<div class="tab-pane fade" id="tabBanners">
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createBannerModal">
            <i class="fas fa-plus me-1"></i> New Banner
        </button>
    </div>
    <div class="glass rounded-4 p-3">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead><tr><th>Title</th><th>Position</th><th>Order</th><th>Active</th><th>Starts</th><th>Expires</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($banners as $banner): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e((string)($banner['title'] ?? '-')) ?></div>
                            <div class="small text-secondary"><?= e((string)($banner['subtitle'] ?? '')) ?></div>
                        </td>
                        <td><?= e(ucfirst((string)($banner['position'] ?? '-'))) ?></td>
                        <td><?= (int)($banner['display_order'] ?? 0) ?></td>
                        <td><span class="badge text-bg-<?= (int)($banner['is_active'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($banner['is_active'] ?? 0) ? 'Active' : 'Off' ?></span></td>
                        <td class="small text-secondary"><?= e((string)($banner['starts_at'] ?? '-')) ?></td>
                        <td class="small text-secondary"><?= e((string)($banner['expires_at'] ?? '-')) ?></td>
                        <td class="text-end">
                            <button class="btn btn-xs btn-outline-light me-1" onclick="openEditBanner(<?= e(json_encode($banner)) ?>)">Edit</button>
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteBanner(<?= (int)$banner['id'] ?>)">Del</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($banners === []): ?><tr><td colspan="7" class="text-center text-secondary">No banners.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EMAIL TEMPLATES -->
<div class="tab-pane fade" id="tabEmailTemplates">
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createEmailTemplateModal">
            <i class="fas fa-plus me-1"></i> New Template
        </button>
    </div>
    <div class="glass rounded-4 p-3">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead><tr><th>Key</th><th>Subject</th><th>Active</th><th>Updated</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($emailTemplates as $tmpl): ?>
                    <tr>
                        <td><code class="text-info"><?= e((string)($tmpl['template_key'] ?? '-')) ?></code></td>
                        <td><?= e((string)($tmpl['subject'] ?? '-')) ?></td>
                        <td><span class="badge text-bg-<?= (int)($tmpl['is_active'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($tmpl['is_active'] ?? 0) ? 'Active' : 'Off' ?></span></td>
                        <td class="small text-secondary"><?= e((string)($tmpl['updated_at'] ?? '-')) ?></td>
                        <td class="text-end">
                            <button class="btn btn-xs btn-outline-light me-1" onclick="openEditEmailTemplate(<?= (int)$tmpl['id'] ?>, '<?= e((string)$tmpl['template_key']) ?>')">Edit</button>
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteEmailTemplate(<?= (int)$tmpl['id'] ?>)">Del</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($emailTemplates === []): ?><tr><td colspan="5" class="text-center text-secondary">No email templates.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- PAGES & DOCS -->
<div class="tab-pane fade" id="tabPages">
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createDocModal">
            <i class="fas fa-plus me-1"></i> New Page / Document
        </button>
    </div>
    <?php foreach ($legalByType as $docType => $docs): ?>
    <div class="glass rounded-4 p-3 mb-3">
        <h2 class="h6 mb-3 text-warning text-uppercase"><?= e(ucwords(str_replace('_', ' ', $docType))) ?></h2>
        <div class="table-responsive">
            <table class="table table-dark table-sm align-middle mb-0">
                <thead><tr><th>Title</th><th>Slug</th><th>Version</th><th>Active</th><th>Effective</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($docs as $doc): ?>
                    <tr>
                        <td class="fw-semibold"><?= e((string)($doc['title'] ?? '-')) ?></td>
                        <td><code class="small text-secondary">/<?= e((string)($doc['slug'] ?? '-')) ?></code></td>
                        <td><?= e((string)($doc['version'] ?? '-')) ?></td>
                        <td><span class="badge text-bg-<?= (int)($doc['is_active'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($doc['is_active'] ?? 0) ? 'Active' : 'Off' ?></span></td>
                        <td class="small text-secondary"><?= e((string)($doc['effective_date'] ?? '-')) ?></td>
                        <td class="text-end">
                            <button class="btn btn-xs btn-outline-light me-1" onclick="openEditDoc(<?= e(json_encode($doc)) ?>)">Edit</button>
                            <button class="btn btn-xs btn-outline-danger" onclick="deleteDoc(<?= (int)$doc['id'] ?>)">Del</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($legalDocuments === []): ?>
        <div class="glass rounded-4 p-4 text-center text-secondary">No pages or documents created yet.</div>
    <?php endif; ?>
</div>

</div><!-- /tab-content -->

<!-- ===== MODALS ===== -->

<!-- Create Announcement Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/content/announcement/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary"><h5 class="modal-title">New Announcement</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Title <span class="text-danger">*</span></label><input class="form-control" type="text" name="title" required></div>
                    <div class="col-md-4"><label class="form-label">Type</label>
                        <select class="form-select" name="type"><option value="info">Info</option><option value="warning">Warning</option><option value="success">Success</option><option value="danger">Danger</option><option value="maintenance">Maintenance</option></select></div>
                    <div class="col-md-4"><label class="form-label">Target Audience</label>
                        <select class="form-select" name="target_audience"><option value="all">All Users</option><option value="verified">Verified</option><option value="unverified">Unverified</option></select></div>
                    <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="annIsActive" checked><label class="form-check-label" for="annIsActive">Active</label></div></div>
                    <div class="col-md-6"><label class="form-label">Starts At</label><input class="form-control" type="datetime-local" name="starts_at"></div>
                    <div class="col-md-6"><label class="form-label">Expires At</label><input class="form-control" type="datetime-local" name="expires_at"></div>
                    <div class="col-12"><label class="form-label">Content</label><textarea class="form-control" name="content" rows="4"></textarea></div>
                </div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
        </form>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/content/announcement/update" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="announcement_id" id="editAnnId">
            <div class="modal-header border-secondary"><h5 class="modal-title">Edit Announcement</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Title</label><input class="form-control" type="text" name="title" id="editAnnTitle" required></div>
                    <div class="col-md-4"><label class="form-label">Type</label>
                        <select class="form-select" name="type" id="editAnnType"><option value="info">Info</option><option value="warning">Warning</option><option value="success">Success</option><option value="danger">Danger</option><option value="maintenance">Maintenance</option></select></div>
                    <div class="col-md-4"><label class="form-label">Target</label>
                        <select class="form-select" name="target_audience" id="editAnnTarget"><option value="all">All Users</option><option value="verified">Verified</option><option value="unverified">Unverified</option></select></div>
                    <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="editAnnActive"><label class="form-check-label" for="editAnnActive">Active</label></div></div>
                    <div class="col-md-6"><label class="form-label">Starts At</label><input class="form-control" type="datetime-local" name="starts_at" id="editAnnStarts"></div>
                    <div class="col-md-6"><label class="form-label">Expires At</label><input class="form-control" type="datetime-local" name="expires_at" id="editAnnExpires"></div>
                    <div class="col-12"><label class="form-label">Content</label><textarea class="form-control" name="content" id="editAnnContent" rows="4"></textarea></div>
                </div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<!-- Create Banner Modal -->
<div class="modal fade" id="createBannerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/content/banner/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary"><h5 class="modal-title">New Banner</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Title <span class="text-danger">*</span></label><input class="form-control" type="text" name="title" required></div>
                    <div class="col-md-4"><label class="form-label">Position</label>
                        <select class="form-select" name="position"><option value="home">Home</option><option value="trading">Trading</option><option value="dashboard">Dashboard</option><option value="login">Login</option></select></div>
                    <div class="col-12"><label class="form-label">Subtitle</label><input class="form-control" type="text" name="subtitle"></div>
                    <div class="col-md-6"><label class="form-label">Image URL</label><input class="form-control" type="url" name="image_url"></div>
                    <div class="col-md-6"><label class="form-label">Link URL</label><input class="form-control" type="url" name="link_url"></div>
                    <div class="col-md-4"><label class="form-label">Button Text</label><input class="form-control" type="text" name="button_text"></div>
                    <div class="col-md-4"><label class="form-label">Display Order</label><input class="form-control" type="number" name="display_order" value="0" min="0"></div>
                    <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked><label class="form-check-label">Active</label></div></div>
                    <div class="col-md-6"><label class="form-label">Starts At</label><input class="form-control" type="datetime-local" name="starts_at"></div>
                    <div class="col-md-6"><label class="form-label">Expires At</label><input class="form-control" type="datetime-local" name="expires_at"></div>
                </div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Create Banner</button></div>
        </form>
    </div>
</div>

<!-- Edit Banner Modal (same structure, JS fills it) -->
<div class="modal fade" id="editBannerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/content/banner/update" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="banner_id" id="editBannerId">
            <div class="modal-header border-secondary"><h5 class="modal-title">Edit Banner</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Title</label><input class="form-control" type="text" name="title" id="editBannerTitle" required></div>
                    <div class="col-md-4"><label class="form-label">Position</label>
                        <select class="form-select" name="position" id="editBannerPos"><option value="home">Home</option><option value="trading">Trading</option><option value="dashboard">Dashboard</option><option value="login">Login</option></select></div>
                    <div class="col-12"><label class="form-label">Subtitle</label><input class="form-control" type="text" name="subtitle" id="editBannerSubtitle"></div>
                    <div class="col-md-6"><label class="form-label">Image URL</label><input class="form-control" type="url" name="image_url" id="editBannerImg"></div>
                    <div class="col-md-6"><label class="form-label">Link URL</label><input class="form-control" type="url" name="link_url" id="editBannerLink"></div>
                    <div class="col-md-4"><label class="form-label">Button Text</label><input class="form-control" type="text" name="button_text" id="editBannerBtn"></div>
                    <div class="col-md-4"><label class="form-label">Display Order</label><input class="form-control" type="number" name="display_order" id="editBannerOrder" min="0"></div>
                    <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="editBannerActive"><label class="form-check-label" for="editBannerActive">Active</label></div></div>
                    <div class="col-md-6"><label class="form-label">Starts At</label><input class="form-control" type="datetime-local" name="starts_at" id="editBannerStarts"></div>
                    <div class="col-md-6"><label class="form-label">Expires At</label><input class="form-control" type="datetime-local" name="expires_at" id="editBannerExpires"></div>
                </div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<!-- Create Email Template Modal -->
<div class="modal fade" id="createEmailTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/content/email-template/save" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary"><h5 class="modal-title">New Email Template</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Template Key <span class="text-danger">*</span></label><input class="form-control" type="text" name="template_key" placeholder="e.g. verify_email" required></div>
                    <div class="col-md-5"><label class="form-label">Subject <span class="text-danger">*</span></label><input class="form-control" type="text" name="subject" required></div>
                    <div class="col-md-1 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked><label class="form-check-label">On</label></div></div>
                    <div class="col-12"><label class="form-label">HTML Body</label><textarea class="form-control font-monospace" name="body_html" rows="12" style="font-size:12px"></textarea></div>
                </div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Template</button></div>
        </form>
    </div>
</div>

<!-- Create Document Modal -->
<div class="modal fade" id="createDocModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/content/document/save" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="modal-header border-secondary"><h5 class="modal-title">New Page / Document</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Type</label>
                        <select class="form-select" name="doc_type"><option value="page">Page</option><option value="terms">Terms &amp; Conditions</option><option value="privacy">Privacy Policy</option><option value="faq">FAQ</option><option value="aml">AML Policy</option><option value="risk_disclaimer">Risk Disclaimer</option></select></div>
                    <div class="col-md-8"><label class="form-label">Title <span class="text-danger">*</span></label><input class="form-control" type="text" name="title" required></div>
                    <div class="col-md-4"><label class="form-label">Slug <span class="text-danger">*</span></label><input class="form-control" type="text" name="slug" placeholder="my-page" required></div>
                    <div class="col-md-4"><label class="form-label">Version</label><input class="form-control" type="text" name="version" value="1.0"></div>
                    <div class="col-md-2"><label class="form-label">Effective Date</label><input class="form-control" type="date" name="effective_date"></div>
                    <div class="col-md-2 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked><label class="form-check-label">Active</label></div></div>
                    <div class="col-12"><label class="form-label">Content (HTML)</label><textarea class="form-control font-monospace" name="content" rows="14" style="font-size:12px"></textarea></div>
                </div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<!-- Edit Document Modal -->
<div class="modal fade" id="editDocModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form class="modal-content bg-dark border-secondary" data-ajax="true" action="/admin/content/document/save" method="post">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" id="editDocId">
            <div class="modal-header border-secondary"><h5 class="modal-title">Edit Document</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Type</label>
                        <select class="form-select" name="doc_type" id="editDocType"><option value="page">Page</option><option value="terms">Terms</option><option value="privacy">Privacy</option><option value="faq">FAQ</option><option value="aml">AML</option><option value="risk_disclaimer">Risk Disclaimer</option></select></div>
                    <div class="col-md-8"><label class="form-label">Title</label><input class="form-control" type="text" name="title" id="editDocTitle" required></div>
                    <div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" type="text" name="slug" id="editDocSlug" required></div>
                    <div class="col-md-4"><label class="form-label">Version</label><input class="form-control" type="text" name="version" id="editDocVersion"></div>
                    <div class="col-md-2"><label class="form-label">Effective Date</label><input class="form-control" type="date" name="effective_date" id="editDocEffective"></div>
                    <div class="col-md-2 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="editDocActive"><label class="form-check-label" for="editDocActive">Active</label></div></div>
                    <div class="col-12"><label class="form-label">Content (HTML)</label><textarea class="form-control font-monospace" name="content" id="editDocContent" rows="14" style="font-size:12px"></textarea></div>
                </div>
            </div>
            <div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<!-- Delete forms -->
<form id="deleteAnnForm" data-ajax="true" action="/admin/content/announcement/delete" method="post" class="d-none"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="announcement_id" id="deleteAnnId"></form>
<form id="deleteBannerForm" data-ajax="true" action="/admin/content/banner/delete" method="post" class="d-none"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="banner_id" id="deleteBannerId"></form>
<form id="deleteEmailTemplateForm" data-ajax="true" action="/admin/content/email-template/delete" method="post" class="d-none"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="template_id" id="deleteTemplateId"></form>
<form id="deleteDocForm" data-ajax="true" action="/admin/content/document/delete" method="post" class="d-none"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="document_id" id="deleteDocId"></form>

<script>
function openEditAnnouncement(ann) {
    document.getElementById('editAnnId').value = ann.id;
    document.getElementById('editAnnTitle').value = ann.title || '';
    document.getElementById('editAnnType').value = ann.type || 'info';
    document.getElementById('editAnnTarget').value = ann.target_audience || 'all';
    document.getElementById('editAnnActive').checked = parseInt(ann.is_active) === 1;
    document.getElementById('editAnnContent').value = ann.content || '';
    if (ann.starts_at) document.getElementById('editAnnStarts').value = ann.starts_at.replace(' ', 'T').substring(0, 16);
    if (ann.expires_at) document.getElementById('editAnnExpires').value = ann.expires_at.replace(' ', 'T').substring(0, 16);
    new bootstrap.Modal(document.getElementById('editAnnouncementModal')).show();
}
function deleteAnnouncement(id) {
    if (!confirm('Delete announcement?')) return;
    document.getElementById('deleteAnnId').value = id;
    $('#deleteAnnForm').trigger('submit');
}
function openEditBanner(b) {
    document.getElementById('editBannerId').value = b.id;
    document.getElementById('editBannerTitle').value = b.title || '';
    document.getElementById('editBannerSubtitle').value = b.subtitle || '';
    document.getElementById('editBannerPos').value = b.position || 'home';
    document.getElementById('editBannerImg').value = b.image_url || '';
    document.getElementById('editBannerLink').value = b.link_url || '';
    document.getElementById('editBannerBtn').value = b.button_text || '';
    document.getElementById('editBannerOrder').value = b.display_order || 0;
    document.getElementById('editBannerActive').checked = parseInt(b.is_active) === 1;
    if (b.starts_at) document.getElementById('editBannerStarts').value = b.starts_at.replace(' ', 'T').substring(0, 16);
    if (b.expires_at) document.getElementById('editBannerExpires').value = b.expires_at.replace(' ', 'T').substring(0, 16);
    new bootstrap.Modal(document.getElementById('editBannerModal')).show();
}
function deleteBanner(id) {
    if (!confirm('Delete banner?')) return;
    document.getElementById('deleteBannerId').value = id;
    $('#deleteBannerForm').trigger('submit');
}
function openEditEmailTemplate(id, key) {
    // Open create modal pre-filled? For email templates with HTML body we keep it simple
    alert('Edit template: open the create modal to re-save with template_id=' + id + ' and key=' + key);
}
function deleteEmailTemplate(id) {
    if (!confirm('Delete email template?')) return;
    document.getElementById('deleteTemplateId').value = id;
    $('#deleteEmailTemplateForm').trigger('submit');
}
function openEditDoc(doc) {
    document.getElementById('editDocId').value = doc.id;
    document.getElementById('editDocType').value = doc.doc_type || 'page';
    document.getElementById('editDocTitle').value = doc.title || '';
    document.getElementById('editDocSlug').value = doc.slug || '';
    document.getElementById('editDocVersion').value = doc.version || '1.0';
    document.getElementById('editDocActive').checked = parseInt(doc.is_active) === 1;
    document.getElementById('editDocContent').value = doc.content || '';
    if (doc.effective_date) document.getElementById('editDocEffective').value = doc.effective_date.substring(0, 10);
    new bootstrap.Modal(document.getElementById('editDocModal')).show();
}
function deleteDoc(id) {
    if (!confirm('Delete document?')) return;
    document.getElementById('deleteDocId').value = id;
    $('#deleteDocForm').trigger('submit');
}
</script>
