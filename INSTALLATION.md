# Inplat — Installation Guide

This guide covers three scenarios:

1. **[Demo Installation](#1-demo-installation)** — run locally without a license for evaluation
2. **[Standard Installation](#2-standard-installation)** — production/staging with a CodeCanyon license
3. **[Production Hardening](#3-production-hardening)** — post-install checklist

---

## System Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 8.3+ |
| MySQL | 8.0+ |
| PHP extensions | `pdo`, `pdo_mysql`, `openssl`, `mbstring`, `json` |
| Web server | Apache 2.4+ or Nginx 1.18+ (or PHP built-in for demo) |
| Writable paths | `storage/`, `storage/uploads/`, `storage/config/`, `storage/logs/` |

---

## 1. Demo Installation

Demo Mode lets you explore the full platform locally **without** a CodeCanyon purchase code. A demo banner is displayed on every page to indicate restricted mode.

### 1.1 Clone / download the project

```bash
git clone https://github.com/your-org/inplat.git
cd inplat
```

### 1.2 Install PHP dependencies

```bash
composer dump-autoload
```

### 1.3 Enable Demo Mode

Set the `DEMO_MODE` environment variable before starting PHP:

```bash
# Linux / macOS
export DEMO_MODE=true

# Windows PowerShell
$env:DEMO_MODE = "true"

# Windows CMD
set DEMO_MODE=true
```

### 1.4 Start the built-in PHP development server

```bash
php -S 127.0.0.1:8000 -t public
```

### 1.5 Run the installer

Open `http://127.0.0.1:8000/install/step1` in your browser and follow the steps:

| Step | What happens |
|------|-------------|
| Step 1 | Requirements check — all extensions must pass |
| Step 2 | Database credentials — license section is replaced with a **Demo Mode Active** notice |
| Step 3 | SQL schema import — imports `trading_platform_schema.sql` |
| Step 4 | Admin account creation — set your super-admin credentials |
| Step 5 | Installation lock — marks the install as complete |

### 1.6 Seed demo accounts

After the installer finishes, run the demo seeder to create the read-only demo admin and demo user:

```bash
php database/demo_seed.php
```

The seeder prints the created credentials on success.

### 1.7 Log in

| Role | URL | Email | Password |
|------|-----|-------|----------|
| Demo Admin | `http://127.0.0.1:8000/admin/dashboard` | `admin@demo.test` | `Demo@1234` |
| Demo User | `http://127.0.0.1:8000/dashboard` | `user@demo.test` | `Demo@1234` |

> **Note:** Demo accounts can **view** all panels but cannot perform write operations (approve withdrawals, modify settings, place trades, etc.).

---

## 2. Standard Installation

### 2.1 Server setup

**Apache** — add a virtual host pointing to the `public/` directory:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/inplat/public

    <Directory /var/www/inplat/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Enable `mod_rewrite` and restart Apache:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**Nginx** — example server block:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/inplat/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|git|env) {
        deny all;
    }
}
```

### 2.2 File permissions

```bash
chown -R www-data:www-data /var/www/inplat
chmod -R 755 /var/www/inplat
chmod -R 750 /var/www/inplat/storage
```

### 2.3 Install PHP dependencies

```bash
cd /var/www/inplat
composer dump-autoload --optimize
```

### 2.4 Run the installer

Open `https://yourdomain.com/install/step1` and complete all five steps:

| Step | Action |
|------|--------|
| Step 1 | Pass requirements check |
| Step 2 | Enter database credentials **and** your CodeCanyon buyer name, email, purchase code and licensed domain |
| Step 3 | Import the SQL schema |
| Step 4 | Create the super-admin account |
| Step 5 | Finish and lock the installer |

### 2.5 Optional: Google reCAPTCHA

Set these environment variables (e.g. in your `.env` file or server config):

```bash
RECAPTCHA_ENABLED=true
RECAPTCHA_SITE_KEY=your_site_key
RECAPTCHA_SECRET_KEY=your_secret_key
```

---

## 3. Production Hardening

After a successful installation, apply these hardening steps:

### 3.1 Protect sensitive paths

Ensure the following paths are **never** publicly accessible:

- `storage/config/` — contains `database.php` and `license.json`
- `storage/logs/`
- `app/`
- `database/`
- `trading_platform_schema.sql`
- `composer.json` / `composer.lock`

If you use the provided `public/.htaccess` with Apache this is already handled. For Nginx the `deny all` location block above covers `.env` and `.git`; add similar blocks for the paths listed.

### 3.2 HTTPS

Obtain and install a TLS certificate (e.g. via Let's Encrypt / Certbot) and redirect all HTTP traffic to HTTPS.

### 3.3 File permissions

```bash
chmod 600 /var/www/inplat/storage/config/database.php
chmod 600 /var/www/inplat/storage/config/license.json
chmod 600 /var/www/inplat/storage/config/license.key
```

### 3.4 Remove the installer after use

Once installed, delete or restrict the `install/` directory:

```bash
rm -rf /var/www/inplat/install
```

Alternatively, keep the directory but block web access at the server level.

### 3.5 PHP configuration recommendations

```ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

### 3.6 Database security

- Create a dedicated MySQL user with access **only** to the platform database:

```sql
CREATE USER 'inplat_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON trading_platform.* TO 'inplat_user'@'localhost';
FLUSH PRIVILEGES;
```

- Enable MySQL binary logging for point-in-time recovery.
- Schedule regular database backups.

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Blank page after install | Check `storage/logs/app.log` for PHP errors; ensure `APP_DEBUG=true` temporarily |
| "Storage not writable" at Step 1 | `chmod -R 750 storage && chown -R www-data:www-data storage` |
| License validation fails | Ensure the domain in the license matches `$_SERVER['SERVER_NAME']` exactly (no `www` mismatch) |
| Demo Mode not activating at Step 2 | Verify `echo $_ENV['DEMO_MODE']` or `phpinfo()` shows the env var |
| 404 on all routes | Confirm `mod_rewrite` is enabled (Apache) or `try_files` is configured (Nginx) |
| CSS/JS not loading | Verify `DocumentRoot` points to `public/`, not the project root |
