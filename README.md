# Inplat

Professional Trading Platform foundation (Phase 1) generated around the provided MySQL schema.

## Stack

- PHP 8.3+
- MySQL 8+
- Bootstrap 5.3+
- jQuery + AJAX
- PDO + CSRF + session auth

## Phase 1 + Phase 2 Included

- MVC folder architecture
- Clean routing bootstrap
- 5-step installer:
  1. Requirements check
  2. Database config
  3. SQL import from `trading_platform_schema.sql`
  4. Admin account creation
  5. Install finish lock
- Authentication baseline:
  - Login
  - Register
  - Logout
  - Forgot password screen
- Admin dashboard entrypoint with auth guard
- Phase 2 admin dashboard foundation:
  - KPI cards (users, orders, volume, fees)
  - Live Chart.js visualizations for activity trends
  - Recent trades/deposits/withdrawals tables
- Installer license activation:
  - CodeCanyon buyer details + purchase code capture
  - Domain-bound encrypted local license validation

## Local run

```bash
composer dump-autoload
php -S 127.0.0.1:8000 -t public
```

Open `http://127.0.0.1:8000/install/step1`.
