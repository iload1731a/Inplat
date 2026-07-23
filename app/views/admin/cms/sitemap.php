<?php declare(strict_types=1); ?>
<?php
$xml     = (string)($xml     ?? '');
$siteUrl = (string)($siteUrl ?? '');
$urlCount = substr_count($xml, '<url>');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-sitemap me-2 text-success"></i>Sitemap Generator</h1>
        <p class="text-secondary mb-0"><?= $urlCount ?> URLs in sitemap · Base: <?= e($siteUrl) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/cms/sitemap/download" class="btn btn-sm btn-success">
            <i class="fas fa-download me-1"></i> Download sitemap.xml
        </a>
        <a href="/sitemap.xml" target="_blank" class="btn btn-sm btn-outline-info">
            <i class="fas fa-external-link-alt me-1"></i> View Live
        </a>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>
<?php require app_path('app/views/admin/cms/_subnav.php'); ?>

<div class="glass rounded-4 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0">Sitemap Preview</h6>
        <span class="badge text-bg-success"><?= $urlCount ?> URLs</span>
    </div>
    <pre class="bg-dark text-light p-3 rounded border border-secondary" style="max-height:500px;overflow:auto;font-size:.75rem;line-height:1.4"><?= e($xml) ?></pre>
</div>

<div class="glass rounded-4 p-4">
    <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-info"></i>Sitemap Configuration</h6>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="glass rounded-3 p-3">
                <div class="small text-secondary mb-1">Static Pages</div>
                <div class="fw-bold">7 URLs</div>
                <div class="text-secondary small">Home, Blog, FAQ, Contact, Markets, Register, Login</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass rounded-3 p-3">
                <div class="small text-secondary mb-1">CMS Pages</div>
                <div class="fw-bold"><?= substr_count($xml, '/page/') ?> URLs</div>
                <div class="text-secondary small">Published CMS pages</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass rounded-3 p-3">
                <div class="small text-secondary mb-1">Blog / News Posts</div>
                <div class="fw-bold"><?= substr_count($xml, '/blog/') + substr_count($xml, '/news/') ?> URLs</div>
                <div class="text-secondary small">Published blog and news posts</div>
            </div>
        </div>
    </div>

    <div class="alert alert-info alert-dismissible mt-3 mb-0">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Submit your sitemap</strong> to search engines:
        <a href="https://search.google.com/search-console" target="_blank" class="alert-link">Google Search Console</a>
        and
        <a href="https://www.bing.com/webmasters" target="_blank" class="alert-link">Bing Webmaster Tools</a>.
        Your sitemap URL: <code><?= e($siteUrl) ?>/sitemap.xml</code>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
