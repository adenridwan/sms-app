# User Manual: Docker Migration & Optimasi

Panduan lengkap untuk menjalankan database migration dan optimasi performa di environment Docker.

---

## Daftar Isi

1. [Prasyarat](#prasyarat)
2. [Command Dasar Migration](#command-dasar-migration)
3. [Script Migration Lengkap](#script-migration-lengkap)
4. [Optimasi Performa](#optimasi-performa)
5. [Troubleshooting](#troubleshooting)
6. [Referensi Command](#referensi-command)

---

## Prasyarat

Pastikan Docker container sudah berjalan:

```bash
# Cek container yang berjalan
docker ps

# Harus ada container: sms_php, sms_postgres, sms_redis
# Jika belum jalan:
cd E:\sms-app
docker-compose up -d
```

---

## Command Dasar Migration

### Jalankan Migration

```bash
# Development
docker exec -it sms_php php artisan migrate

# Production (dengan --force)
docker exec -it sms_php php artisan migrate --force
```

### Cek Status Migration

```bash
docker exec -it sms_php php artisan migrate:status
```

### Rollback Migration

```bash
# Rollback 1 batch terakhir
docker exec -it sms_php php artisan migrate:rollback

# Rollback N step
docker exec -it sms_php php artisan migrate:rollback --step=2

# Rollback semua (HATI-HATI!)
docker exec -it sms_php php artisan migrate:reset
```

---

## Script Migration Lengkap

### Lokasi Script

```
E:\sms-app\scripts\docker-migrate.sh
```

### Isi Script

```bash
#!/bin/bash
# ===========================================
# Script: docker-migrate.sh
# Deskripsi: Jalankan migration + optimasi cache di Docker
# Penggunaan: bash scripts/docker-migrate.sh
# ===========================================

set -e

CONTAINER="sms_php"

echo "=========================================="
echo "SMS App - Docker Migration Script"
echo "=========================================="

# Cek container running
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    echo "Error: Container ${CONTAINER} tidak berjalan!"
    echo "Jalankan: docker-compose up -d"
    exit 1
fi

echo ""
echo "Step 1: Cek status migration..."
docker exec -it $CONTAINER php artisan migrate:status

echo ""
read -p "Lanjutkan migration? (y/n): " confirm
if [ "$confirm" != "y" ]; then
    echo "Migration dibatalkan."
    exit 0
fi

echo ""
echo "Step 2: Jalankan migration..."
docker exec -it $CONTAINER php artisan migrate --force

echo ""
echo "Step 3: Reset permission cache (Spatie)..."
docker exec -it $CONTAINER php artisan permission:cache-reset

echo ""
echo "Step 4: Clear application cache..."
docker exec -it $CONTAINER php artisan cache:clear
docker exec -it $CONTAINER php artisan config:clear
docker exec -it $CONTAINER php artisan view:clear

echo ""
echo "Step 5: Optimize untuk production..."
docker exec -it $CONTAINER php artisan config:cache
docker exec -it $CONTAINER php artisan route:cache
docker exec -it $CONTAINER php artisan view:cache

echo ""
echo "=========================================="
echo "Migration selesai!"
echo "=========================================="
```

### Cara Menjalankan

```bash
# Dari folder E:\sms-app
cd E:\sms-app

# Windows (Git Bash / WSL)
bash scripts/docker-migrate.sh

# Linux / Mac
chmod +x scripts/docker-migrate.sh
./scripts/docker-migrate.sh
```

---

## Optimasi Performa

### Setelah Migration Index Baru

Jalankan command berikut untuk mengoptimalkan performa setelah menambahkan index baru:

```bash
# 1. Jalankan migration index
docker exec -it sms_php php artisan migrate --force

# 2. Reset permission cache (WAJIB setelah migration)
docker exec -it sms_php php artisan permission:cache-reset

# 3. Clear semua cache
docker exec -it sms_php php artisan optimize:clear

# 4. Rebuild cache untuk production
docker exec -it sms_php php artisan optimize

# 5. (Opsional) Analyze tabel PostgreSQL untuk optimasi query planner
docker exec -it sms_postgres psql -U sms_user -d sms_db -c "ANALYZE;"
```

### Script Optimasi Lengkap

```bash
#!/bin/bash
# ===========================================
# Script: docker-optimize.sh
# Deskripsi: Optimasi performa aplikasi di Docker
# ===========================================

CONTAINER="sms_php"
DB_CONTAINER="sms_postgres"

echo "Optimizing SMS App..."

# Clear cache
docker exec -it $CONTAINER php artisan optimize:clear

# Reset Spatie permission cache
docker exec -it $CONTAINER php artisan permission:cache-reset

# Rebuild optimization cache
docker exec -it $CONTAINER php artisan optimize

# Analyze PostgreSQL tables
docker exec -it $DB_CONTAINER psql -U sms_user -d sms_db -c "ANALYZE;"

echo "Optimization complete!"
```

---

## Troubleshooting

### Error: Container tidak berjalan

```bash
# Cek status
docker-compose ps

# Start ulang semua container
docker-compose down
docker-compose up -d

# Lihat log jika ada error
docker-compose logs -f php
```

### Error: Migration gagal karena constraint

```bash
# Lihat detail error
docker exec -it sms_php php artisan migrate --pretend

# Rollback dan coba lagi
docker exec -it sms_php php artisan migrate:rollback
docker exec -it sms_php php artisan migrate --force
```

### Error: Permission denied

```bash
# Fix ownership (di dalam container)
docker exec -it sms_php chown -R www-data:www-data /var/www/html/storage
docker exec -it sms_php chmod -R 775 /var/www/html/storage
```

### Error: Memory limit

```bash
# Jalankan dengan memory limit lebih tinggi
docker exec -it sms_php php -d memory_limit=512M artisan migrate --force
```

### Cache tidak ter-clear

```bash
# Clear manual
docker exec -it sms_php rm -rf /var/www/html/bootstrap/cache/*.php
docker exec -it sms_php rm -rf /var/www/html/storage/framework/cache/data/*
docker exec -it sms_php rm -rf /var/www/html/storage/framework/views/*.php

# Rebuild
docker exec -it sms_php php artisan optimize
```

---

## Referensi Command

### Migration Commands

| Command | Deskripsi |
|---------|-----------|
| `php artisan migrate` | Jalankan migration pending |
| `php artisan migrate --force` | Jalankan di production |
| `php artisan migrate --pretend` | Lihat SQL tanpa execute |
| `php artisan migrate:status` | Cek status migration |
| `php artisan migrate:rollback` | Rollback batch terakhir |
| `php artisan migrate:rollback --step=N` | Rollback N migration |
| `php artisan migrate:reset` | Rollback semua |
| `php artisan migrate:refresh` | Reset + migrate ulang |
| `php artisan migrate:fresh` | Drop semua + migrate |

### Cache Commands

| Command | Deskripsi |
|---------|-----------|
| `php artisan cache:clear` | Clear application cache |
| `php artisan config:clear` | Clear config cache |
| `php artisan config:cache` | Cache config |
| `php artisan route:clear` | Clear route cache |
| `php artisan route:cache` | Cache routes |
| `php artisan view:clear` | Clear compiled views |
| `php artisan view:cache` | Cache views |
| `php artisan optimize` | Cache config + routes |
| `php artisan optimize:clear` | Clear semua cache |

### Permission Commands (Spatie)

| Command | Deskripsi |
|---------|-----------|
| `php artisan permission:cache-reset` | Reset permission cache |
| `php artisan permission:show` | Tampilkan permission/role |

### Docker Exec Format

```bash
# Format dasar
docker exec -it sms_php <command>

# Contoh
docker exec -it sms_php php artisan migrate --force
docker exec -it sms_php php artisan tinker
docker exec -it sms_php composer install

# Masuk ke shell container
docker exec -it sms_php bash

# Alternatif dengan docker-compose
docker-compose exec php php artisan migrate --force
```

---

## Migration yang Tersedia untuk Optimasi

### Index Performa (2026_08_14)
- `idx_schedules_teacher_year_active` - Query jadwal guru
- `idx_teacher_classrooms_teacher_year` - Query kelas guru
- `idx_classrooms_homeroom_year` - Query wali kelas
- `idx_student_attendances_date_classroom` - Query absensi harian
- `idx_student_attendances_student_date` - Query absensi per siswa
- `idx_student_fees_student_remaining` - Query tagihan siswa
- `idx_academic_years_active_tenant` - Query tahun ajaran aktif

### Index Dashboard (2026_09_03)
- `idx_classrooms_active` - Count kelas aktif
- `idx_students_active` - Count siswa aktif
- `idx_teachers_active` - Count guru aktif
- `idx_staff_active` - Count staf aktif
- `idx_model_has_roles_model` - Lookup roles per user
- `idx_role_has_permissions_role` - Lookup permissions per role
- `idx_student_enrollments_classroom_active` - Count siswa per kelas
- `idx_payments_completed_recent` - Query pembayaran terbaru

---

## Catatan Penting

1. **Selalu backup database sebelum migration di production**
   ```bash
   docker exec -it sms_postgres pg_dump -U sms_user sms_db > backup_$(date +%Y%m%d).sql
   ```

2. **Jangan gunakan `migrate:fresh` di production** - akan menghapus semua data!

3. **Setelah migration, selalu jalankan:**
   - `permission:cache-reset` - untuk refresh Spatie cache
   - `optimize:clear` + `optimize` - untuk refresh Laravel cache

4. **Monitor performa setelah index baru:**
   ```bash
   # Cek penggunaan index
   docker exec -it sms_postgres psql -U sms_user -d sms_db -c "
     SELECT schemaname, tablename, indexname, idx_scan
     FROM pg_stat_user_indexes
     ORDER BY idx_scan DESC;
   "
   ```
