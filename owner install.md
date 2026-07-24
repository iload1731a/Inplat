# Owner Install (Step by Step)

## 1) Prepare environment

1. Copy environment file:
   ```bash
   cp .env.example .env
   ```
2. Set production URL and database values in `/home/runner/work/Inplat/Inplat/.env`:
   ```env
   APP_URL=https://your-domain.com
   DEMO_MODE=false
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=trading_platform
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_password
   ```

## 2) Enable owner license mode

In `/home/runner/work/Inplat/Inplat/.env`, set:

```env
OWNER_LICENSE_ENABLED=true
OWNER_INSTALL_TOKEN=CHANGE_WITH_A_LONG_RANDOM_SECRET
```

Generate a strong token:

```bash
openssl rand -hex 32
```

Put that output as `OWNER_INSTALL_TOKEN`.

## 3) Install dependencies and serve app

```bash
cd /home/runner/work/Inplat/Inplat
composer dump-autoload
php -S 127.0.0.1:8000 -t public
```

## 4) Run installer wizard

Open:

- Local: `http://127.0.0.1:8000/install/step1`
- Server: `https://your-domain.com/install/step1`

Then complete:

1. **Step 1**: requirements check
2. **Step 2**:
   - Fill database fields
   - License Type: **Owner License (token required)**
   - Fill **Owner Name**
   - Fill **Owner Email**
   - Fill **Owner Install Token** with the exact value from `.env`
   - Save & Continue
3. **Step 3**: import schema
4. **Step 4**: create admin account
5. **Step 5**: finish install

## 5) Verify install lock and login

After completion, confirm lock file exists:

- `/home/runner/work/Inplat/Inplat/storage/installed.lock`

Login with the admin account you created.

## 6) Common fixes

- Owner option not visible in Step 2:
  - Check `.env`: `OWNER_LICENSE_ENABLED=true`
  - Restart PHP process after editing `.env`
- Token rejected:
  - Ensure installer token exactly matches `OWNER_INSTALL_TOKEN` in `.env`
  - Remove leading/trailing spaces
- License validation failed after install:
  - Ensure `APP_URL` host matches the install domain
