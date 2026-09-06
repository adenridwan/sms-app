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
    echo ""
    echo "❌ Error: Container ${CONTAINER} tidak berjalan!"
    echo "   Jalankan: cd .. && docker-compose up -d"
    exit 1
fi

echo ""
echo "📋 Step 1: Cek status migration..."
echo "------------------------------------------"
docker exec -it $CONTAINER php artisan migrate:status

echo ""
read -p "Lanjutkan migration? (y/n): " confirm
if [ "$confirm" != "y" ]; then
    echo "Migration dibatalkan."
    exit 0
fi

echo ""
echo "📦 Step 2: Jalankan migration..."
echo "------------------------------------------"
docker exec -it $CONTAINER php artisan migrate --force

echo ""
echo "🔄 Step 3: Reset permission cache (Spatie)..."
echo "------------------------------------------"
docker exec -it $CONTAINER php artisan permission:cache-reset

echo ""
echo "🧹 Step 4: Clear application cache..."
echo "------------------------------------------"
docker exec -it $CONTAINER php artisan cache:clear
docker exec -it $CONTAINER php artisan config:clear
docker exec -it $CONTAINER php artisan view:clear

echo ""
echo "⚡ Step 5: Optimize untuk production..."
echo "------------------------------------------"
docker exec -it $CONTAINER php artisan config:cache
docker exec -it $CONTAINER php artisan route:cache
docker exec -it $CONTAINER php artisan view:cache

echo ""
echo "=========================================="
echo "✅ Migration selesai!"
echo "=========================================="
