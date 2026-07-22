# Inplat

Professional Trading Platform foundation generated around the provided MySQL schema.

## Stack

- PHP 8.3+
- MySQL 8+
- Bootstrap 5.3+
- jQuery + AJAX
- PDO + CSRF + session auth

## Current Delivery Scope

- MVC folder architecture
- Clean routing bootstrap
- 5-step installer:
  1. Requirements check
  2. Database config
  3. SQL import from `trading_platform_schema.sql`
  4. Admin account creation
  5. Install finish lock
- Authentication module pages:
  - Login + Remember Me
  - Register
  - Forgot Password (token generation)
  - Reset Password
  - Email Verification notice + verification route
  - 2FA challenge flow
  - Session management (revoke remembered sessions)
- Admin dashboard + admin operations page:
  - KPI cards and charts
  - User status, KYC queue, payments queue, support tickets, settings preview
- User dashboard + trading workspace page:
  - Portfolio overview
  - Market overview, orders, positions, wallets, notifications, API keys, tickets
- Installer license activation:
  - CodeCanyon buyer details + purchase code capture
  - Domain-bound encrypted local license validation

## Local run

```bash
composer dump-autoload
php -S 127.0.0.1:8000 -t public
```

Open `http://127.0.0.1:8000/install/step1`.
