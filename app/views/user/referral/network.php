<?php declare(strict_types=1); ?>
<?php
$network      = is_array($network      ?? null) ? $network      : [];
$referralCode = (string)($referralCode ?? '');
$stats        = is_array($stats        ?? null) ? $stats        : [];
require app_path('app/views/user/_nav.php');
?>

<div class="glass rounded-4 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fas fa-project-diagram me-2 text-success"></i>Your Referral Network</h6>
        <div class="text-secondary small">
            Code: <span class="badge bg-info"><?= e($referralCode) ?></span>
            &nbsp;|&nbsp; <?= number_format((int)($stats['total_referred'] ?? 0)) ?> total referrals
        </div>
    </div>

    <?php if ($network === []): ?>
    <div class="text-center py-5">
        <i class="fas fa-users fa-3x text-secondary mb-3 d-block"></i>
        <p class="text-secondary">You have no referrals yet. Share your referral link to build your network!</p>
        <a href="/user/referral" class="btn btn-info btn-sm">Get Referral Link</a>
    </div>
    <?php else: ?>

    <!-- Level 1 Tree -->
    <div class="network-tree">
        <!-- Root node -->
        <div class="text-center mb-4">
            <div class="d-inline-block glass rounded-3 px-4 py-2 border border-info border-opacity-50">
                <i class="fas fa-user-circle fa-lg text-info me-2"></i><strong>You</strong>
                <div class="small text-secondary">Level 0 (Root)</div>
            </div>
        </div>

        <!-- Level 1 -->
        <div class="row g-3">
            <?php foreach ($network as $l1): ?>
            <div class="col-md-6 col-xl-4">
                <div class="glass rounded-3 p-3 border border-success border-opacity-25">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-user text-success"></i>
                        <div>
                            <div class="fw-semibold small"><?= e((string)($l1['referred_username'] ?? '—')) ?></div>
                            <div class="text-secondary" style="font-size:.7rem">
                                <span class="badge bg-secondary me-1">L1</span>
                                <span class="badge bg-<?= match((string)($l1['status'] ?? 'pending')) { 'qualified' => 'success', 'active' => 'info', default => 'warning' } ?>"><?= e((string)($l1['status'] ?? 'pending')) ?></span>
                            </div>
                        </div>
                        <div class="ms-auto text-warning small font-monospace"><?= number_format((float)($l1['total_earned'] ?? 0), 4) ?></div>
                    </div>

                    <!-- Level 2 children -->
                    <?php if (!empty($l1['children'])): ?>
                    <div class="ms-3 mt-2 border-start border-secondary ps-3">
                        <?php foreach ($l1['children'] as $l2): ?>
                        <div class="d-flex align-items-center gap-2 mb-1 py-1">
                            <i class="fas fa-user-circle text-info" style="font-size:.8rem"></i>
                            <div class="small">
                                <span><?= e((string)($l2['referred_username'] ?? '—')) ?></span>
                                <span class="badge bg-secondary ms-1" style="font-size:.6rem">L2</span>
                            </div>
                            <!-- Level 3 count indicator -->
                            <?php if (!empty($l2['children'])): ?>
                            <span class="ms-auto badge bg-outline-secondary text-secondary" style="font-size:.65rem">
                                +<?= count($l2['children']) ?> L3
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($l1['children']) >= 50): ?>
                        <div class="small text-secondary">... and more</div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 text-secondary small text-center">
            Network shows up to 3 levels deep. Only direct children shown per node (max 50).
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Tier Distribution -->
<div class="glass rounded-4 p-4">
    <h6 class="mb-3"><i class="fas fa-layer-group me-2 text-info"></i>Network Summary</h6>
    <div class="row g-3">
        <?php
        $l1Count = count($network);
        $l2Count = array_sum(array_map(fn($n) => count($n['children'] ?? []), $network));
        $l3Count = array_sum(array_map(fn($n) => array_sum(array_map(fn($c) => count($c['children'] ?? []), $n['children'] ?? [])), $network));
        foreach ([
            ['Level 1 (Direct)', $l1Count, 'success', '20%'],
            ['Level 2',          $l2Count, 'info',    '10%'],
            ['Level 3',          $l3Count, 'warning',  '5%'],
        ] as [$label, $cnt, $color, $rate]):
        ?>
        <div class="col-md-4">
            <div class="glass rounded-3 p-3 text-center">
                <div class="h3 fw-bold text-<?= $color ?>"><?= number_format($cnt) ?></div>
                <div class="small text-secondary"><?= e($label) ?></div>
                <div class="badge bg-<?= $color ?> mt-1"><?= e($rate) ?> commission</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
