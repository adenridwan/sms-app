#!/usr/bin/env bash
# Migrate + seed MINIMAL supaya database bisa dipakai login — TANPA data
# akademik/master/demo (Tahun Ajaran, Kelas, Mapel, siswa, guru, dst).
#
# Aman dijalankan berkali-kali: migrate (bukan migrate:fresh) tidak
# menghapus tabel yang sudah ada, dan seeder di bawah semuanya idempotent
# (firstOrCreate/insertOrIgnore) — tidak akan menduplikasi data yang sudah ada.
#
# Pemakaian:
#   bash scripts/seed-login-only.sh              # database default (.env)
#   bash scripts/seed-login-only.sh --env=testing # ke sms_testing
#   bash scripts/seed-login-only.sh --force       # lewati konfirmasi

set -e

cd "$(dirname "$0")/.."

ENV_FLAG=""
FORCE=""
for arg in "$@"; do
    case "$arg" in
        --env=*) ENV_FLAG="$arg" ;;
        --force) FORCE="yes" ;;
    esac
done

if [ -n "$ENV_FLAG" ]; then
    ENV_NAME="${ENV_FLAG#--env=}"
    DB_NAME=$(grep -E '^DB_DATABASE=' ".env.${ENV_NAME}" 2>/dev/null | cut -d '=' -f2)
else
    DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d '=' -f2)
fi

echo "Target database: ${DB_NAME:-<tidak diketahui>}"
echo "Akan menjalankan: migrate + PermissionSeeder + RoleSeeder + permission:cache-reset + TenantSeeder + UserSeeder"
echo "(tanpa AcademicSeeder/MasterDataSeeder/Demo*Seeder — tidak ada data akademik/demo)"

if [ -z "$FORCE" ]; then
    read -r -p "Lanjutkan? (y/N) " confirm
    if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
        echo "Dibatalkan."
        exit 1
    fi
fi

php artisan migrate --force $ENV_FLAG

php artisan db:seed --class=PermissionSeeder --force $ENV_FLAG
php artisan db:seed --class=RoleSeeder --force $ENV_FLAG
php artisan permission:cache-reset $ENV_FLAG
php artisan db:seed --class=TenantSeeder --force $ENV_FLAG
php artisan db:seed --class=UserSeeder --force $ENV_FLAG

echo "Selesai. Login dengan superadmin@sms.local / password (segera ganti passwordnya)."
