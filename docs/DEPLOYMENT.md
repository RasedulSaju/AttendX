# AttendX Production Deployment Guide (Windows Server)

This guide covers deploying AttendX in production on Windows Server with Apache Lounge, MySQL 8.0, and Python 3.11 background services running as Windows Services.

## 1. Server Preparation

### Install Apache Lounge

1. Download Apache 2.4 from [apachelounge.com](https://www.apachelounge.com/download/)
2. Extract to `C:\Apache24`
3. Install the Visual C++ Redistributable required by your Apache build
4. Install as a service:
   ```cmd
   cd C:\Apache24\bin
   httpd.exe -k install
   ```

### Install PHP 8.2

1. Download the **Thread Safe** PHP 8.2 build for Windows from [windows.php.net](https://windows.php.net/download/)
2. Extract to `C:\PHP82`
3. Copy `php.ini-production` to `php.ini` and enable:
   ```ini
   extension=pdo_mysql
   extension=mbstring
   extension=fileinfo
   extension=gd
   extension=openssl
   ```
4. Set `date.timezone = Asia/Dhaka` (or your timezone) in `php.ini`

### Configure Apache for PHP (mod_fcgid or PHP-FPM recommended)

```apache
LoadModule fcgid_module modules/mod_fcgid.so
FcgidInitialEnv PHPRC "C:/PHP82"
AddHandler fcgid-script .php
FcgidWrapper "C:/PHP82/php-cgi.exe" .php
```

### Install MySQL 8.0

Use MySQL Installer for Windows. During setup:
- Choose **Server only** or **Custom**
- Set root password
- Create a dedicated `attendx_user` with privileges scoped to the `attendx` database (avoid using `root` in production)

```sql
CREATE USER 'attendx_user'@'localhost' IDENTIFIED BY 'StrongPasswordHere';
GRANT ALL PRIVILEGES ON attendx.* TO 'attendx_user'@'localhost';
FLUSH PRIVILEGES;
```

### Install Python 3.11

Download from [python.org](https://www.python.org/downloads/), check **Add to PATH** during install.

## 2. Deploy Application Code

```cmd
mkdir C:\inetpub\attendx
:: Extract AttendX package here
cd C:\inetpub\attendx
php install.php
```

Follow the installer prompts using the `attendx_user` credentials created above.

## 3. Apache Virtual Host

`C:\Apache24\conf\extra\attendx.conf`:

```apache
<VirtualHost *:80>
    ServerName attendx.yourcompany.local
    DocumentRoot "C:/inetpub/attendx/public"

    <Directory "C:/inetpub/attendx/public">
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    ErrorLog "logs/attendx-error.log"
    CustomLog "logs/attendx-access.log" combined
</VirtualHost>
```

Include it in `httpd.conf`:
```apache
Include conf/extra/attendx.conf
```

Restart Apache:
```cmd
httpd.exe -k restart
```

### HTTPS (Recommended)

Use a reverse proxy (IIS with ARR, or a TLS-terminating load balancer) in front of Apache, or configure `mod_ssl` directly with a certificate. After enabling HTTPS, set in `.env`:
```
SESSION_SECURE=true
```

## 4. Python Services as Windows Services (NSSM)

Download [NSSM](https://nssm.cc/) and place `nssm.exe` somewhere on PATH.

### ADMS Listener Service

```cmd
nssm install AttendXAdms "C:\Python311\python.exe" "C:\inetpub\attendx\python\adms_listener.py"
nssm set AttendXAdms AppDirectory "C:\inetpub\attendx\python"
nssm set AttendXAdms AppStdout "C:\inetpub\attendx\storage\logs\adms_service.log"
nssm set AttendXAdms AppStderr "C:\inetpub\attendx\storage\logs\adms_service_err.log"
nssm set AttendXAdms AppEnvironmentExtra DB_HOST=127.0.0.1 DB_USER=attendx_user DB_PASS=StrongPasswordHere DB_NAME=attendx
nssm set AttendXAdms Start SERVICE_AUTO_START
nssm start AttendXAdms
```

### SDK Pull Service

```cmd
nssm install AttendXSdkPull "C:\Python311\python.exe" "C:\inetpub\attendx\python\sdk_pull.py"
nssm set AttendXSdkPull AppDirectory "C:\inetpub\attendx\python"
nssm set AttendXSdkPull AppStdout "C:\inetpub\attendx\storage\logs\sdk_pull_service.log"
nssm set AttendXSdkPull AppStderr "C:\inetpub\attendx\storage\logs\sdk_pull_service_err.log"
nssm set AttendXSdkPull AppEnvironmentExtra DB_HOST=127.0.0.1 DB_USER=attendx_user DB_PASS=StrongPasswordHere DB_NAME=attendx PULL_INTERVAL_SEC=300
nssm set AttendXSdkPull Start SERVICE_AUTO_START
nssm start AttendXSdkPull
```

Verify both are running:
```cmd
sc query AttendXAdms
sc query AttendXSdkPull
```

## 5. Firewall Rules

Open inbound ports for device communication:

```cmd
netsh advfirewall firewall add rule name="AttendX ADMS 8080" dir=in action=allow protocol=TCP localport=8080
netsh advfirewall firewall add rule name="AttendX ADMS 8081" dir=in action=allow protocol=TCP localport=8081
netsh advfirewall firewall add rule name="AttendX HTTP" dir=in action=allow protocol=TCP localport=80
netsh advfirewall firewall add rule name="AttendX HTTPS" dir=in action=allow protocol=TCP localport=443
```

Outbound to devices on port 4370 (SDK pull) is typically allowed by default Windows firewall rules but verify if a restrictive outbound policy is in place.

## 6. Scheduled Tasks

### Daily Attendance Auto-Processing

If `attendance.auto_process` is enabled in System Settings, set up a Windows Scheduled Task to hit the processing endpoint daily after the last shift ends:

```cmd
schtasks /create /tn "AttendX Daily Process" /tr "C:\PHP82\php.exe C:\inetpub\attendx\cron\process_attendance.php" /sc daily /st 23:55
```

(Create a thin CLI wrapper script if not already present that calls the same logic as `AttendanceController::process()`.)

### Database Backup

```cmd
schtasks /create /tn "AttendX DB Backup" /tr "mysqldump -u attendx_user -pStrongPasswordHere attendx > C:\inetpub\attendx\storage\backups\attendx_%date:~-4,4%%date:~-10,2%%date:~-7,2%.sql" /sc daily /st 02:00
```

Or use the built-in **System → Backup** panel, which wraps `mysqldump` and stores files under `storage/backups`.

## 7. Monitoring Checklist

- [ ] Apache error log: `C:\Apache24\logs\attendx-error.log`
- [ ] PHP error log: `storage/logs/php_errors.log`
- [ ] ADMS service log: `storage/logs/adms_service.log`
- [ ] SDK pull service log: `storage/logs/sdk_pull_service.log`
- [ ] Audit log table (`audit_logs`) — review periodically via **System → Audit Logs**
- [ ] Device online/offline status — **Devices** page, or query `SELECT name,status,last_sync_at FROM devices`

## 8. Hardening Checklist

- [ ] `APP_DEBUG=false` in `.env`
- [ ] `public/setup.php` deleted after installation
- [ ] Default admin password changed
- [ ] `attendx_user` MySQL account scoped to `attendx` database only (not root/global)
- [ ] `.env` file permissions restricted (`chmod 640` on Linux equivalents, or NTFS ACLs restricting to the Apache service account on Windows)
- [ ] HTTPS enabled, `SESSION_SECURE=true`
- [ ] Firewall restricts inbound traffic to required ports only
- [ ] Regular `mysqldump` backups verified and tested for restore
- [ ] `storage/logs` rotated/archived periodically to prevent disk exhaustion

## 9. Upgrade Procedure

1. Back up the database: `mysqldump -u attendx_user -p attendx > backup_pre_upgrade.sql`
2. Back up `.env` and `storage/`
3. Deploy new application code (excluding `.env`, `storage/`, `public/setup.php` if already removed)
4. Run any new migration files added under `database/migrations/` (the consolidated `install.sql` is for fresh installs only — upgrades use incremental migrations)
5. Clear PHP opcache if enabled: restart Apache/PHP-FPM
6. Verify login and dashboard load correctly
7. Restart Python services if `python/` files changed:
   ```cmd
   nssm restart AttendXAdms
   nssm restart AttendXSdkPull
   ```
