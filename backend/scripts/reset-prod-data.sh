#!/usr/bin/env bash
# Mengosongkan SEMUA data (migrate:fresh) lalu hanya seed ulang login &
# otorisasi (permission, role, tenant, user) — tanpa data akademik/master/demo.
#
# TIDAK DIEKSEKUSI OTOMATIS. Jalankan manual setelah backup:
#   bash scripts/reset-prod-data.sh
#
# Prasyarat WAJIB: backup dulu (pg_dump), idealnya dalam maintenance mode.
#
# INSIDEN 2026-08-01: skrip ini pernah dijalankan TANPA backup dan TANPA
# konfirmasi. migrate:fresh berhasil mengosongkan DB_DATABASE aktif, tapi
# seeding berikutnya crash di UserSeeder (cache permission basi -> role
# super_admin "tidak ditemukan"), sehingga proses berhenti sebelum reseed
# selesai dan database (termasuk data non-seed yang sudah dibuat lewat UI)
# hilang tanpa cara pulih. Konfirmasi + cache-reset di bawah menutup kedua
# celah itu.

set -e

cd "$(dirname "$0")/.."

DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d '=' -f2)

echo "Skrip ini akan MENGHAPUS SELURUH DATA di database: ${DB_NAME:-<tidak diketahui>}"
echo "Pastikan Anda SUDAH menjalankan pg_dump untuk backup sebelum lanjut."
read -r -p "Ketik persis 'HAPUS ${DB_NAME}' untuk melanjutkan: " confirm
if [ "$confirm" != "HAPUS ${DB_NAME}" ]; then
    echo "Dibatalkan — konfirmasi tidak cocok."
    exit 1
fi

php artisan down

php artisan migrate:fresh --force

php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=RoleSeeder --force
php artisan permission:cache-reset
php artisan db:seed --class=TenantSeeder --force
php artisan db:seed --class=UserSeeder --force

php artisan up

echo "Selesai. Database bersih, hanya login & otorisasi yang ter-seed."
