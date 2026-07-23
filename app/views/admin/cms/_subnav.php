<?php declare(strict_types=1); ?>
<?php
// CMS sub-navigation partial
$current = $adminSection ?? 'cms';
$items = [
    ['cms',              '/admin/cms',              'fa-tachometer-alt', 'Dashboard'],
    ['cms-pages',        '/admin/cms/pages',        'fa-file-alt',       'Pages'],
    ['cms-homepage',     '/admin/cms/homepage',     'fa-home',           'Homepage'],
    ['cms-blog',         '/admin/cms/blog',         'fa-rss',            'Blog & News'],
    ['cms-faq',          '/admin/cms/faq',          'fa-question-circle','FAQ'],
    ['cms-testimonials', '/admin/cms/testimonials', 'fa-star',           'Testimonials'],
    ['cms-features',     '/admin/cms/features',     'fa-bolt',           'Features'],
    ['cms-pricing',      '/admin/cms/pricing',      'fa-tag',            'Pricing'],
    ['cms-contact',      '/admin/cms/contact',      'fa-envelope',       'Contact'],
    ['cms-media',        '/admin/cms/media',        'fa-images',         'Media'],
    ['cms-seo',          '/admin/cms/seo',          'fa-search',         'SEO'],
    ['cms-sitemap',      '/admin/cms/sitemap',      'fa-sitemap',        'Sitemap'],
];
?>
<div class="glass rounded-4 p-2 mb-4">
    <div class="d-flex flex-wrap gap-1">
        <?php foreach ($items as [$key, $href, $icon, $label]):
            $active = $current === $key; ?>
        <a class="btn btn-xs <?= $active ? 'btn-info text-dark' : 'btn-outline-light' ?>" href="<?= e($href) ?>">
            <i class="fas <?= e($icon) ?> me-1"></i><?= e($label) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
