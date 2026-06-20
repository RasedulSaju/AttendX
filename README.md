# AttendX

Enterprise-grade attendance, access control, and payroll management system built for on-premise deployment on Windows Server with Apache, PHP 8.2, MySQL 8.0, and Python 3.11 background services.

AttendX covers the complete workforce lifecycle: biometric punch-in via Granding F09/ID devices, attendance processing, leave management, shift/roster planning, access control, overtime tracking, and payslip generation — all in a single self-hosted system.

## Features

- **Employee Management** — full employee lifecycle, documents, transfers, organizational structure
- **Biometric Attendance** — dual-mode device integration (ADMS push + SDK pull) for Granding F09/ID terminals
- **Shift & Roster Planning** — flexible shift definitions, roster assignment, shift-change workflow
- **Leave Management** — configurable leave types, balance tracking, approval workflow, holiday calendar
- **Access Control** — zone-based door access, schedules, visitor passes, real-time access logs
- **Payroll** — salary structures, configurable earning/deduction components, payslip generation
- **Reports & Analytics** — attendance, leave, payroll, overtime, and late-arrival reports with CSV export
- **REST API & Mobile** — JWT-authenticated API for mobile apps, push notification support
- **PWA Support** — offline-capable progressive web app shell
- **Audit Logging** — every mutating action is recorded with before/after diffs

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 (custom MVC, no framework) |
| Background Services | Python 3.11 |
| Database | MySQL 8.0 |
| Web Server | Apache 2.4 (Apache Lounge on Windows) |
| Frontend | Bootstrap 5, vanilla JS, Chart.js |
| Auth | Session-based (web) + JWT (API/mobile) |
| Device SDK | pyzk (ZKTeco protocol family, used by Granding) |

## Quick Start

```bash
# 1. Clone/extract the project
cd attendx

# 2. Run the CLI installer
php install.php

# 3. Point Apache document root to /public

# 4. Install Python dependencies
pip install -r python/requirements.txt --break-system-packages

# 5. Start device services
python python/adms_listener.py
python python/sdk_pull.py
```

Or use the **web installer**: visit `http://your-domain/setup.php` and follow the wizard. Delete `setup.php` after installation.

See [docs/INSTALLATION.md](docs/INSTALLATION.md) for full setup instructions and [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for production deployment on Windows Server.

## Default Login

```
Email:    admin@attendx.com
Password: Admin@1234
```

**Change this password immediately after first login.**

## Project Structure

```
attendx/
├── app/
│   ├── Controllers/      # Request handlers (one per module)
│   ├── Models/            # Database access layer
│   ├── Core/               # Framework: Router, Auth, Database, Session, etc.
│   ├── Services/         # MailService, etc.
│   └── Views/              # PHP templates (layout + per-module views)
├── config/                 # database.php, app.php, jwt.php, mail.php, backup.php
├── database/
│   ├── install.sql        # Single consolidated schema + seed data (first install)
│   └── migrations/        # Legacy incremental migrations (reference only)
├── python/
│   ├── adms_listener.py   # Multi-port ADMS push listener (devices → server)
│   ├── sdk_pull.py        # SDK pull service (server → device)
│   └── windows_service.py # NSSM-compatible Windows service wrapper
├── public/                  # Web root — point Apache here
│   ├── index.php           # Front controller
│   ├── setup.php           # Web installer (delete after use)
│   └── .htaccess
├── routes/
│   ├── core.php            # Phases 1–10 routes
│   ├── web.php             # Phases 11–14 routes
│   └── api.php              # REST + mobile API routes
├── storage/                  # Logs, uploads, backups (created on install)
├── docs/                     # INSTALLATION.md, API.md, DEPLOYMENT.md
├── install.php               # CLI installer
└── .env.example
```

## Device Integration Notes

AttendX supports two independent communication modes with Granding F09/ID biometric devices, and they must not be confused:

- **ADMS Push (ports 8080/8081)** — the device initiates the connection and pushes attendance logs to the server in real time. The `adms_listener.py` service listens on every unique `adms_port` configured in the `devices` table, spawning one thread per port.
- **SDK Pull (port 4370)** — the server actively connects to the device using the `pyzk` library and pulls logs on a schedule. Used as a fallback/sync mechanism and for device management (user enrollment, clock sync, etc.).

Both can run simultaneously; data is deduplicated on insert (±30 second window) regardless of source.

## License

Proprietary — internal enterprise deployment only.
