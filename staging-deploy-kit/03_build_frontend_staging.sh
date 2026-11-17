#!/bin/bash
# ==============================================================================
# SCRIPT BUILD FRONTEND WEB-DASHBOARD UNTUK SERVER STAGING
# ==============================================================================
# Jalankan script ini di dalam folder: web-dashboard
# ==============================================================================

set -e

# Default Staging API Base URL (Staging Backend: demoapi.dareliman.sch.id)
STAGING_API_URL=${1:-"https://demoapi.dareliman.sch.id/api/v1"}

echo "🚀 [1/3] Menyiapkan Environment Build Staging Web Dashboard..."
echo "  • Target API Endpoint: ${STAGING_API_URL}"

export VITE_API_BASE_URL="${STAGING_API_URL}"

echo "📦 [2/3] Memeriksa Node Modules..."
if [ ! -d "node_modules" ]; then
    npm ci
fi

echo "⚡ [3/3] Menjalankan Kompilasi Produksi (Vite Build)..."
npm run build

echo ""
echo "✅ BUILD WEB DASHBOARD STAGING SELESAI!"
echo "📁 Output berada di: web-dashboard/dist/"
echo "➡️ Salin isi folder 'dist/' ke root web server Nginx/Apache di server staging:"
echo "   Contoh: rsync -avz --delete dist/ user@staging-server:/var/www/dareliman-staging/web-dashboard/dist/"

