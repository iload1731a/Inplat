<?php declare(strict_types=1); ?>
<?php
$page     = is_array($page     ?? null) ? $page     : [];
$sections = is_array($sections ?? null) ? $sections : [];
if ($page === []) { http_response_code(404); exit; }
?>
<div class="container py-5">
    <?php if ($sections !== []): ?>
        <?php foreach ($sections as $sec):
            if (!(int)($sec['is_visible'] ?? 1)) continue;
            $data = json_decode((string)($sec['section_data'] ?? '{}'), true) ?? [];
        ?>
        <div class="mb-4">
            <?php if ((string)($sec['section_type'] ?? '') === 'text'): ?>
                <div class="glass rounded-4 p-5 cms-content">
                    <?= /* trusted admin-managed HTML */ (string)($data['content'] ?? '') ?>
                </div>
            <?php elseif ((string)($sec['section_type'] ?? '') === 'hero'): ?>
                <div class="glass rounded-4 p-5 text-center">
                    <?php if ($data['heading'] ?? ''): ?>
                        <h2><?= e((string)$data['heading']) ?></h2>
                    <?php endif; ?>
                    <?php if ($data['subheading'] ?? ''): ?>
                        <p class="lead text-secondary"><?= e((string)$data['subheading']) ?></p>
                    <?php endif; ?>
                    <?php if ($data['button_text'] ?? ''): ?>
                        <a href="<?= e((string)($data['button_url'] ?? '#')) ?>" class="btn btn-primary">
                            <?= e((string)$data['button_text']) ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="glass rounded-4 p-4 cms-content">
                    <?= /* trusted admin-managed HTML */ (string)($data['content'] ?? '') ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="glass rounded-4 p-5">
            <h1 class="h2 mb-3"><?= e((string)($page['title'] ?? '')) ?></h1>
            <?php if ($page['excerpt'] ?? ''): ?>
                <p class="lead text-secondary mb-4"><?= e((string)$page['excerpt']) ?></p>
            <?php endif; ?>
            <div class="cms-content">
                <?= (string)($page['content'] ?? '') ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.cms-content h1,.cms-content h2,.cms-content h3 { color:#fff; margin-top:1.5rem; }
.cms-content p { margin-bottom:1rem; color:rgba(255,255,255,.87); }
.cms-content img { max-width:100%; border-radius:.5rem; }
.cms-content a { color:#0dcaf0; }
</style>
