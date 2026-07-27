<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\DashboardPemantauanController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EducationUnitController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\FeaturePlaceholderController;
use App\Http\Controllers\Api\V1\HakAksesController;
use App\Http\Controllers\Api\V1\JabatanController;
use App\Http\Controllers\Api\V1\JenisUnitPendidikanController;
use App\Http\Controllers\Api\V1\KelasController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TahfizhController;
use App\Http\Controllers\Api\V1\TeacherController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [DashboardPemantauanController::class, 'ringkasan']);
    Route::get('/dashboard-v1', DashboardController::class);

    Route::prefix('dashboard-pemantauan')->group(function () {
        Route::get('/ringkasan', [DashboardPemantauanController::class, 'ringkasan']);

        Route::get('/pemantauan-divisi', [DashboardPemantauanController::class, 'daftarPemantauanDivisi']);
        Route::post('/pemantauan-divisi', [DashboardPemantauanController::class, 'simpanPemantauanDivisi']);
        Route::get('/pemantauan-divisi/{id}', [DashboardPemantauanController::class, 'detailPemantauanDivisi']);
        Route::put('/pemantauan-divisi/{id}', [DashboardPemantauanController::class, 'ubahPemantauanDivisi']);
        Route::delete('/pemantauan-divisi/{id}', [DashboardPemantauanController::class, 'hapusPemantauanDivisi']);

        Route::get('/laporan-bulanan', [DashboardPemantauanController::class, 'daftarLaporanBulanan']);
        Route::post('/laporan-bulanan', [DashboardPemantauanController::class, 'simpanLaporanBulanan']);
        Route::get('/laporan-bulanan/{id}', [DashboardPemantauanController::class, 'detailLaporanBulanan']);
        Route::put('/laporan-bulanan/{id}', [DashboardPemantauanController::class, 'ubahLaporanBulanan']);
        Route::delete('/laporan-bulanan/{id}', [DashboardPemantauanController::class, 'hapusLaporanBulanan']);

        Route::get('/rekap-prestasi-siswa', [DashboardPemantauanController::class, 'daftarRekapPrestasiSiswa']);
        Route::post('/rekap-prestasi-siswa', [DashboardPemantauanController::class, 'simpanRekapPrestasiSiswa']);
        Route::get('/rekap-prestasi-siswa/{id}', [DashboardPemantauanController::class, 'detailRekapPrestasiSiswa']);
        Route::put('/rekap-prestasi-siswa/{id}', [DashboardPemantauanController::class, 'ubahRekapPrestasiSiswa']);
        Route::delete('/rekap-prestasi-siswa/{id}', [DashboardPemantauanController::class, 'hapusRekapPrestasiSiswa']);

        Route::get('/pengumuman-sekolah', [DashboardPemantauanController::class, 'daftarPengumumanSekolah']);
        Route::post('/pengumuman-sekolah', [DashboardPemantauanController::class, 'simpanPengumumanSekolah']);
        Route::get('/pengumuman-sekolah/{id}', [DashboardPemantauanController::class, 'detailPengumumanSekolah']);
        Route::put('/pengumuman-sekolah/{id}', [DashboardPemantauanController::class, 'ubahPengumumanSekolah']);
        Route::delete('/pengumuman-sekolah/{id}', [DashboardPemantauanController::class, 'hapusPengumumanSekolah']);

        Route::get('/indikator-kinerja-utama', [DashboardPemantauanController::class, 'daftarIndikatorKinerjaUtama']);
        Route::post('/indikator-kinerja-utama', [DashboardPemantauanController::class, 'simpanIndikatorKinerjaUtama']);
        Route::get('/indikator-kinerja-utama/{id}', [DashboardPemantauanController::class, 'detailIndikatorKinerjaUtama']);
        Route::put('/indikator-kinerja-utama/{id}', [DashboardPemantauanController::class, 'ubahIndikatorKinerjaUtama']);
        Route::delete('/indikator-kinerja-utama/{id}', [DashboardPemantauanController::class, 'hapusIndikatorKinerjaUtama']);
    });

    // Direct Database Read Endpoints for Master Data
    Route::get('/employees/dashboard', [EmployeeController::class, 'dashboard']);
    Route::get('/employees/positions', [EmployeeController::class, 'positions']);
    Route::get('/employees/export', [EmployeeController::class, 'export']);
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{employee}', [EmployeeController::class, 'show']);

    Route::post('/employees/import', [EmployeeController::class, 'import']);
    Route::post('/employees/{id}/teachings', [EmployeeController::class, 'assignTeaching']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);

    Route::get('/students/dashboard', [StudentController::class, 'dashboard']);
    Route::apiResource('students', StudentController::class)->except(['create', 'edit']);
    Route::apiResource('education-units', EducationUnitController::class)->except(['create', 'edit']);
    Route::apiResource('teachers', TeacherController::class)->only(['index']);
    Route::apiResource('classes', ClassController::class)->only(['index']);

    // Rute Master Data Kelas / Rombongan Belajar (Rombel)
    Route::get('/kelas/options', [KelasController::class, 'options']);
    Route::get('/kelas/stats', [KelasController::class, 'stats']);
    Route::post('/kelas/import', [KelasController::class, 'import']);
    Route::post('/kelas/{id}/restore', [KelasController::class, 'restore']);
    Route::get('/kelas/{id}/siswa', [KelasController::class, 'siswa']);
    Route::apiResource('kelas', KelasController::class)->except(['create', 'edit']);
    // Rute Master Data Jabatan
    Route::get('/jabatan/options', [JabatanController::class, 'options']);
    Route::get('/jabatan/stats', [JabatanController::class, 'stats']);
    Route::get('/jabatan/export', [JabatanController::class, 'export']);
    Route::post('/jabatan/import', [JabatanController::class, 'import']);
    Route::post('/jabatan/{id}/restore', [JabatanController::class, 'restore']);
    Route::apiResource('jabatan', JabatanController::class);

    // Rute Master Jenis Unit Pendidikan & Mata Pelajaran
    Route::prefix('master')->group(function () {
        Route::get('/jenis-unit/dropdown', [JenisUnitPendidikanController::class, 'dropdown']);
        Route::get('/jenis-unit/stats', [JenisUnitPendidikanController::class, 'stats']);
        Route::get('/jenis-unit/export', [JenisUnitPendidikanController::class, 'export']);
        Route::post('/jenis-unit/import', [JenisUnitPendidikanController::class, 'import']);
        Route::post('/jenis-unit/{id}/restore', [JenisUnitPendidikanController::class, 'restore']);
        Route::apiResource('jenis-unit', JenisUnitPendidikanController::class);

        // Subjects (Mata Pelajaran)
        Route::get('/subjects/dropdown', [SubjectController::class, 'dropdown']);
        Route::get('/subjects/stats', [SubjectController::class, 'stats']);
        Route::post('/subjects/{id}/restore', [SubjectController::class, 'restore']);
        Route::apiResource('subjects', SubjectController::class);
    });

    // Rute Master Hak Akses (Role & Permission — Spatie)
    Route::prefix('hak-akses')->group(function () {
        Route::get('/stats', [HakAksesController::class, 'stats']);

        // Role CRUD
        Route::get('/roles', [HakAksesController::class, 'indexRoles']);
        Route::post('/roles', [HakAksesController::class, 'storeRole']);
        Route::get('/roles/{id}', [HakAksesController::class, 'showRole']);
        Route::put('/roles/{id}', [HakAksesController::class, 'updateRole']);
        Route::delete('/roles/{id}', [HakAksesController::class, 'destroyRole']);

        // Permission CRUD
        Route::get('/permissions', [HakAksesController::class, 'indexPermissions']);
        Route::post('/permissions', [HakAksesController::class, 'storePermission']);
        Route::delete('/permissions/{id}', [HakAksesController::class, 'destroyPermission']);
    });

    Route::post('/attendance/checkin', [AttendanceController::class, 'absenMasuk']);
    Route::post('/attendance/checkout', [AttendanceController::class, 'absenPulang']);
    Route::get('/attendance/report', [AttendanceController::class, 'rekapKehadiran']);

    Route::post('/tahfizh/store', [TahfizhController::class, 'inputSetoran']);
    Route::get('/tahfizh/report', [TahfizhController::class, 'rekapTahfizh']);

    Route::get('/mutabaah', fn () => app(FeaturePlaceholderController::class)('mutabaah'));
    Route::get('/materials', fn () => app(FeaturePlaceholderController::class)('materials'));
    Route::get('/assignments', fn () => app(FeaturePlaceholderController::class)('assignments'));
    Route::get('/exams', fn () => app(FeaturePlaceholderController::class)('exams'));
    Route::get('/alumni', fn () => app(FeaturePlaceholderController::class)('alumni'));
    Route::get('/notifications', fn () => app(FeaturePlaceholderController::class)('notifications'));
});
