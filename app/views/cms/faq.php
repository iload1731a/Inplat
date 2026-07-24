<?php declare(strict_types=1); ?>
<?php
$categories = is_array($categories ?? null) ? $categories : [];
$grouped    = is_array($grouped    ?? null) ? $grouped    : [];
?>
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="h2 mb-2"><i class="fas fa-question-circle me-2 text-warning"></i>Help Center</h1>
        <p class="text-secondary">Find answers to common questions about our trading platform.</p>
    </div>

    <!-- Category Quick-nav -->
    <div class="d-flex flex-wrap justify-content-center gap-2 mb-5">
        <?php foreach ($categories as $cat): ?>
        <a href="#cat_<?= (int)$cat['id'] ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fas <?= e((string)($cat['icon'] ?? 'fa-circle')) ?> me-1"></i>
            <?= e((string)$cat['name']) ?>
            <span class="badge text-bg-secondary ms-1"><?= (int)($cat['faq_count'] ?? 0) ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <?php foreach ($categories as $cat):
            $catFaqs = $grouped[(int)$cat['id']] ?? [];
            if ($catFaqs === []) continue;
        ?>
        <div class="col-xl-6" id="cat_<?= (int)$cat['id'] ?>">
            <div class="glass rounded-4 p-4 h-100">
                <h5 class="mb-4">
                    <i class="fas <?= e((string)($cat['icon'] ?? 'fa-circle')) ?> me-2 text-warning"></i>
                    <?= e((string)$cat['name']) ?>
                </h5>
                <div class="accordion accordion-flush" id="acc_<?= (int)$cat['id'] ?>">
                    <?php foreach ($catFaqs as $i => $faq): ?>
                    <div class="accordion-item bg-transparent border-0 border-bottom border-secondary">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent text-light px-0"
                                    type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq_<?= (int)$faq['id'] ?>">
                                <?= e((string)($faq['question'] ?? '-')) ?>
                            </button>
                        </h2>
                        <div id="faq_<?= (int)$faq['id'] ?>" class="accordion-collapse collapse"
                             data-bs-parent="#acc_<?= (int)$cat['id'] ?>">
                            <div class="accordion-body px-0 text-secondary">
                                <?= nl2br(e((string)($faq['answer'] ?? '-'))) ?>
                                <div class="d-flex align-items-center gap-3 mt-3">
                                    <span class="small text-secondary">Was this helpful?</span>
                                    <button class="btn btn-xs btn-outline-success" onclick="faqVote(<?= (int)$faq['id'] ?>, 'yes', this)">
                                        <i class="fas fa-thumbs-up me-1"></i><?= (int)($faq['helpful_yes'] ?? 0) ?>
                                    </button>
                                    <button class="btn btn-xs btn-outline-danger" onclick="faqVote(<?= (int)$faq['id'] ?>, 'no', this)">
                                        <i class="fas fa-thumbs-down me-1"></i><?= (int)($faq['helpful_no'] ?? 0) ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($categories === [] || $grouped === []): ?>
        <div class="col-12 text-center py-5 text-secondary">
            <i class="fas fa-question-circle fa-3x mb-3 d-block opacity-25"></i>
            No FAQs available yet.
        </div>
        <?php endif; ?>
    </div>

    <!-- Still have questions? -->
    <div class="glass rounded-4 p-5 text-center mt-5">
        <h4 class="mb-2">Still have questions?</h4>
        <p class="text-secondary mb-3">Can't find the answer you're looking for? Our support team is here to help.</p>
        <a href="/contact" class="btn btn-primary">
            <i class="fas fa-envelope me-1"></i> Contact Support
        </a>
    </div>
</div>

<script>
function faqVote(id, type, btn) {
    fetch('/faq/vote', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, type})
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            btn.disabled = true;
            const count = parseInt(btn.textContent.match(/\d+/) ?? [0]) + 1;
            const icon  = type === 'yes' ? '<i class="fas fa-thumbs-up me-1"></i>' : '<i class="fas fa-thumbs-down me-1"></i>';
            btn.innerHTML = icon + count;
        }
    });
}
</script>
