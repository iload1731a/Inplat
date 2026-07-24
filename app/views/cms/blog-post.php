<?php declare(strict_types=1); ?>
<?php
$post    = is_array($post    ?? null) ? $post    : [];
$related = is_array($related ?? null) ? $related : [];
if ($post === []) { http_response_code(404); exit; }
?>
<div class="container py-5">
    <div class="row g-4">
        <!-- Article -->
        <div class="col-xl-8">
            <!-- Breadcrumb -->
            <nav class="mb-3">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="/" class="text-secondary">Home</a></li>
                    <li class="breadcrumb-item">
                        <a href="<?= (string)($post['post_type'] ?? 'blog') === 'news' ? '/news' : '/blog' ?>" class="text-secondary">
                            <?= (string)($post['post_type'] ?? 'blog') === 'news' ? 'News' : 'Blog' ?>
                        </a>
                    </li>
                    <?php if ($post['category_name'] ?? ''): ?>
                    <li class="breadcrumb-item">
                        <a href="/blog?category=<?= urlencode((string)$post['category_slug']) ?>" class="text-secondary">
                            <?= e((string)$post['category_name']) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active text-secondary"><?= e(substr((string)($post['title'] ?? ''), 0, 40)) ?>...</li>
                </ol>
            </nav>

            <?php if ($post['featured_image'] ?? ''): ?>
            <div class="rounded-4 overflow-hidden mb-4" style="max-height:420px">
                <img src="<?= e((string)$post['featured_image']) ?>" class="w-100" style="object-fit:cover;max-height:420px" alt="">
            </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <?php if ($post['category_name'] ?? ''): ?>
                    <a href="/blog?category=<?= urlencode((string)$post['category_slug']) ?>" class="badge text-bg-primary text-decoration-none">
                        <?= e((string)$post['category_name']) ?>
                    </a>
                <?php endif; ?>
                <span class="badge text-bg-secondary"><?= e(ucfirst((string)($post['post_type'] ?? 'blog'))) ?></span>
                <span class="text-secondary small">
                    <i class="fas fa-clock me-1"></i><?= (int)($post['reading_time'] ?? 1) ?> min read
                </span>
                <span class="text-secondary small">
                    <i class="fas fa-eye me-1"></i><?= number_format((int)($post['view_count'] ?? 0)) ?> views
                </span>
            </div>

            <h1 class="h2 mb-3"><?= e((string)($post['title'] ?? '')) ?></h1>

            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width:40px;height:40px;flex-shrink:0">
                    <i class="fas fa-user text-light"></i>
                </div>
                <div>
                    <div class="fw-semibold small"><?= e((string)($post['author_name'] ?? 'Admin')) ?></div>
                    <div class="text-secondary small"><?= e(substr((string)($post['published_at'] ?? ''), 0, 10)) ?></div>
                </div>
            </div>

            <hr class="border-secondary mb-4">

            <!-- Content: trusted admin-managed HTML stored by authenticated admins -->
            <div class="blog-content" style="line-height:1.8;color:rgba(255,255,255,.87)">
                <?= /* trusted admin-managed HTML */ (string)($post['content'] ?? '') ?>
            </div>

            <?php if ($post['tags'] ?? ''): ?>
            <div class="mt-4 pt-4 border-top border-secondary">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-secondary small"><i class="fas fa-tags me-1"></i>Tags:</span>
                    <?php foreach (array_map('trim', explode(',', (string)$post['tags'])) as $tag): ?>
                        <?php if ($tag !== ''): ?>
                            <a href="/blog?search=<?= urlencode($tag) ?>" class="badge text-bg-secondary text-decoration-none"><?= e($tag) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Share -->
            <div class="mt-4 pt-4 border-top border-secondary">
                <div class="d-flex align-items-center gap-3">
                    <span class="small text-secondary">Share:</span>
                    <a href="https://twitter.com/intent/tweet?url=<?= urlencode((isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . '/blog/' . ($post['slug'] ?? '')) ?>&text=<?= urlencode((string)($post['title'] ?? '')) ?>"
                       target="_blank" class="btn btn-xs btn-outline-info">
                        <i class="fab fa-twitter me-1"></i>Twitter
                    </a>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-xl-4">
            <?php if ($related !== []): ?>
            <div class="glass rounded-4 p-4 mb-4">
                <h6 class="mb-3"><i class="fas fa-newspaper me-2 text-warning"></i>Related Posts</h6>
                <?php foreach ($related as $rp):
                    if ((int)($rp['id'] ?? 0) === (int)($post['id'] ?? 0)) continue;
                ?>
                <div class="mb-3">
                    <a href="/blog/post?slug=<?= e((string)$rp['slug']) ?>" class="text-light text-decoration-none small fw-semibold d-block mb-1">
                        <?= e((string)$rp['title']) ?>
                    </a>
                    <div class="text-secondary" style="font-size:.7rem"><?= e(substr((string)($rp['published_at'] ?? ''), 0, 10)) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.blog-content h1, .blog-content h2, .blog-content h3 { color: #fff; margin-top: 1.5rem; }
.blog-content p { margin-bottom: 1rem; }
.blog-content img { max-width: 100%; border-radius: .5rem; }
.blog-content a { color: #0dcaf0; }
.blog-content blockquote { border-left: 4px solid #0dcaf0; padding-left: 1rem; color: rgba(255,255,255,.7); }
.blog-content pre { background: #0d1117; padding: 1rem; border-radius: .5rem; overflow-x: auto; }
</style>
