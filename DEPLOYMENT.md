# V2 PHP Deployment Guide — Spaceship Hosting

Date: 2026-03-30
Target: Full PHP backend (not static GitHub Pages)
Host: spaceship.com

---

## Quick Start

1. **Database Setup:** Create database in Spaceship cPanel → Databases
2. **Config File:** Copy `portal/config-deploy.template.php` → `portal/config-deploy.php` on Spaceship, fill in credentials
3. **Upload:** FTP/Git clone all PHP files and folders to public_html
4. **Permissions:** `chmod 755` for dirs, `chmod 600` for config-deploy.php
5. **Sessions:** Create `portal/tmp/sessions` and `portal/logs` directories
6. **Test:** Visit https://yourdomain.com/portal/login.php

---

## Full Instructions

See below or use this guide step-by-step:

### Step 1: Spaceship Database

In Spaceship cPanel → **Databases** → **Create New Database**:
- Name: `saltshore_v2`
- Charset: utf8mb4
- Record: host, user, password

### Step 2: Import Schema

Via SSH or phpMyAdmin, run `portal/db/setup.sql`:
```bash
mysql -h <HOST> -u <USER> -p <DB> < setup.sql
```

### Step 3: Create portal/config-deploy.php

Copy from `portal/config-deploy.template.php`, fill in:
- `DB_HOST` (from Spaceship)
- `DB_USER` (create in cPanel)
- `DB_PASSWORD` (strong password)
- `DB_NAME` (from Spaceship)
- `SALT` (random value: `openssl rand -base64 32`)

On server, restrict permissions:
```bash
chmod 600 portal/config-deploy.php
```

### Step 4: Upload Files

Via cPanel File Manager or FTP to `public_html/`:
- All `.php` files (root level)
- `assets/`, `includes/`, `legal/`, `portal/` (entire directories)

### Step 5: Create Directories & Permissions

```bash
mkdir -p portal/tmp/sessions
mkdir -p portal/logs
chmod 755 portal
chmod 755 portal/tmp
chmod 755 portal/tmp/sessions
chmod 775 portal/tmp/sessions    # writable by web server
chmod 775 portal/logs
```

### Step 6: Setup & Test

1. Visit: `https://yourdomain.com/portal/setup.php`
2. Create owner account
3. Log in at: `https://yourdomain.com/portal/login.php`
4. Test Dashboard, CalGen, FinPro, LedgerPro

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| "Connection refused" | Verify DB_HOST, DB_USER, DB_PASSWORD in config-deploy.php |
| "Session not persisting" | Ensure `portal/tmp/sessions/` exists and chmod 775 |
| "Headers already sent" | Check for spaces/BOM before `<?php` in any PHP file |
| 500 error on login | Check `portal/logs/error.log` and Spaceship cPanel error logs |

---

For full details, see full [DEPLOYMENT.md documentation](DEPLOYMENT-FULL.md).

