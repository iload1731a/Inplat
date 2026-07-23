<?php declare(strict_types=1); ?>
<?php
$posts      = is_array($posts      ?? null) ? $posts      : [];
$categories = is_array($categories ?? null) ? $categories : [];
$recentPosts= is_array($recentPosts ?? null) ? $recentPosts : [];
$total      = (int)($total         ?? 0);
$page       = (int)($page          ?? 1);
$totalPages = (int)($totalPages    ?? 1);
$filters    = is_array($filters    ?? null) ? $filters    : [];
$isNews     = (bool)($isNews       ?? false);
$pageTitle  = $isNews ? 'News' : 'Blog';
?>
<div class="container py-5">
    <div class="row g-4">
        <!-- Main content -->
        <div class="col-xl-8">
            <h1 class="h2 mb-1"><?= $isNews ? '<i class="fas fa-newspaper me-2"></i>Latest News' : '<i class="fas fa-rss me-2"></i>Blog' ?></h1>
            <p class="text-secondary mb-4">
                <?= number_format($total) ?> articles
                <?php if ($filters['category'] ?? ''): ?>
                    in <strong><?= e((string)$filters['category']) ?></strong>
                <?php endif; ?>
            </p>

            <!-- Category Filter -->
            <?php if (!$isNews): ?>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="/blog" class="btn btn-sm <?= ($filters['category'] ?? '') === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="/blog?category=<?= urlencode((string)$cat['slug']) ?>"
                       class="btn btn-sm <?= ($filters['category'] ?? '') === (string)$cat['slug'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        <?= e((string)$cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Search -->
            <form method="GET" action="<?= $isNews ? '/news' : '/blog' ?>" class="mb-4">
                <div class="input-group">
                    <input type="text" name="search" class="form-control bg-dark text-light border-secondary"
                           placeholder="Search articles..." value="<?= e((string)($filters['search'] ?? '')) ?>">
                    <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>

            <!-- Post Grid -->
            <?php if ($posts === []): ?>
            <div class="text-center py-5 text-secondary">
                <i class="fas fa-search fa-3x mb-3 d-block opacity-25"></i>
                No articles found<?= ($filters['search'] ?? '') ? ' for "' . e((string)$filters['search']) . '"' : '' ?>.
            </div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($posts as $post): ?>
                <div class="col-md-6">
                    <div class="glass rounded-4 h-100 overflow-hidden">
                        <?php if ($post['featured_image'] ?? ''): ?>
                        <div class="ratio ratio-16x9" style="background:#0d0d1a">
                            <img src="<?= e((string)$post['featured_image']) ?>" class="w-100 h-100" style="object-fit:cover" alt="">
                        </div>
                        <?php endif; ?>
                        <div class="p-4">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <?php if ($post['category_name'] ?? ''): ?>
                                    <span class="badge text-bg-primary"><?= e((string)$post['category_name']) ?></span>
                                <?php endif; ?>
                                <span class="badge text-bg-secondary"><?= e(ucfirst((string)($post['post_type'] ?? 'blog'))) ?></span>
                                <span class="text-secondary small ms-auto">
                                    <i class="fas fa-clock me-1"></i><?= (int)($post['reading_time'] ?? 1) ?> min read
                                </span>
                            </div>
                            <h5 class="mb-2">
                                <a href="<?= $isNews ? '/news/post?slug=' : '/blog/post?slug=' ?><?= e((string)$post['slug']) ?>" class="text-light text-decoration-none">
                                    <?= e((string)($post['title'] ?? '-')) ?>
                                </a>
                            </h5>
                            <p class="text-secondary small mb-3 line-clamp-3">
                                <?= e(substr(strip_tags((string)($post['excerpt'] ?? '')), 0, 150)) ?>
                            </p>
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="small text-secondary">
                                    <i class="fas fa-user me-1"></i><?= e((string)($post['author_name'] ?? '')) ?>
                                </span>
                                <span class="small text-secondary"><?= e(substr((string)($post['published_at'] ?? ''), 0, 10)) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-center gap-1 mt-5">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter($filters)) ?>" class="btn btn-outline-secondary">‹ Prev</a>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <a href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"
                       class="btn <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter($filters)) ?>" class="btn btn-outline-secondary">Next ›</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-xl-4">
            <div class="glass rounded-4 p-4 mb-4">
                <h6 class="mb-3"><i class="fas fa-clock me-2 text-primary"></i>Recent Posts</h6>
                <?php foreach ($recentPosts as $rp): ?>
                <a href="/blog/post?slug=<?= e((string)$rp['slug']) ?>" class="d-flex gap-3 mb-3 text-decoration-none text-light">
                    <?php if ($rp['featured_image'] ?? ''): ?>
                    <img src="<?= e((string)$rp['featured_image']) ?>" class="rounded-2 flex-shrink-0"
                         style="width:64px;height:50px;object-fit:cover">
                    <?php endif; ?>
                    <div>
                        <div class="small fw-semibold line-clamp-2"><?= e((string)($rp['title'] ?? '-')) ?></div>
                        <div class="text-secondary" style="font-size:.7rem"><?= e(substr((string)($rp['published_at'] ?? ''), 0, 10)) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if (!$isNews && $categories !== []): ?>
            <div class="glass rounded-4 p-4">
                <h6 class="mb-3"><i class="fas fa-folder me-2 text-warning"></i>Categories</h6>
                <?php foreach ($categories as $cat): ?>
                <a href="/blog?category=<?= urlencode((string)$cat['slug']) ?>"
                   class="d-flex justify-content-between align-items-center text-decoration-none text-secondary py-1 border-bottom border-secondary">
                    <span><?= e((string)$cat['name']) ?></span>
                    <span class="badge text-bg-secondary"><?= (int)($cat['post_count'] ?? 0) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.line-clamp-3 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
</style>
