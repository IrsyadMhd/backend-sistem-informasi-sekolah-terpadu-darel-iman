<?php
// ==============================================================================
// SCRIPT VERIFIKASI HASIL SEEDING STAGING DARELIMAN EDU
// ==============================================================================
// Cara menjalankan di staging (dalam folder backend):
// php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); require 'path/to/02_verify_staging_seeder.php';"
// ==============================================================================

use App\Models\Student;
use App\Models\User;
use App\Models\EducationUnit;
use App\Models\Role;
use Illuminate\Support\Facades\Http;

$envMode = env('APP_ENV', 'staging');
$isProduction = $envMode === 'production' || filter_var(env('SEED_TEST_ACCOUNTS', true), FILTER_VALIDATE_BOOLEAN) === false;

echo "\n=======================================================\n";
echo "🔍 MEMULAI VERIFIKASI SEEDER DARELIMAN EDU (" . strtoupper($envMode) . " MODE)\n";
echo "=======================================================\n";

// 1. Verifikasi Jumlah Santri & Proteksi Dummy
$totalStudents = Student::count();
$realSipdeiStudents = Student::whereRaw("metadata->>'source' = 'ImportGoogleSheetSiswaSeeder'")->count();
$testAccountStudents = Student::whereRaw("metadata->>'source' IS NULL")->count();
$dummyStudents = Student::whereRaw("metadata->>'source' != 'ImportGoogleSheetSiswaSeeder' AND metadata->>'source' IS NOT NULL")->count();

$targetTotal = $isProduction ? 2549 : 2555;
$targetTest = $isProduction ? 0 : 6;

echo "\n📊 [1] DATA SANTRI & SIPDEI (" . ($isProduction ? "PRODUCTION" : "STAGING") . " TARGET):\n";
echo "  • Total Santri Terdaftar : {$totalStudents} (Target: {$targetTotal})\n";
echo "  • Santri Riil SIPDEI     : {$realSipdeiStudents} (Target: 2549)\n";
echo "  • Akun Uji Coba Terpadu  : {$testAccountStudents} (Target: {$targetTest})\n";
echo "  • Santri Dummy / Palsu   : {$dummyStudents} (Target: 0)\n";

if ($dummyStudents === 0 && $realSipdeiStudents === 2549 && $totalStudents === $targetTotal) {
    echo "  ✅ Status Santri: VALID & BERSIH (0 DUMMY, SESUAI ATURAN " . ($isProduction ? "PRODUCTION" : "STAGING") . ")\n";
} else {
    echo "  ❌ Status Santri: TIDAK SESUAI TARGET!\n";
}

// 2. Verifikasi Unit Pendidikan
$totalUnits = EducationUnit::count();
echo "\n🏫 [2] UNIT PENDIDIKAN YAYASAN:\n";
echo "  • Total Unit Pendidikan  : {$totalUnits} (Target: 16 Unit)\n";
if ($totalUnits === 16) {
    echo "  ✅ Status Unit: VALID (Lengkap 16 Unit)\n";
} else {
    echo "  ⚠️ Perhatian: Jumlah unit terdeteksi {$totalUnits}\n";
}

// 3. Verifikasi Akun Resmi 7 Role
$rolesToVerify = [
    'Super Admin'       => 'superadmin@dareliman.sch.id',
    'Divisi Pendidikan' => 'divisi.pendidikan.test@dareliman.sch.id',
    'Kepala Sekolah'    => 'kepsek.sdit01@dareliman.sch.id',
    'Tata Usaha'        => 'tu.sdit01@dareliman.sch.id',
    'Guru Mapel / Walas'=> 'guru.fullday.test@dareliman.sch.id',
    'Musyrif Asrama'    => 'musyrif.test@dareliman.sch.id',
    'Orang Tua Santri'  => 'orangtua.test@dareliman.sch.id',
];

echo "\n👥 [3] AKUN RESMI 7 ROLE AKSES:\n";
$allRolesFound = true;
foreach ($rolesToVerify as $roleName => $email) {
    $user = User::where('email', $email)->first();
    if ($user) {
        echo "  • [OK] {$roleName}: {$email} (User ID: {$user->id})\n";
    } else {
        echo "  • [MISSING] {$roleName}: {$email} TIDAK DITEMUKAN!\n";
        $allRolesFound = false;
    }
}

if ($allRolesFound) {
    echo "  ✅ Status Akun: LENGKAP & SIAP DIGUNAKAN DI STAGING\n";
} else {
    echo "  ❌ Status Akun: ADA AKUN RESMI YANG BELUM TER-SEED!\n";
}

// 4. Verifikasi Koneksi SIPDEI Live
echo "\n🌐 [4] INTEGRASI KONEKSI API SIPDEI (PARTNER ENDPOINT):\n";
$baseUrl = config('services.sipdei.base_url', env('SIPDEI_BASE_URL', 'https://sipdei.dareliman.tech'));
$apiKey = config('services.sipdei.api_key', env('SIPDEI_API_KEY'));

echo "  • Target URL SIPDEI : {$baseUrl}\n";
try {
    $testNis = '525020003'; // Santri Alifa Rafania di SDIT 2 (NIS Pembayaran SIPDEI)
    $response = Http::withHeaders([
        'X-API-Key' => $apiKey,
        'Accept'    => 'application/json',
    ])->timeout(8)->get("{$baseUrl}/api/partner/tagihan-siswa/{$testNis}/riwayat");

    if ($response->successful()) {
        $data = $response->json();
        $billCount = count($data['data']['tagihan'] ?? []);
        echo "  ✅ Status Koneksi SIPDEI: TERHUBUNG LIVE (HTTP {$response->status()})\n";
        echo "  • Tagihan Santri Uji ({$testNis} - {$data['data']['nama_lengkap']}): {$billCount} data tagihan diterima secara real-time.\n";
    } else {
        echo "  ⚠️ Respon SIPDEI: HTTP {$response->status()} ({$response->body()})\n";
    }
} catch (\Throwable $e) {
    echo "  ⚠️ Gagal Menghubungi SIPDEI: " . $e->getMessage() . "\n";
}

echo "\n=======================================================\n";
echo "🏁 VERIFIKASI SEEDER STAGING SELESAI\n";
echo "=======================================================\n\n";
