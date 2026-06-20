# AttendX Installation Guide

This guide covers installing AttendX from scratch on a fresh server (Windows or Linux).

## 1. Prerequisites

| Requirement | Minimum Version |
|---|---|
| PHP | 8.2 |
| MySQL | 8.0 |
| Apache | 2.4 (with mod_rewrite, mod_headers) |
| Python | 3.11 |

### Required PHP Extensions

```
pdo, pdo_mysql, mbstring, json, session, fileinfo, gd, openssl
```

Check with:
```bash
php -m | grep -E "pdo|mbstring|json|gd|fileinfo|openssl"
```

### Required Python Packages

Installed via `pip install -r python/requirements.txt`:
```
pyzk
mysql-connector-python
```

## 2. Get the Code

Extract the AttendX package to your web server, e.g.:
```
C:\inetpub\attendx          (Windows)
/var/www/attendx            (Linux)
```

## 3. Database Setup

### Option A — CLI Installer (Recommended)

```bash
cd attendx
php install.php
```

The wizard will:
1. Verify PHP version and extensions
2. Prompt for database credentials and create the database if it doesn't exist
3. Run `database/install.sql` (creates all 45 tables + seed data in one pass)
4. Generate `.env` with random `APP_KEY` and `JWT_SECRET`
5. Create `storage/` subdirectories with correct permissions
6. Print the default admin login

### Option B — Web Installer

1. Point your web server at `attendx/public`
2. Visit `http://your-domain/setup.php`
3. Follow the 4-step wizard (requirements check → database → app settings → finish)
4. **Delete `public/setup.php` immediately after installation** — it allows database reconfiguration if left in place

### Option C — Manual

```bash
mysql -u root -p -e "CREATE DATABASE attendx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p attendx < database/install.sql
cp .env.example .env
# Edit .env manually with your DB credentials, APP_KEY, JWT_SECRET
```

Generate secure keys manually if needed:
```bash
php -r "echo bin2hex(random_bytes(16));"   # APP_KEY (32 chars)
php -r "echo bin2hex(random_bytes(32));"   # JWT_SECRET (64 chars)
```

## 4. Web Server Configuration

### Apache (document root = `attendx/public`)

The included `public/.htaccess` handles URL rewriting and security headers automatically, provided `mod_rewrite` and `mod_headers` are enabled and `AllowOverride All` is set for the directory.

Example VirtualHost:
```apache
<VirtualHost *:80>
    ServerName attendx.yourcompany.local
    DocumentRoot "C:/inetpub/attendx/public"
    <Directory "C:/inetpub/attendx/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Reload Apache after changes.

## 5. File Permissions

The web server user needs write access to:
```
storage/logs
storage/backups
storage/uploads
storage/cache
.env
```

Linux:
```bash
chown -R www-data:www-data storage .env
chmod -R 750 storage
chmod 640 .env
```

Windows: ensure the IIS/Apache service account has Modify permission on the `storage` folder.

## 6. Python Services Setup

```bash
cd python
pip install -r requirements.txt --break-system-packages
```

### Run manually (testing)

```bash
python adms_listener.py
python sdk_pull.py
```

### Run as Windows Service (production)

Use NSSM (Non-Sucking Service Manager):

```cmd
nssm install AttendXAdmsListener "C:\Python311\python.exe" "C:\inetpub\attendx\python\adms_listener.py"
nssm set AttendXAdmsListener AppDirectory "C:\inetpub\attendx\python"
nssm start AttendXAdmsListener

nssm install AttendXSdkPull "C:\Python311\python.exe" "C:\inetpub\attendx\python\sdk_pull.py"
nssm set AttendXSdkPull AppDirectory "C:\inetpub\attendx\python"
nssm start AttendXSdkPull
```

Both services read database credentials from environment variables (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`) — set these as Windows environment variables or pass them via NSSM's `AppEnvironmentExtra` parameter.

## 7. Device Configuration

For each Granding F09/ID device, go to **Devices** in the admin panel and register:

- **IP Address** — the device's network IP
- **SDK Port** — typically `4370` (used for pull/sync operations)
- **ADMS Port** — typically `8080` (the port the device pushes attendance data to)

On the physical device, configure the ADMS server settings to point to your AttendX server's IP and the configured ADMS port. Multiple devices can share the same ADMS port or use different ones — `adms_listener.py` automatically spawns a listener thread for every distinct port found in the `devices` table.

## 8. First Login

```
URL:      http://your-domain/login
Email:    admin@attendx.com
Password: Admin@1234
```

Immediately:
1. Change the admin password (**My Profile → Change Password**)
2. Update company information (**System → Settings → Company**)
3. Configure SMTP for email notifications (**System → Settings → Mail**)
4. Add departments, designations, and shifts before importing employees

## 9. Verifying Installation

- [ ] Login page loads at `/login`
- [ ] Dashboard loads after login with KPI cards
- [ ] `php install.php` or setup.php reported no SQL errors
- [ ] `storage/logs/` is writable (check after first error, if any)
- [ ] ADMS listener log shows `Database connection OK` on startup
- [ ] SDK pull service log shows the same

## Troubleshooting

| Symptom | Likely Cause |
|---|---|
| 500 error on every page | Check `storage/logs/php_errors.log`; usually a missing PHP extension or wrong DB credentials in `.env` |
| 404 on all routes except `/` | `mod_rewrite` not enabled or `AllowOverride None` in Apache config |
| "CSRF token mismatch" on every form | `session.cookie_secure` mismatch — set `SESSION_SECURE=false` in `.env` if not using HTTPS |
| Devices show "offline" despite being online | Verify firewall allows inbound on the configured ADMS port, and outbound on SDK port 4370 |
| Python service exits immediately | Run manually in a terminal to see the error; usually missing `mysql-connector-python` or wrong DB env vars |
