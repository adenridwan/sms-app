# SMS Enterprise — First Run Guide (Local & Docker)

This guide explains how to run the app for the first time in two ways:

- **Option A — Local / Manual** (recommended if you are not familiar with Docker)
- **Option B — Docker** (everything runs in containers)

**Tech stack:** Laravel 12 (PHP 8.4) · Inertia + React 18 · Vite · PostgreSQL 16 · Redis (Docker only, optional for local)

---

## Step 0 — Check Required Apps

Open PowerShell and run each command. The version must meet the minimum shown.

| Command | Minimum | Notes |
|---|---|---|
| `php -v` | **8.4** | Must include extensions: `pdo_pgsql`, `pgsql`, `gd`, `intl`, `zip`, `fileinfo`, `curl`, `mbstring`, `exif` |
| `composer -V` | 2.x | https://getcomposer.org |
| `node -v` | **20+** | https://nodejs.org |
| `npm -v` | any | comes with Node |
| `psql --version` | 16 | PostgreSQL — only for **local** option |
| `docker -v` | any recent | Docker Desktop — only for **Docker** option |

Check PHP extensions are enabled:

```powershell
php -m | findstr /i "pdo_pgsql pgsql gd intl zip fileinfo exif"
```

All of those names should appear in the output.

---

## Option A — Run Locally (Manual, no Docker)

### A1. Install PHP 8.4

If you use **Laragon**:

1. Download **PHP 8.4 (x64, Thread Safe, zip)** from https://windows.php.net/download
2. Extract it to `C:\laragon\bin\php\php-8.4.x`
3. Laragon menu → **PHP → Version → php-8.4.x**, or add that folder to your Windows `PATH` (before any older PHP)
4. In that folder, copy `php.ini-development` to `php.ini`, then open `php.ini` and enable these lines (remove the leading `;`):

```ini
extension_dir = "ext"
extension=pdo_pgsql
extension=pgsql
extension=gd
extension=intl
extension=zip
extension=fileinfo
extension=curl
extension=mbstring
extension=exif
extension=openssl
```

5. Close and reopen PowerShell, then verify: `php -v` shows 8.4 and `php -m` shows the extensions.

### A2. Install PostgreSQL 16

1. Download the installer: https://www.postgresql.org/download/windows/
2. During install, set a password for the `postgres` user — **remember it**
3. Keep the default port `5432`
4. Create the database (use pgAdmin, or in PowerShell):

```powershell
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -c "CREATE DATABASE sms_enterprise;"
```

> Redis is **not required** for local mode — the local config uses database/file drivers instead.

### A3. Setup the Laravel backend

```powershell
cd d:\PROJECT\sms-app\backend

# 1. Install PHP dependencies
composer install

# 2. Use the LOCAL env template (not .env.example — that one is for Docker)
copy .env.local.example .env
```

Open `backend\.env` and set your PostgreSQL password:

```ini
DB_PASSWORD=your_postgres_password
```

Then continue:

```powershell
# 3. Generate the application key
php artisan key:generate

# 4. Create tables + demo data
php artisan migrate --seed

# 5. Link public storage
php artisan storage:link
```

### A4. Run the app (2 terminals)

**Terminal 1 — Laravel server:**

```powershell
cd d:\PROJECT\sms-app\backend
php artisan serve
```

**Terminal 2 — Vite dev server (frontend):**

```powershell
cd d:\PROJECT\sms-app\backend
npm install
npm run dev
```

Keep **both** terminals running.

### A5. Open the app

Browser → **http://localhost:8000**

---

## Option B — Run with Docker

### B1. Install Docker Desktop

1. Download: https://www.docker.com/products/docker-desktop/
2. During install, enable **WSL 2** when asked (Windows will guide you)
3. Restart, open Docker Desktop, wait until it says **"Docker Desktop is running"**
4. Verify: `docker -v` and `docker compose version`

### B2. Prepare the environment file

Docker uses the **root** `.env.example` (Redis + Postgres hostnames point to the containers):

```powershell
cd d:\PROJECT\sms-app
copy .env.example .env
copy .env.example backend\.env
```

> Port 80 conflict: if something already uses port 80 (Laragon Apache/Nginx often does — stop Laragon first, or edit `.env` and set `APP_PORT=8080`).

### B3. Build and start the containers

```powershell
cd d:\PROJECT\sms-app

# Build the PHP image (first time only, takes a few minutes)
docker compose build

# Start core services: nginx, php, postgres, redis, horizon, scheduler
docker compose up -d

# Check that everything is running
docker compose ps
```

To also start the dev extras (Vite hot-reload + Mailpit email viewer):

```powershell
docker compose --profile dev up -d
```

### B4. First-time app setup (run once)

```powershell
docker compose exec php composer install
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed
docker compose exec php php artisan storage:link
```

Build the frontend (choose one):

```powershell
# Dev mode with hot-reload (needs --profile dev from B3)
docker compose exec node npm install

# OR a one-time production build without the node container:
docker compose run --rm node sh -c "npm install && npm run build"
```

### B5. Open the app

- App: **http://localhost** (or **http://localhost:8080** if you changed `APP_PORT`)
- Mailpit (email inbox for testing): http://localhost:8025
- Horizon (queue dashboard): http://localhost/horizon

### Daily Docker commands

```powershell
docker compose up -d        # start
docker compose down         # stop
docker compose logs -f php  # watch app logs
docker compose exec php php artisan <command>   # any artisan command
docker compose exec php sh  # shell inside the PHP container
```

(If `make` is available, the `Makefile` has shortcuts: `make up`, `make down`, `make fresh`, `make logs`, etc.)

---

## Demo Login Accounts

| Role | Email | Password |
|---|---|---|
| Super Admin | superadmin@sms.test | password |
| Admin | admin@sms.test | password |
| Kepala Sekolah | kepsek@sms.test | password |
| Guru | guru1@sms.test | password |
| Siswa | siswa.001@sms.test | password |
| Wali Murid | wali1@sms.test | password |

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `composer install` fails: "requires php ^8.4" | Your PATH still points to an old PHP. Run `php -v`, fix PATH / Laragon PHP version, reopen the terminal. |
| `could not find driver (pgsql)` | Enable `extension=pdo_pgsql` and `extension=pgsql` in `php.ini`, then restart the terminal. |
| `SQLSTATE password authentication failed` | `DB_PASSWORD` in `backend\.env` doesn't match your PostgreSQL password. |
| Page loads but no styling / Vite error | Frontend not running: run `npm run dev` (local) or build assets (Docker B4). |
| Docker: port 80 already in use | Stop Laragon/IIS/Skype, or set `APP_PORT=8080` in root `.env` and `docker compose up -d` again. |
| Docker: containers keep restarting | `docker compose logs -f php` to see the error — usually a missing `backend\.env` or app key. |
| Reset database with fresh demo data | `php artisan migrate:fresh --seed` (add `docker compose exec php` in front for Docker). |
