#!/bin/bash
# ==============================================================================
# SCRIPT DEPLOY & SEED DATABASE BACKEND DI SERVER STAGING
# ==============================================================================
# Jalankan script ini di dalam folder: /var/www/dareliman-staging/backend
# ==============================================================================

set -e

echo "🚀 [1/6] Memeriksa Konfigurasi .env Staging..."
if [ ! -f .env ]; then
    echo "❌ Error: File .env belum ditemukan! Salin .env.staging.example ke .env terlebih dahulu."
    exit 1
fi

# Pastikan SEED_DUMMY_DATA=false
if grep -q "SEED_DUMMY_DATA=true" .env; then
    echo "❌ Error: SEED_DUMMY_DATA bernilai 'true' di .env! Ubah menjadi 'false' sebelum melanjutkan."
    exit 1
fi

echo "📦 [2/6] Memperbarui Dependensi Composer (Production Mode)..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "🔑 [3/6] Memastikan APP_KEY Terpasang..."
php artisan key:generate --force

echo "🌱 [4/6] Menjalankan Migrasi Bersih & Seeder Resmi (SEED_DUMMY_DATA=false)..."
# PERINGATAN: Perintah ini me-reset database staging dan men-seed data master riil SIPDEI
php artisan migrate:fresh --seed --force

echo "🔗 [5/6] Memastikan Storage Symlink Terpasang..."
php artisan storage:link || true

echo "⚡ [6/6] Melakukan Cache Optimasi Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo ""
echo "✅ DEPLOY & SEEDING BACKEND STAGING SELESAI DENGAN SUKSES!"
echo "➡️ Jalankan verifikasi seeder dengan: php artisan tinker --execute=\"require 'verify_staging.php';\""

