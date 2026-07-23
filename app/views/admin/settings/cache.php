<?php declare(strict_types=1); ?>
<?php
$cacheLogs = is_array($cacheLogs ?? null) ? $cacheLogs : [];
$settings  = is_array($settings ?? null) ? $settings : [];
$cacheInfo = is_array($cacheInfo ?? null) ? $cacheInfo : [];
$s = fn(string $k, string $d = '') => (string)($settings[$k] ?? $d);
$csrf = \App\Libraries\Csrf::token();
require app_path('app/views/admin/_nav.php');
?>
<?php require __DIR__ . '/_subnav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-bolt me-2 text-info"></i>Cache Management</h1>
        <p class="text-secondary mb-0">Configure cache driver, flush caches, and monitor OPcache performance.</p>
    </div>
</div>

<div class="row g-4">
    <!-- OPcache Status -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-tachometer-alt me-2 text-success"></i>OPcache Status</h6>
            <?php if ($cacheInfo['opcache_enabled'] ?? false): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary small">Status</span>
                    <span class="badge bg-success">Enabled</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary small">Memory Used</span>
                    <strong><?= e((string)($cacheInfo['opcache_memory_mb'] ?? 0)) ?> MB</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary small">Hit Rate</span>
                    <strong class="text-success"><?= e((string)($cacheInfo['opcache_hit_rate'] ?? 0)) ?>%</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary small">Cached Scripts</span>
                    <strong><?= number_format((int)($cacheInfo['opcache_scripts'] ?? 0)) ?></strong>
                </div>
                <form method="post" action="/admin/settings/cache/flush" data-ajax="true">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="cache_type" value="opcache">
                    <button class="btn btn-sm btn-outline-warning w-100"><i class="fas fa-redo me-1"></i>Reset OPcache</button>
                </form>
            <?php else: ?>
                <div class="text-secondary small">OPcache is not enabled or not available.</div>
                <div class="mt-2 small"><code>opcache.enable=1</code> in php.ini</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Session Cache -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-user-clock me-2 text-info"></i>Session Cache</h6>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-secondary small">Session Path</span>
                <code class="small"><?= e((string)($cacheInfo['session_path'] ?? sys_get_temp_dir())) ?></code>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small">Active Session Files</span>
                <strong><?= number_format((int)($cacheInfo['session_files'] ?? 0)) ?></strong>
            </div>
            <form method="post" action="/admin/settings/cache/flush" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="cache_type" value="sessions">
                <button class="btn btn-sm btn-outline-info w-100"><i class="fas fa-trash me-1"></i>Flush Session Files</button>
            </form>
        </div>
    </div>

    <!-- App Cache -->
    <div class="col-lg-4">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-3"><i class="fas fa-layer-group me-2 text-warning"></i>Application Cache</h6>
            <div class="small text-secondary mb-3">Clears all file-based application caches including settings, user data, prices, and page cache.</div>
            <div class="d-flex flex-column gap-2">
                <form method="post" action="/admin/settings/cache/flush" data-ajax="true">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="cache_type" value="settings">
                    <button class="btn btn-sm btn-outline-light w-100">Flush Settings Cache</button>
                </form>
                <form method="post" action="/admin/settings/cache/flush" data-ajax="true">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="cache_type" value="prices">
                    <button class="btn btn-sm btn-outline-light w-100">Flush Price Cache</button>
                </form>
                <form method="post" action="/admin/settings/cache/flush" data-ajax="true">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="cache_type" value="all">
                    <button class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-fire me-1"></i>Flush ALL Caches</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Cache Driver Config -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-4">
            <h6 class="mb-4"><i class="fas fa-server me-2 text-secondary"></i>Cache Driver Configuration</h6>
            <form method="post" action="/admin/settings/cache/config" data-ajax="true">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">

                <div class="mb-3">
                    <label class="form-label small text-secondary">Cache Driver</label>
                    <select name="cache_driver" class="form-select bg-transparent text-light border-secondary" id="cacheDriverSel" onchange="toggleRedisFields(this.value)">
                        <option value="file"       <?= $s('cache_driver', 'file') === 'file'       ? 'selected' : '' ?>>File System</option>
                        <option value="redis"      <?= $s('cache_driver', 'file') === 'redis'      ? 'selected' : '' ?>>Redis</option>
                        <option value="memcached"  <?= $s('cache_driver', 'file') === 'memcached'  ? 'selected' : '' ?>>Memcached</option>
                    </select>
                </div>

                <div id="redisFields" style="display:<?= $s('cache_driver', 'file') === 'redis' ? '' : 'none' ?>">
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label small text-secondary">Redis Host</label>
                            <input type="text" name="redis_host" value="<?= e($s('redis_host', '127.0.0.1')) ?>"
                                   class="form-control bg-transparent text-light border-secondary">
                        </div>
                        <div class="col-3">
                            <label class="form-label small text-secondary">Port</label>
                            <input type="number" name="redis_port" value="<?= e($s('redis_port', '6379')) ?>"
                                   class="form-control bg-transparent text-light border-secondary" min="1" max="65535">
                        </div>
                        <div class="col-2">
                            <label class="form-label small text-secondary">DB</label>
                            <input type="number" name="redis_database" value="<?= e($s('redis_database', '0')) ?>"
                                   class="form-control bg-transparent text-light border-secondary" min="0" max="15">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Redis Password</label>
                        <input type="password" name="redis_password" value=""
                               class="form-control bg-transparent text-light border-secondary" placeholder="Leave blank if none" autocomplete="new-password">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary">Default Cache TTL (seconds)</label>
                    <input type="number" name="cache_ttl_seconds" value="<?= e($s('cache_ttl_seconds', '300')) ?>"
                           class="form-control bg-transparent text-light border-secondary" min="1" max="86400">
                </div>

                <button class="btn btn-primary">Save Cache Config</button>
            </form>
        </div>
    </div>

    <!-- Flush Log -->
    <div class="col-lg-6">
        <div class="glass rounded-4 p-0 overflow-hidden">
            <div class="p-3 border-bottom border-secondary-subtle">
                <h6 class="mb-0"><i class="fas fa-history me-2 text-secondary"></i>Recent Flush Operations</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0 small">
                    <thead><tr><th>Type</th><th>Cleared</th><th>By</th><th>When</th></tr></thead>
                    <tbody>
                    <?php foreach ($cacheLogs as $log): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= e((string)($log['cache_type'] ?? '-')) ?></span></td>
                            <td><?= (int)($log['items_cleared'] ?? 0) ?></td>
                            <td class="text-secondary"><?= e((string)($log['flushed_by_name'] ?? 'System')) ?></td>
                            <td class="text-secondary"><?= e(substr((string)($log['flushed_at'] ?? '-'), 0, 16)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($cacheLogs === []): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-3">No flush history.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRedisFields(driver) {
    document.getElementById('redisFields').style.display = driver === 'redis' ? '' : 'none';
}
</script>
