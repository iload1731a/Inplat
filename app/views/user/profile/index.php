<?php declare(strict_types=1); ?>
<?php
$profile = is_array($profile ?? null) ? $profile : [];
require app_path('app/views/user/_nav.php');
?>

<div class="row g-4">
    <!-- Avatar -->
    <div class="col-lg-3">
        <div class="glass rounded-4 p-4 text-center">
            <div id="avatarPreview" class="mb-3 mx-auto" style="width:100px;height:100px;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,#38bdf8,#6366f1);display:flex;align-items:center;justify-content:center;">
                <?php if (!empty($profile['avatar_url'])): ?>
                    <img src="<?= e((string)$profile['avatar_url']) ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <span class="fw-bold fs-2 text-white"><?= e(mb_strtoupper(mb_substr((string)($profile['username'] ?? 'U'), 0, 2))) ?></span>
                <?php endif; ?>
            </div>
            <div class="fw-semibold mb-1"><?= e((string)($profile['username'] ?? '')) ?></div>
            <div class="text-secondary small mb-3"><?= e((string)($profile['email'] ?? '')) ?></div>
            <div class="mb-2">
                <?php
                $kycBadge = match((string)($profile['kyc_status'] ?? 'unverified')) {
                    'verified'   => ['success', 'Verified'],
                    'pending'    => ['warning', 'Pending'],
                    'rejected'   => ['danger',  'Rejected'],
                    default      => ['secondary','Unverified'],
                };
                ?>
                <span class="badge bg-<?= $kycBadge[0] ?>"><i class="fas fa-shield-halved me-1"></i><?= $kycBadge[1] ?></span>
            </div>
            <div class="text-secondary small mb-3">Member since <?= e(date('M Y', strtotime((string)($profile['created_at'] ?? 'now')))) ?></div>
            <form id="avatarForm" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <label class="btn btn-outline-info btn-sm w-100">
                    <i class="fas fa-camera me-1"></i>Change Avatar
                    <input type="file" name="avatar" accept="image/*" class="d-none" id="avatarFileInput">
                </label>
            </form>
        </div>
    </div>

    <!-- Profile Form -->
    <div class="col-lg-9">
        <div class="glass rounded-4 p-4">
            <ul class="nav nav-tabs nav-pills gap-1 mb-4 border-0" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPersonal">Personal Info</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAddress">Address</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabFinancial">Financial</button></li>
            </ul>
            <form id="profileForm" data-ajax="true" action="/user/profile/update" method="POST">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="tab-content">
                    <!-- Personal -->
                    <div class="tab-pane fade show active" id="tabPersonal">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">First Name</label>
                                <input type="text" name="first_name" class="form-control bg-transparent text-light border-secondary"
                                       value="<?= e((string)($profile['first_name'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Last Name</label>
                                <input type="text" name="last_name" class="form-control bg-transparent text-light border-secondary"
                                       value="<?= e((string)($profile['last_name'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Phone</label>
                                <input type="tel" name="phone" class="form-control bg-transparent text-light border-secondary"
                                       value="<?= e((string)($profile['phone'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control bg-transparent text-light border-secondary"
                                       value="<?= e((string)($profile['date_of_birth'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Gender</label>
                                <select name="gender" class="form-select bg-transparent text-light border-secondary">
                                    <option value="">— Select —</option>
                                    <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'prefer_not_to_say' => 'Prefer not to say'] as $v => $l): ?>
                                    <option value="<?= e($v) ?>" <?= ($profile['gender'] ?? '') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Timezone</label>
                                <select name="timezone" class="form-select bg-transparent text-light border-secondary">
                                    <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                                    <option value="<?= e($tz) ?>" <?= ($profile['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Language</label>
                                <select name="preferred_language" class="form-select bg-transparent text-light border-secondary">
                                    <?php foreach (['en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'zh' => 'Chinese', 'ar' => 'Arabic'] as $v => $l): ?>
                                    <option value="<?= e($v) ?>" <?= ($profile['preferred_language'] ?? 'en') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Company Name</label>
                                <input type="text" name="company_name" class="form-control bg-transparent text-light border-secondary"
                                       value="<?= e((string)($profile['company_name'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Tax ID</label>
                                <input type="text" name="tax_id" class="form-control bg-transparent text-light border-secondary"
                                       value="<?= e((string)($profile['tax_id'] ?? '')) ?>">
                            </div>
                        </div>
                    </div>
                    <!-- Address -->
                    <div class="tab-pane fade" id="tabAddress">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label text-secondary small">Address Line 1</label>
                                <input type="text" name="address_line1" class="form-control bg-transparent text-light border-secondary"
                                       value="<?= e((string)($profile['address_line1'] ?? '')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-secondary small">Address Line 2</label>
                                <input type="text" name="address_line2" class="form-control bg-transparent text-light border-secondary"
                                       value="<?= e((string)($profile['address_line2'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">City</label>
                                <input type="text" name="city" class="form-control bg-transparent text-light border-secondary"
                                       value="<?= e((string)($profile['city'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">State / Province</label>
                                <input type="text" name="state_province" class="form-control bg-transparent text-light border-secondary"
                                       value="<?= e((string)($profile['state_province'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Postal Code</label>
                                <input type="text" name="postal_code" class="form-control bg-transparent text-light border-secondary"
                                       value="<?= e((string)($profile['postal_code'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Country</label>
                                <input type="text" name="country_code" class="form-control bg-transparent text-light border-secondary"
                                       maxlength="2" placeholder="US"
                                       value="<?= e((string)($profile['profile_country'] ?? $profile['country_code'] ?? '')) ?>">
                            </div>
                        </div>
                    </div>
                    <!-- Financial -->
                    <div class="tab-pane fade" id="tabFinancial">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Occupation</label>
                                <input type="text" name="occupation" class="form-control bg-transparent text-light border-secondary"
                                       value="<?= e((string)($profile['occupation'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Source of Funds</label>
                                <select name="source_of_funds" class="form-select bg-transparent text-light border-secondary">
                                    <option value="">— Select —</option>
                                    <?php foreach (['employment' => 'Employment', 'business' => 'Business', 'investments' => 'Investments', 'savings' => 'Savings', 'inheritance' => 'Inheritance', 'other' => 'Other'] as $v => $l): ?>
                                    <option value="<?= e($v) ?>" <?= ($profile['source_of_funds'] ?? '') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Annual Income Range</label>
                                <select name="annual_income_range" class="form-select bg-transparent text-light border-secondary">
                                    <option value="">— Select —</option>
                                    <?php foreach (['<25k' => 'Under $25,000', '25k-50k' => '$25,000 – $50,000', '50k-100k' => '$50,000 – $100,000', '100k-250k' => '$100,000 – $250,000', '>250k' => 'Over $250,000'] as $v => $l): ?>
                                    <option value="<?= e($v) ?>" <?= ($profile['annual_income_range'] ?? '') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-info px-4"><i class="fas fa-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Avatar upload via event delegation on file input change
$('#avatarFileInput').on('change', function () {
    $('#avatarForm').trigger('submit');
});

$('#avatarForm').on('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    $.ajax({
        url: '/user/profile/avatar',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success(r) {
            if (r.ok) {
                document.getElementById('avatarPreview').innerHTML = '<img src="' + r.url + '" style="width:100%;height:100%;object-fit:cover;">';
                Swal.fire({ icon: 'success', title: 'Avatar updated', timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: r.message });
            }
        },
        error() { Swal.fire({ icon: 'error', title: 'Upload failed' }); }
    });
});
</script>
