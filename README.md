# Inplat

Professional Trading Platform foundation (Phase 1) generated around the provided MySQL schema.

## Stack

- PHP 8.3+
- MySQL 8+
- Bootstrap 5.3+
- jQuery + AJAX
- PDO + CSRF + session auth

## Phase 1 Included

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

## Local run

```bash
composer dump-autoload
php -S 127.0.0.1:8000 -t public
```

Open `http://127.0.0.1:8000/install/step1`.
