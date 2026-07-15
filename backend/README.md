# SMS Enterprise - School Management System

Sistem Manajemen Sekolah Enterprise dengan Laravel 12 + React + Inertia.js

## Persyaratan Sistem

### Dengan Docker:
- Docker Desktop
- Docker Compose

### Tanpa Docker:
- PHP 8.4+
- Composer 2.x
- Node.js 20+ & npm
- PostgreSQL 16+
- Redis 7+

## Quick Start dengan Docker

```bash
# 1. Clone/masuk ke direktori project
cd E:\sms-app\backend

# 2. Copy file environment
cp .env.example .env

# 3. Jalankan Docker containers
docker-compose up -d

# 4. Install dependencies (dalam container)
docker-compose exec php composer install
docker-compose exec node npm install

# 5. Generate app key
docker-compose exec php php artisan key:generate

# 6. Jalankan migrations & seeders
docker-compose exec php php artisan migrate --seed

# 7. Build frontend assets
docker-compose exec node npm run build

# 8. Akses aplikasi
# Frontend: http://localhost:8080
# Mailpit: http://localhost:8025
# MinIO: http://localhost:9001
```

## Quick Start Tanpa Docker (Manual)

### 1. Setup Database

```bash
# Buat database PostgreSQL
psql -U postgres
CREATE DATABASE sms_enterprise;
CREATE USER sms_user WITH PASSWORD 'password';
GRANT ALL PRIVILEGES ON DATABASE sms_enterprise TO sms_user;
\q
```

### 2. Setup Backend

```bash
# Masuk ke direktori backend
cd E:\sms-app\backend

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Edit .env sesuai konfigurasi lokal
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=sms_enterprise
# DB_USERNAME=sms_user
# DB_PASSWORD=password

# Generate application key
php artisan key:generate

# Jalankan migrations
php artisan migrate

# Jalankan seeders (data demo)
php artisan db:seed

# Link storage
php artisan storage:link

# Jalankan Laravel server
php artisan serve
```

### 3. Setup Frontend

```bash
# Di terminal baru, masuk ke direktori backend
cd E:\sms-app\backend

# Install Node dependencies
npm install

# Development mode (hot reload)
npm run dev

# ATAU build untuk production
npm run build
```

### 4. Menjalankan Queue Worker (Opsional)

```bash
# Di terminal baru
php artisan queue:work

# Atau dengan Horizon
php artisan horizon
```

## Akses Aplikasi

| Service | URL | Keterangan |
|---------|-----|------------|
| Web App | http://localhost:8000 | Aplikasi utama |
| API | http://localhost:8000/api/v1 | REST API |
| Horizon | http://localhost:8000/horizon | Queue Dashboard |

## Akun Demo

Setelah menjalankan seeder, gunakan akun berikut:

| Role | Email | Password |
|------|-------|----------|
| Super Admin | superadmin@sms.test | password |
| Admin | admin@sms.test | password |
| Kepala Sekolah | kepsek@sms.test | password |
| Guru | guru1@sms.test | password |
| Siswa | siswa.001@sms.test | password |
| Wali Murid | wali1@sms.test | password |
| Bendahara | bendahara@sms.test | password |

## Struktur Project

```
backend/
├── app/
│   ├── Domain/           # Business Logic (Entities, Value Objects)
│   ├── Application/      # Use Cases, DTOs
│   ├── Infrastructure/   # Repositories, External Services
│   ├── Http/
│   │   ├── Controllers/  # API & Web Controllers
│   │   ├── Requests/     # Form Validation
│   │   └── Resources/    # API Resources
│   └── Models/           # Eloquent Models
├── resources/
│   ├── js/              # React Components
│   │   ├── components/  # UI Components (shadcn/ui)
│   │   ├── layouts/     # Layout Components
│   │   ├── pages/       # Inertia Pages
│   │   ├── services/    # API Services
│   │   └── types/       # TypeScript Types
│   └── css/             # Tailwind CSS
├── routes/
│   ├── api_v1.php       # API Routes
│   └── web.php          # Web Routes
└── database/
    ├── migrations/      # Database Migrations
    └── seeders/         # Database Seeders
```

## Perintah Berguna

```bash
# Clear all caches
php artisan optimize:clear

# Run tests
php artisan test

# Generate IDE helper (untuk autocomplete)
php artisan ide-helper:generate
php artisan ide-helper:models

# Check code style
./vendor/bin/pint

# Run queue worker
php artisan queue:work --tries=3

# Monitor Horizon
php artisan horizon

# Fresh migration dengan seeder
php artisan migrate:fresh --seed
```

## API Documentation

API endpoint tersedia di `/api/v1/`. Contoh:

```bash
# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@sms.test","password":"password"}'

# Get students (dengan token)
curl http://localhost:8000/api/v1/students \
  -H "Authorization: Bearer {token}"
```

## Troubleshooting

### Error: SQLSTATE[HY000] connection refused
- Pastikan PostgreSQL berjalan
- Periksa konfigurasi DB di .env

### Error: Vite manifest not found
- Jalankan `npm run build` atau `npm run dev`

### Error: Permission denied pada storage
```bash
chmod -R 775 storage bootstrap/cache
```

### Error: Class not found
```bash
composer dump-autoload
php artisan optimize:clear
```

## License

Proprietary - All rights reserved
