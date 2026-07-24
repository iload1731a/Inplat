# Inplat — Professional Trading Platform

A full-featured, self-hosted trading platform built with PHP 8.3, MySQL 8, Bootstrap 5 and a clean MVC architecture. Ships with a 5-step web installer, a complete admin panel, a full-featured user dashboard, and an optional **Demo Mode** so you can evaluate the platform locally without a paid license.

---

## Table of Contents

- [Stack](#stack)
- [Feature Overview](#feature-overview)
- [Quick Start (Demo)](#quick-start-demo)
- [Full Installation](#full-installation)
- [Demo Credentials](#demo-credentials)
- [Optional: Google reCAPTCHA](#optional-google-recaptcha)
- [License](#license)

---

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.3+ |
| Database | MySQL 8+ |
| Frontend | Bootstrap 5.3+, jQuery, AJAX |
| Data access | PDO (prepared statements) |
| Security | CSRF tokens, bcrypt/argon2id, session auth, 2FA |

---

## Feature Overview

### Installer
- 5-step web wizard: requirements check → database config → SQL import → admin creation → lock
- License activation supports CodeCanyon, third-party provider keys, or owner license token flow
- **Demo Mode** — skip license entirely for local evaluation (see [Quick Start](#quick-start-demo))

### Authentication
- Login with Remember Me, Register, Forgot / Reset Password
- Email verification flow, 2FA challenge (TOTP), session revocation
- Optional Google reCAPTCHA on all auth forms

### Admin Panel
| Module | Highlights |
|--------|-----------|
| Dashboard | KPI cards, growth/revenue trend charts, order-status pie, activity timeline, latest trades/logins/deposits/withdrawals |
| User Management | User list, detail, ban/unban, 2FA reset, session revoke, balance adjustment |
| KYC / Compliance | KYC queue, review workflow, risk assessments, AML audit log |
| Finance | Deposits queue, withdrawals review and approval |
| Trading Engine | Pair config, fee tiers, halt/resume trading, live order overview |
| Risk & Compliance | Risk flag triage, SAR cases, IP blacklist, sanctioned countries |
| Markets | Asset/market management, pair imports |
| Charts | Market analytics administration |
| Signals | Trading signal providers, publish signals, manage subscriptions |
| Roles & Permissions | Create roles, assign granular permissions per module |
| Wallets | Platform-wide wallet overview, manual adjustments |
| Support Tickets | Ticket management, admin replies, internal notes, CSAT ratings |
| Notifications | Bulk notifications, email templates, announcement broadcasts |
| Referral & Affiliate | Multi-level referral tiers, affiliate payouts, commission management |
| CMS / Website Builder | Pages, blog, FAQs, testimonials, pricing plans, homepage sections, SEO |
| Settings | SMTP, SMS, API integrations, maintenance mode, feature flags, cache, backups |
| Logs | Admin activity log, system audit trail |

### User Panel
| Module | Highlights |
|--------|-----------|
| Dashboard | Portfolio overview, market snapshot |
| Trading | Full trading workspace (orders, positions, charts) |
| Charts | Advanced charting with saved templates and indicator preferences |
| Wallets | Deposit, withdraw, transaction history |
| Staking | Pool listing with APY/lock-period, active stakes, reward history |
| Convert | Currency converter with quote history |
| KYC | Document upload and status tracking |
| Notifications | Notification centre, preference management |
| Support Tickets | Submit and track tickets |
| Referral | Referral link, tier progress, reward history |
| API Keys | Create/revoke API keys with permission scopes |
| Profile & Security | Profile editor, password change, 2FA setup, session management |

---

## Quick Start (Demo)

Run a full demo locally or on a public demo domain **without** a CodeCanyon license.

> See **[INSTALLATION.md](INSTALLATION.md)** for the complete guide.

```bash
# 1. Install PHP dependencies
composer dump-autoload

# 2. Create environment file
cp .env.example .env

# 3. Edit .env for your host/domain
# APP_URL=https://demo.yourdomain.com
# DEMO_MODE=true
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=trading_platform
# DB_USERNAME=inplat_user
# DB_PASSWORD=change_me

# 4. Start the built-in PHP server for local demo
php -S 127.0.0.1:8000 -t public
```

For a hosted demo, point your domain to `/public`, then open `https://your-demo-domain/install/step1`.  
For a local demo, open `http://127.0.0.1:8000/install/step1`.  
At **Step 2** the license section will be replaced with a "Demo Mode Active" notice.

After installation, seed the demo accounts:

```bash
php database/demo_seed.php
```

---

## Demo Credentials

| Role | URL | Email | Password |
|------|-----|-------|----------|
| Demo Admin | `/admin/dashboard` | `admin@demo.test` | `Demo@1234` |
| Demo User | `/dashboard` | `user@demo.test` | `Demo@1234` |

> **Demo accounts are read-only.** Write operations (approve withdrawals, modify settings, place trades, etc.) display a "Demo Mode – action disabled" notice.

---

## Full Installation

See **[INSTALLATION.md](INSTALLATION.md)** for step-by-step instructions covering:
- Server requirements
- Apache / Nginx vhost configuration
- `.env` / hosted demo configuration
- Standard installation with CodeCanyon license
- Production hardening checklist

---

## Optional: Google reCAPTCHA

Set environment variables before starting the PHP process:

```bash
export RECAPTCHA_ENABLED=true
export RECAPTCHA_SITE_KEY=your_site_key
export RECAPTCHA_SECRET_KEY=your_secret_key
```

---

## License

Supported installation license sources:

- CodeCanyon (buyer + purchase code)
- Third-party provider (provider name + external key)
- Owner license (owner mode + owner token)

For local evaluation, use Demo Mode (see above). For owner-token CLI installation, see `INSTALLATION.md`.
