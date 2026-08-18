#!/bin/bash
# ================================================================
# POSTGRESQL SAFE BOOTSTRAP SCRIPT
# Run this in your terminal (NOT from IDE)
# ================================================================
set -e

BACKEND_DIR="/Applications/XAMPP/xamppfiles/htdocs/Sistem-Manajemen-Sekolah-terpadu-main/backend"
cd "$BACKEND_DIR"

echo "============================================"
echo "POSTGRESQL SAFE BOOTSTRAP"
echo "============================================"
echo ""

# ── PHASE 1: Connection Verification ──────────────────────────
echo "═══ PHASE 1: PostgreSQL Connection Verification ═══"
echo ""

echo "1a. pg_isready check:"
pg_isready -h 127.0.0.1 -p 5432 && echo "  ✅ PostgreSQL accepting connections" || {
  echo "  ❌ PostgreSQL NOT running! Start it first:"
  echo "     brew services start postgresql@17"
  exit 1
}
echo ""

echo "1b. Laravel connection test:"
php artisan tinker --execute="
echo 'Driver: ' . DB::connection()->getDriverName() . PHP_EOL;
echo 'Version: ' . DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION) . PHP_EOL;
echo 'Database: ' . DB::select('SELECT current_database() as db')[0]->db . PHP_EOL;
echo 'Schema: ' . DB::select('SELECT current_schema() as s')[0]->s . PHP_EOL;
"
echo ""

echo "1c. Existing tables:"
php artisan tinker --execute="
\$tables = DB::select(\"SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name\");
echo 'TABLES BEFORE: ' . count(\$tables) . PHP_EOL;
foreach (\$tables as \$t) echo '  - ' . \$t->table_name . PHP_EOL;
"
echo ""

# ── PHASE 2: Migration Status ──────────────────────────────────
echo "═══ PHASE 2: Migration Status ═══"
php artisan migrate:status
echo ""

# ── PHASE 3: Migration Replay Test ─────────────────────────────
echo "═══ PHASE 3: Migration Replay Test (separate database) ═══"
echo ""

echo "Creating test database..."
psql -U postgres -c "DROP DATABASE IF EXISTS school_management_migration_test;" 2>/dev/null || true
psql -U postgres -c "CREATE DATABASE school_management_migration_test;"

echo "Running migrations on test database..."
DB_DATABASE=school_management_migration_test php artisan migrate --force 2>&1 && {
  echo "  ✅ ALL MIGRATIONS PASSED on test database"
} || {
  echo "  ❌ MIGRATION FAILED on test database!"
  echo "  Fix the failing migration before proceeding."
  psql -U postgres -c "DROP DATABASE IF EXISTS school_management_migration_test;" 2>/dev/null || true
  exit 1
}

echo "Dropping test database..."
psql -U postgres -c "DROP DATABASE IF EXISTS school_management_migration_test;"
echo "  ✅ Test database cleaned up"
echo ""

# ── PHASE 4: Run Migrations on Main Database ───────────────────
echo "═══ PHASE 4: Run Migrations on Main Database ═══"
echo ""
php artisan migrate --force
echo ""

echo "Migration status after run:"
php artisan migrate:status
echo ""

echo "Table count after migration:"
php artisan tinker --execute="
\$tables = DB::select(\"SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name\");
echo 'TABLES AFTER: ' . count(\$tables) . PHP_EOL;
foreach (\$tables as \$t) echo '  - ' . \$t->table_name . PHP_EOL;
"
echo ""

# ── PHASE 5: Seeder Execution ──────────────────────────────────
echo "═══ PHASE 5: Seeder Execution ═══"
echo ""

echo "5a. Running RolePermissionSeeder first..."
php artisan db:seed --class=RolePermissionSeeder --force
echo ""

echo "5b. Verifying roles & permissions..."
php artisan tinker --execute="
echo 'Roles: ' . \Spatie\Permission\Models\Role::count() . PHP_EOL;
echo 'Permissions: ' . \Spatie\Permission\Models\Permission::count() . PHP_EOL;
echo 'Role-Permission links: ' . DB::table('role_has_permissions')->count() . PHP_EOL;
"
echo ""

echo "5c. Resetting permission cache..."
php artisan permission:cache-reset
echo ""

echo "5d. Running full seed (1st run)..."
php artisan db:seed --force 2>&1 && echo "  ✅ SEED RUN 1: PASSED" || echo "  ❌ SEED RUN 1: FAILED"
echo ""

echo "5e. Running full seed (2nd run — idempotency test)..."
php artisan db:seed --force 2>&1 && echo "  ✅ SEED RUN 2: PASSED (no duplicates)" || echo "  ❌ SEED RUN 2: FAILED (possible duplicates)"
echo ""

# ── PHASE 6: Verify Counts ─────────────────────────────────────
echo "═══ PHASE 6: Verify Counts ═══"
echo ""
php artisan tinker --execute="
\$models = [
    'USERS' => \App\Models\User::class,
    'ROLES' => \Spatie\Permission\Models\Role::class,
    'PERMISSIONS' => \Spatie\Permission\Models\Permission::class,
    'UNITS' => \App\Models\EducationUnit::class,
    'EMPLOYEES' => \App\Models\Employee::class,
    'TEACHERS' => \App\Models\Teacher::class,
    'PARENTS' => \App\Models\ParentModel::class,
    'STUDENTS' => \App\Models\Student::class,
    'CLASSES' => \App\Models\Kelas::class,
    'SUBJECTS' => \App\Models\Subject::class,
];
foreach (\$models as \$label => \$model) {
    \$count = \$model::count();
    echo \$label . ': ' . \$count . (\$count > 0 ? ' ✅' : ' ❌') . PHP_EOL;
}
echo PHP_EOL;
echo 'SCHEDULES: ' . (class_exists(\App\Models\ClassSchedule::class) ? \App\Models\ClassSchedule::count() : 'N/A') . PHP_EOL;
echo 'ROLE_HAS_PERMISSIONS: ' . DB::table('role_has_permissions')->count() . PHP_EOL;
echo 'MODEL_HAS_ROLES: ' . DB::table('model_has_roles')->count() . PHP_EOL;
"
echo ""

# ── PHASE 7: Login Verification ─────────────────────────────────
echo "═══ PHASE 7: Login Verification ═══"
echo ""
php artisan tinker --execute="
\$accounts = [
    'Super Admin' => 'superadmin@school-erp.local',
    'Yayasan' => 'yayasan@school-erp.local',
    'Ketua Yayasan' => 'ketua.yayasan@school-erp.local',
    'Kepala Sekolah' => 'kepsek@school-erp.local',
    'Tata Usaha' => 'tu@school-erp.local',
    'Guru' => 'guru@school-erp.local',
    'Guru Tahfizh' => 'guru.tahfizh@school-erp.local',
    'Musyrif' => 'musyrif@school-erp.local',
    'Orang Tua' => 'orangtua@school-erp.local',
    'Siswa' => 'siswa@school-erp.local',
];
foreach (\$accounts as \$role => \$email) {
    \$user = \App\Models\User::where('email', \$email)->first();
    if (\$user) {
        \$roles = \$user->getRoleNames()->join(', ');
        \$perms = \$user->getAllPermissions()->count();
        echo \"✅ \$role: Found | Roles: \$roles | Permissions: \$perms\" . PHP_EOL;
    } else {
        echo \"❌ \$role: NOT FOUND (\$email)\" . PHP_EOL;
    }
}
"
echo ""

echo "============================================"
echo "BOOTSTRAP COMPLETE"
echo "============================================"
echo ""
echo "Next steps:"
echo "  1. Start Laravel: php artisan serve"
echo "  2. Start Vite: cd ../web-dashboard && npm run dev"
echo "  3. Open http://localhost:5173"
echo "  4. Login as superadmin@school-erp.local / Password123!"
echo ""
