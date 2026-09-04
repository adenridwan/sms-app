#!/bin/bash
# ===========================================
# Script: docker-optimize.sh
# Deskripsi: Optimasi performa aplikasi di Docker
# Penggunaan: bash scripts/docker-optimize.sh
# ===========================================

set -e

CONTAINER="sms_php"
DB_CONTAINER="sms_postgres"

echo "=========================================="
echo "SMS App - Docker Optimization Script"
echo "=========================================="

# Cek container running
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    echo ""
    echo "❌ Error: Container ${CONTAINER} tidak berjalan!"
    exit 1
fi

echo ""
echo "🧹 Step 1: Clear semua cache..."
echo "------------------------------------------"
docker exec -it $CONTAINER php artisan optimize:clear

echo ""
echo "🔄 Step 2: Reset permission cache (Spatie)..."
echo "------------------------------------------"
docker exec -it $CONTAINER php artisan permission:cache-reset

echo ""
echo "⚡ Step 3: Rebuild optimization cache..."
echo "------------------------------------------"
docker exec -it $CONTAINER php artisan optimize

echo ""
echo "🗄️  Step 4: Analyze PostgreSQL tables..."
echo "------------------------------------------"
if docker ps --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
    docker exec -it $DB_CONTAINER psql -U sms_user -d sms_db -c "ANALYZE VERBOSE;" 2>/dev/null || echo "   Skipped (tidak bisa connect ke database)"
else
    echo "   Skipped (container database tidak berjalan)"
fi

echo ""
echo "=========================================="
echo "✅ Optimization selesai!"
echo "=========================================="
echo ""
echo "Tips: Untuk melihat penggunaan index, jalankan:"
echo "  docker exec -it sms_postgres psql -U sms_user -d sms_db -c \\"
echo "    \"SELECT indexname, idx_scan FROM pg_stat_user_indexes ORDER BY idx_scan DESC LIMIT 20;\""
echo ""
