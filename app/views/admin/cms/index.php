<?php declare(strict_types=1); ?>
<?php
$kpis          = is_array($kpis          ?? null) ? $kpis          : [];
$recentPosts   = is_array($recentPosts   ?? null) ? $recentPosts   : [];
$homepageSections = is_array($homepageSections ?? null) ? $homepageSections : [];
$csrf          = \App\Libraries\Csrf::token();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-globe me-2 text-info"></i>CMS Dashboard</h1>
        <p class="text-secondary mb-0">Manage website content, blog, SEO and media.</p>
    </div>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- CMS Sub-nav -->
<div class="glass rounded-4 p-2 mb-4">
    <div class="d-flex flex-wrap gap-1">
        <?php
        $items = [
            ['cms',              '/admin/cms',                    'fa-tachometer-alt', 'Dashboard'],
            ['cms-pages',        '/admin/cms/pages',              'fa-file-alt',       'Pages'],
            ['cms-homepage',     '/admin/cms/homepage',           'fa-home',           'Homepage Builder'],
            ['cms-blog',         '/admin/cms/blog',               'fa-rss',            'Blog & News'],
            ['cms-faq',          '/admin/cms/faq',                'fa-question-circle','FAQ'],
            ['cms-testimonials', '/admin/cms/testimonials',       'fa-star',           'Testimonials'],
            ['cms-features',     '/admin/cms/features',           'fa-bolt',           'Features'],
            ['cms-pricing',      '/admin/cms/pricing',            'fa-tag',            'Pricing'],
            ['cms-contact',      '/admin/cms/contact',            'fa-envelope',       'Contact'],
            ['cms-media',        '/admin/cms/media',              'fa-images',         'Media Library'],
            ['cms-seo',          '/admin/cms/seo',                'fa-search',         'SEO'],
            ['cms-sitemap',      '/admin/cms/sitemap',            'fa-sitemap',        'Sitemap'],
        ];
        $current = $adminSection ?? 'cms';
        foreach ($items as [$key, $href, $icon, $label]):
            $active = $current === $key;
        ?>
        <a class="btn btn-xs <?= $active ? 'btn-info text-dark' : 'btn-outline-light' ?>" href="<?= e($href) ?>">
            <i class="fas <?= e($icon) ?> me-1"></i><?= e($label) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Pages',        (int)($kpis['total_pages']       ?? 0),  'info',    'fa-file-alt'],
        ['Published Pages',    (int)($kpis['published_pages']   ?? 0),  'success', 'fa-check-circle'],
        ['Blog Posts',         (int)($kpis['total_posts']       ?? 0),  'primary', 'fa-rss'],
        ['Published Posts',    (int)($kpis['published_posts']   ?? 0),  'success', 'fa-check'],
        ['FAQs Active',        (int)($kpis['total_faqs']        ?? 0),  'warning', 'fa-question-circle'],
        ['Testimonials',       (int)($kpis['total_testimonials']?? 0),  'info',    'fa-star'],
        ['Media Files',        (int)($kpis['total_media']       ?? 0),  'secondary','fa-images'],
        ['Unread Messages',    (int)($kpis['unread_contacts']   ?? 0),  'danger',  'fa-envelope'],
    ];
    foreach ($cards as [$label, $val, $color, $icon]):
    ?>
    <div class="col-xl-3 col-md-4 col-6">
        <div class="glass rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-3 d-flex align-items-center justify-content-center"
                     style="width:38px;height:38px;background:rgba(var(--bs-<?= $color ?>-rgb),.15)">
                    <i class="fas <?= $icon ?> text-<?= $color ?>" style="font-size:.9rem"></i>
                </div>
                <div>
                    <div class="fw-bold"><?= number_format($val) ?></div>
                    <div class="text-secondary" style="font-size:.75rem"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Quick Links + Recent Posts -->
<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <div class="glass rounded-4 p-4 h-100">
            <h6 class="mb-3"><i class="fas fa-bolt me-2 text-warning"></i>Quick Actions</h6>
            <div class="d-grid gap-2">
                <a href="/admin/cms/pages/create" class="btn btn-sm btn-outline-info">
                    <i class="fas fa-plus me-1"></i> New CMS Page
                </a>
                <a href="/admin/cms/blog/create" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-pen me-1"></i> New Blog Post
                </a>
                <a href="/admin/cms/media" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-upload me-1"></i> Upload Media
                </a>
                <a href="/admin/cms/faq" class="btn btn-sm btn-outline-warning">
                    <i class="fas fa-plus me-1"></i> Add FAQ
                </a>
                <a href="/admin/cms/contact" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-envelope me-1"></i> View Contact Messages
                </a>
                <a href="/admin/cms/sitemap" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-sitemap me-1"></i> Generate Sitemap
                </a>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="glass rounded-4 p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-rss me-2 text-primary"></i>Recent Blog Posts</h6>
                <a href="/admin/cms/blog" class="btn btn-xs btn-outline-secondary">All Posts</a>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead><tr><th>Title</th><th>Status</th><th>Views</th><th>Published</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentPosts as $post): ?>
                        <tr>
                            <td>
                                <a href="/admin/cms/blog/edit?id=<?= (int)$post['id'] ?>" class="text-light text-decoration-none fw-semibold">
                                    <?= e((string)($post['title'] ?? '-')) ?>
                                </a>
                            </td>
                            <td><span class="badge text-bg-success">Published</span></td>
                            <td><?= number_format((int)($post['view_count'] ?? 0)) ?></td>
                            <td class="small text-secondary"><?= e(substr((string)($post['published_at'] ?? '-'), 0, 10)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentPosts === []): ?>
                        <tr><td colspan="4" class="text-center text-secondary">No published posts.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Homepage Sections Status -->
<div class="glass rounded-4 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fas fa-home me-2 text-info"></i>Homepage Sections</h6>
        <a href="/admin/cms/homepage" class="btn btn-xs btn-outline-info">Manage</a>
    </div>
    <div class="row g-2">
        <?php foreach ($homepageSections as $sec): ?>
        <div class="col-md-3 col-6">
            <div class="d-flex align-items-center gap-2 glass rounded-3 p-2">
                <span class="badge text-bg-<?= (int)($sec['is_enabled'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($sec['is_enabled'] ?? 0) ? 'On' : 'Off' ?></span>
                <span class="small"><?= e((string)($sec['section_title'] ?? '')) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
