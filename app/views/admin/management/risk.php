<?php declare(strict_types=1); ?>
<?php
$filters = is_array($filters ?? null) ? $filters : [];
$summary = is_array($summary ?? null) ? $summary : [];
$riskFlags = is_array($riskFlags ?? null) ? $riskFlags : [];
$ipBlacklist = is_array($ipBlacklist ?? null) ? $ipBlacklist : [];
$sarCases = is_array($sarCases ?? null) ? $sarCases : [];
$sanctionedCountries = is_array($sanctionedCountries ?? null) ? $sanctionedCountries : [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Risk &amp; Compliance</h1>
        <p class="text-secondary mb-0">Monitor risk flags, SAR cases, IP blacklist and sanctioned countries.</p>
    </div>
    <a href="/admin/dashboard" class="btn btn-outline-light btn-sm">Dashboard</a>
</div>
<?php require app_path('app/views/admin/_nav.php'); ?>

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['label' => 'Open Risk Flags', 'value' => $summary['open_flags'] ?? 0, 'color' => 'warning'],
        ['label' => 'Critical Flags', 'value' => $summary['critical_flags'] ?? 0, 'color' => 'danger'],
        ['label' => 'Open SAR Cases', 'value' => $summary['open_sar_cases'] ?? 0, 'color' => 'info'],
        ['label' => 'Blocked IPs', 'value' => $summary['blocked_ips'] ?? 0, 'color' => 'secondary'],
        ['label' => 'Sanctioned Countries', 'value' => $summary['sanctioned_countries'] ?? 0, 'color' => 'danger'],
    ];
    foreach ($kpis as $kpi):
    ?>
    <div class="col-md-2 col-6">
        <div class="glass rounded-4 p-3 text-center">
            <div class="h4 fw-bold text-<?= e($kpi['color']) ?>"><?= number_format((int)$kpi['value']) ?></div>
            <div class="small text-secondary"><?= e($kpi['label']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="glass rounded-4 p-3 mb-4">
    <form class="row g-2" method="get" action="/admin/risk">
        <div class="col-md-3">
            <select class="form-select" name="status">
                <option value="">All flag statuses</option>
                <?php foreach (['open', 'investigating', 'resolved', 'false_positive'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= (($filters['status'] ?? '') === $st) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" name="severity">
                <option value="">All severities</option>
                <?php foreach (['critical', 'high', 'medium', 'low'] as $sv): ?>
                    <option value="<?= e($sv) ?>" <?= (($filters['severity'] ?? '') === $sv) ? 'selected' : '' ?>><?= e(ucfirst($sv)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
        <div class="col-md-1"><a class="btn btn-outline-light w-100" href="/admin/risk">Reset</a></div>
    </form>
</div>

<div class="row g-3 mb-4">
    <!-- Risk Flags -->
    <div class="col-xl-8">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Risk Flags</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>ID</th><th>User</th><th>Type</th><th>Severity</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($riskFlags as $flag): ?>
                        <tr>
                            <td><?= (int)($flag['id'] ?? 0) ?></td>
                            <td><?= e((string)($flag['username'] ?? '-')) ?></td>
                            <td class="small"><?= e(str_replace('_', ' ', (string)($flag['flag_type'] ?? '-'))) ?></td>
                            <td>
                                <span class="badge text-bg-<?= match($flag['severity'] ?? '') {
                                    'critical' => 'danger',
                                    'high' => 'warning text-dark',
                                    'medium' => 'info',
                                    default => 'secondary'
                                } ?>"><?= e((string)($flag['severity'] ?? '-')) ?></span>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= match($flag['status'] ?? '') {
                                    'open' => 'warning text-dark',
                                    'investigating' => 'info',
                                    'resolved' => 'success',
                                    'false_positive' => 'secondary',
                                    default => 'secondary'
                                } ?>"><?= e(str_replace('_', ' ', (string)($flag['status'] ?? '-'))) ?></span>
                            </td>
                            <td class="small text-secondary"><?= e((string)($flag['created_at'] ?? '-')) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-light" type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#flag-edit-<?= (int)($flag['id'] ?? 0) ?>">
                                    Update
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse" id="flag-edit-<?= (int)($flag['id'] ?? 0) ?>">
                            <td colspan="7">
                                <?php if (isset($flag['description']) && $flag['description'] !== ''): ?>
                                <div class="small text-secondary p-2"><?= e((string)$flag['description']) ?></div>
                                <?php endif; ?>
                                <form action="/admin/risk/flag/update" method="post" data-ajax="true" class="row g-2 p-2 border border-secondary rounded-3">
                                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                                    <input type="hidden" name="flag_id" value="<?= (int)($flag['id'] ?? 0) ?>">
                                    <div class="col-md-5">
                                        <select class="form-select form-select-sm" name="status">
                                            <?php foreach (['open', 'investigating', 'resolved', 'false_positive'] as $st): ?>
                                                <option value="<?= e($st) ?>" <?= (($flag['status'] ?? '') === $st) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <input class="form-control form-control-sm" type="number" name="assigned_to" placeholder="Assign to admin ID (optional)" value="0" min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-outline-info w-100" type="submit">Save</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($riskFlags === []): ?><tr><td colspan="7" class="text-center text-secondary">No risk flags found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SAR Cases -->
    <div class="col-xl-4">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">SAR Cases</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>Case #</th><th>User</th><th>Status</th><th>Filed</th></tr></thead>
                    <tbody>
                    <?php foreach ($sarCases as $case): ?>
                        <tr>
                            <td class="small"><?= e((string)($case['case_number'] ?? '-')) ?></td>
                            <td><?= e((string)($case['username'] ?? '-')) ?></td>
                            <td>
                                <span class="badge text-bg-<?= match($case['status'] ?? '') {
                                    'open' => 'warning text-dark',
                                    'investigating' => 'info',
                                    'filed_with_authority', 'closed_filed' => 'success',
                                    'closed_no_action' => 'secondary',
                                    default => 'secondary'
                                } ?> small"><?= e(str_replace('_', ' ', (string)($case['status'] ?? '-'))) ?></span>
                            </td>
                            <td><?= (int)($case['filed_with_authority'] ?? 0) === 1 ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-secondary">No</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($sarCases === []): ?><tr><td colspan="4" class="text-center text-secondary">No SAR cases.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- IP Blacklist -->
    <div class="col-xl-6">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">IP Blacklist</h2>
            <form action="/admin/risk/ip/block" method="post" data-ajax="true" class="row g-2 mb-3">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="col-md-5"><input class="form-control form-control-sm" type="text" name="ip_address" placeholder="IP address (IPv4 or IPv6)" required></div>
                <div class="col-md-5"><input class="form-control form-control-sm" type="text" name="reason" placeholder="Reason"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-outline-danger w-100" type="submit">Block</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>IP Address</th><th>Reason</th><th>Blocked</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($ipBlacklist as $entry): ?>
                        <tr>
                            <td class="font-monospace"><?= e((string)($entry['ip_address'] ?? '-')) ?></td>
                            <td class="small text-secondary"><?= e((string)($entry['reason'] ?? '-')) ?></td>
                            <td class="small text-secondary"><?= e((string)($entry['created_at'] ?? '-')) ?></td>
                            <td>
                                <form action="/admin/risk/ip/unblock" method="post" data-ajax="true">
                                    <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                                    <input type="hidden" name="entry_id" value="<?= (int)($entry['id'] ?? 0) ?>">
                                    <button class="btn btn-xs btn-outline-secondary btn-sm" type="submit">Unblock</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($ipBlacklist === []): ?><tr><td colspan="4" class="text-center text-secondary">No blocked IPs.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sanctioned Countries -->
    <div class="col-xl-6">
        <div class="glass rounded-4 p-3">
            <h2 class="h6 mb-3">Sanctioned Countries</h2>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>Code</th><th>Reason</th><th>Added</th></tr></thead>
                    <tbody>
                    <?php foreach ($sanctionedCountries as $country): ?>
                        <tr>
                            <td><span class="badge text-bg-danger"><?= e((string)($country['country_code'] ?? '-')) ?></span></td>
                            <td class="small"><?= e((string)($country['reason'] ?? '-')) ?></td>
                            <td class="small text-secondary"><?= e((string)($country['added_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($sanctionedCountries === []): ?><tr><td colspan="3" class="text-center text-secondary">No sanctioned countries.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
