<?php declare(strict_types=1); ?>
<?php
$sent     = (bool)($sent     ?? false);
$errorMsg = (string)($errorMsg ?? '');
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="text-center mb-5">
                <h1 class="h2 mb-2"><i class="fas fa-envelope me-2 text-info"></i>Contact Us</h1>
                <p class="text-secondary">Have a question or need support? We'd love to hear from you.</p>
            </div>

            <?php if ($sent): ?>
            <div class="alert alert-success text-center p-4 mb-4">
                <i class="fas fa-check-circle fa-2x d-block mb-2"></i>
                <h5>Message Sent Successfully!</h5>
                <p class="mb-0">Thank you for reaching out. We'll get back to you within 24 hours.</p>
            </div>
            <?php endif; ?>

            <?php if ($errorMsg !== ''): ?>
            <div class="alert alert-danger mb-4">
                <i class="fas fa-exclamation-triangle me-2"></i><?= e($errorMsg) ?>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Contact Form -->
                <div class="col-xl-7">
                    <div class="glass rounded-4 p-5">
                        <h5 class="mb-4">Send us a message</h5>
                        <form id="contactForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small text-secondary">Full Name *</label>
                                    <input type="text" name="name" class="form-control bg-dark text-light border-secondary"
                                           placeholder="John Smith" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-secondary">Email Address *</label>
                                    <input type="email" name="email" class="form-control bg-dark text-light border-secondary"
                                           placeholder="john@example.com" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-secondary">Subject</label>
                                    <input type="text" name="subject" class="form-control bg-dark text-light border-secondary"
                                           placeholder="How can we help?">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-secondary">Department</label>
                                    <select name="department" class="form-select bg-dark text-light border-secondary">
                                        <option value="general">General Inquiry</option>
                                        <option value="support">Technical Support</option>
                                        <option value="billing">Billing</option>
                                        <option value="trading">Trading</option>
                                        <option value="kyc">KYC Verification</option>
                                        <option value="legal">Legal</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-secondary">Message *</label>
                                    <textarea name="message" class="form-control bg-dark text-light border-secondary" rows="5"
                                              placeholder="Describe your question or issue in detail..." required></textarea>
                                </div>
                                <div class="col-12">
                                    <div id="formAlert" class="d-none alert mb-3"></div>
                                    <button type="button" class="btn btn-primary btn-lg w-100" onclick="submitForm()">
                                        <i class="fas fa-paper-plane me-2"></i>Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="col-xl-5">
                    <div class="glass rounded-4 p-4 mb-4">
                        <h6 class="mb-3"><i class="fas fa-headset me-2 text-primary"></i>Support Hours</h6>
                        <div class="text-secondary small">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Monday - Friday</span><span class="text-light">9:00 AM - 6:00 PM UTC</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Saturday</span><span class="text-light">10:00 AM - 4:00 PM UTC</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Sunday</span><span class="text-secondary">Closed</span>
                            </div>
                        </div>
                    </div>

                    <div class="glass rounded-4 p-4 mb-4">
                        <h6 class="mb-3"><i class="fas fa-bolt me-2 text-warning"></i>Quick Help</h6>
                        <div class="d-grid gap-2">
                            <a href="/faq" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-question-circle me-1"></i> Browse FAQ
                            </a>
                            <?php if (isset($_SESSION['auth.user_id'])): ?>
                            <a href="/user/tickets/create" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-ticket-alt me-1"></i> Submit Support Ticket
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="glass rounded-4 p-4">
                        <h6 class="mb-3"><i class="fas fa-share-alt me-2 text-info"></i>Follow Us</h6>
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-sm btn-outline-info"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="btn btn-sm btn-outline-primary"><i class="fab fa-telegram"></i></a>
                            <a href="#" class="btn btn-sm btn-outline-danger"><i class="fab fa-discord"></i></a>
                            <a href="#" class="btn btn-sm btn-outline-secondary"><i class="fab fa-reddit"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function submitForm() {
    const form    = document.getElementById('contactForm');
    const fd      = new FormData(form);
    const data    = Object.fromEntries(fd.entries());
    const alert   = document.getElementById('formAlert');

    fetch('/contact', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        alert.classList.remove('d-none', 'alert-success', 'alert-danger');
        if (d.ok) {
            alert.classList.add('alert-success');
            alert.innerHTML = '<i class="fas fa-check me-2"></i>' + d.message;
            form.reset();
        } else {
            alert.classList.add('alert-danger');
            alert.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + (d.message || 'An error occurred.');
        }
    });
}
</script>
