<?php declare(strict_types=1); ?>
<?php require app_path('app/views/user/_nav.php'); ?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="glass rounded-4 p-4">
            <h5 class="mb-4"><i class="fas fa-plus me-2 text-info"></i>New Support Ticket</h5>
            <form id="createTicketForm" data-ajax="true" action="/user/tickets" method="POST">
                <input type="hidden" name="_token" value="<?= e(\App\Libraries\Csrf::token()) ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Subject <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control bg-transparent text-light border-secondary"
                           placeholder="Brief description of your issue" minlength="5" maxlength="255" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label text-secondary small">Category</label>
                        <select name="category" class="form-select bg-transparent text-light border-secondary">
                            <option value="general">General</option>
                            <option value="trading">Trading</option>
                            <option value="kyc">KYC / Verification</option>
                            <option value="payment">Payment / Finance</option>
                            <option value="technical">Technical Issue</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary small">Priority</label>
                        <select name="priority" class="form-select bg-transparent text-light border-secondary">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Message <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control bg-transparent text-light border-secondary" rows="6"
                              placeholder="Please describe your issue in detail..." minlength="10" required></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-info"><i class="fas fa-paper-plane me-1"></i>Submit Ticket</button>
                    <a href="/user/tickets" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
