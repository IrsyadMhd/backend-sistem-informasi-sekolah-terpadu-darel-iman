<?php

use App\Http\Controllers\Api\Approval\DeleteRequestController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\QrCredentialController;
use App\Http\Controllers\Api\LmsBankSoalController;
use App\Http\Controllers\Api\LmsKisiKisiController;
use App\Http\Controllers\Api\LmsPenilaianController;
use App\Http\Controllers\Api\LmsUjianController;
use App\Http\Controllers\Api\V1\AlumniController;
use App\Http\Controllers\Api\V1\AlumniPortalController;
use App\Http\Controllers\Api\V1\AttendanceCaptureController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AttendanceWorkflowController;
use App\Http\Controllers\Api\V1\CapaianPembelajaranController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DashboardPemantauanController;
use App\Http\Controllers\Api\V1\DivisionController;
use App\Http\Controllers\Api\V1\EducationUnitController;
use App\Http\Controllers\Api\V1\EmployeeChatController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EQuranController;
use App\Http\Controllers\Api\V1\FeaturePlaceholderController;
use App\Http\Controllers\Api\V1\DivisiPendidikanDashboardController;
use App\Http\Controllers\Api\V1\FoundationDashboardController;
use App\Http\Controllers\Api\V1\GuruBkDashboardController;
use App\Http\Controllers\Api\V1\GuruTahfizhDashboardController;
use App\Http\Controllers\Api\V1\KepalaSekolahDashboardController;
use App\Http\Controllers\Api\V1\OperatorDashboardController;
use App\Http\Controllers\Api\V1\SuperAdminDashboardController;
use App\Http\Controllers\Api\V1\TataUsahaDashboardController;
use App\Http\Controllers\Api\V1\WakaKesiswaanDashboardController;
use App\Http\Controllers\Api\V1\WakaKurikulumDashboardController;
use App\Http\Controllers\Api\V1\WaliKelasDashboardController;
use App\Http\Controllers\Api\V1\FoundationReportController;
use App\Http\Controllers\Api\V1\GateAttendanceController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\HakAksesController;
use App\Http\Controllers\Api\V1\JabatanController;
use App\Http\Controllers\Api\V1\JenisUnitPendidikanController;
use App\Http\Controllers\Api\V1\KelasController;
use App\Http\Controllers\Api\V1\LmsAktivitasBelajarController;
use App\Http\Controllers\Api\V1\LmsDiskusiController;
use App\Http\Controllers\Api\V1\LmsMateriController;
use App\Http\Controllers\Api\V1\LmsMediaController;
use App\Http\Controllers\Api\V1\LmsModulAjarController;
use App\Http\Controllers\Api\V1\LmsPengumpulanTugasController;
use App\Http\Controllers\Api\V1\LmsPenugasanController;
Route::get('/seed-sdit1-data', function () {
    try {
        $unit = \App\Models\EducationUnit::where('code', 'SDIT-01')->first()
            ?? \App\Models\EducationUnit::where('name', 'LIKE', '%SDIT 1%')->first()
            ?? \App\Models\EducationUnit::first();

        if (! $unit) {
            return response()->json(['error' => 'No unit found'], 404);
        }

        $unitId = $unit->id;

        // 1. Assign unit_id to all unassigned students & employees
        \App\Models\Student::whereNull('unit_id')->update(['unit_id' => $unitId]);
        \App\Models\Employee::whereNull('unit_id')->update(['unit_id' => $unitId]);

        // 2. Ensure 20 Teachers for SDIT 1
        $pos = \App\Models\Jabatan::first();
        $posId = $pos ? $pos->id : null;

        $additionalGuru = [
            ['nama' => 'Ust. Zulkifli, S.Pd.I', 'gender' => 'L'],
            ['nama' => 'Ustdh. Laila Fitri, S.Pd', 'gender' => 'P'],
            ['nama' => 'Ust. Ilham Rabbani, M.Pd', 'gender' => 'L'],
            ['nama' => 'Ustdh. Fatimah Syahidah, S.Pd.I', 'gender' => 'P'],
            ['nama' => 'Ust. Hamzah As-Suyuthi, S.Pd', 'gender' => 'L'],
            ['nama' => 'Ustdh. Aisyah Humaira, S.Si', 'gender' => 'P'],
            ['nama' => 'Ust. Salman Al-Farisi, S.Hum', 'gender' => 'L'],
            ['nama' => 'Ustdh. Maryam Khadijah, S.Pd', 'gender' => 'P'],
            ['nama' => 'Ust. Bilal Ramadan, S.Or', 'gender' => 'L'],
            ['nama' => 'Ustdh. Zahra Nurul, S.Pd.I', 'gender' => 'P'],
            ['nama' => 'Ust. Umar Khadafi, S.Pd', 'gender' => 'L'],
            ['nama' => 'Ustdh. Khadijah Laila, S.Pd', 'gender' => 'P'],
            ['nama' => 'Ust. Ali Imran, S.Pd.I', 'gender' => 'L'],
            ['nama' => 'Ustdh. Safiyah Azzahra, S.Sos', 'gender' => 'P'],
            ['nama' => 'Ust. Hasan Basri, S.Kom', 'gender' => 'L'],
            ['nama' => 'Ustdh. Mutia Rahmi, S.Pd', 'gender' => 'P'],
            ['nama' => 'Ust. Ridwan Syah, S.Pd.I', 'gender' => 'L'],
            ['nama' => 'Ustdh. Nurul Aini, S.Pd', 'gender' => 'P'],
            ['nama' => 'Ust. Faruq Al-Hafizh, S.Pd.I', 'gender' => 'L'],
            ['nama' => 'Ustdh. Annisa Thahirah, S.Pd', 'gender' => 'P'],
        ];

        foreach ($additionalGuru as $idx => $g) {
            $niy = 'NIY-202409'.str_pad($idx + 10, 3, '0', STR_PAD_LEFT);
            \App\Models\Employee::updateOrCreate(
                ['niy' => $niy],
                [
                    'unit_id' => $unitId,
                    'jabatan_id' => $posId,
                    'nama_lengkap' => str_replace(['Ust. ', 'Ustdh. '], '', $g['nama']),
                    'nama_panggilan' => $g['nama'],
                    'jenis_kelamin' => $g['gender'],
                    'status_pegawai' => 'Tetap',
                    'status' => 'Aktif',
                    'email' => strtolower(str_replace(['Ust. ', 'Ustdh. ', ' ', ',', '.'], '', $g['nama'])).'@dareliman.sch.id',
                    'no_hp' => '08127000'.str_pad($idx + 10, 4, '0', STR_PAD_LEFT),
                    'alamat' => 'Jl. Lima Puluh Kota No. '.($idx + 10),
                ]
            );
        }

        // 3. Ensure 20 Students for SDIT 1
        $additionalSiswa = [
            ['nis' => '23011', 'nama' => 'Bilal Ramadan', 'gender' => 'male', 'kelas' => 'Kelas 5A'],
            ['nis' => '23012', 'nama' => 'Laila Fitriani', 'gender' => 'female', 'kelas' => 'Kelas 5B'],
            ['nis' => '23013', 'nama' => 'Zulkifli Mansur', 'gender' => 'male', 'kelas' => 'Kelas 6A'],
            ['nis' => '23014', 'nama' => 'Safiyah Nabila', 'gender' => 'female', 'kelas' => 'Kelas 6B'],
            ['nis' => '23015', 'nama' => 'Ali Imran Al-Hafizh', 'gender' => 'male', 'kelas' => 'Kelas 5A'],
            ['nis' => '23016', 'nama' => 'Maryam Salimah', 'gender' => 'female', 'kelas' => 'Kelas 5B'],
            ['nis' => '23017', 'nama' => 'Salman Al-Farisi', 'gender' => 'male', 'kelas' => 'Kelas 6A'],
            ['nis' => '23018', 'nama' => 'Hasan Al-Banna', 'gender' => 'male', 'kelas' => 'Kelas 6B'],
            ['nis' => '23019', 'nama' => 'Mutia Rahmah', 'gender' => 'female', 'kelas' => 'Kelas 5A'],
            ['nis' => '23020', 'nama' => 'Ilham Rabbani', 'gender' => 'male', 'kelas' => 'Kelas 6A'],
            ['nis' => '23021', 'nama' => 'Annisa Thahirah', 'gender' => 'female', 'kelas' => 'Kelas 4A'],
            ['nis' => '23022', 'nama' => 'Faruq Abdillah', 'gender' => 'male', 'kelas' => 'Kelas 4A'],
            ['nis' => '23023', 'nama' => 'Siti Nurhaliza', 'gender' => 'female', 'kelas' => 'Kelas 4B'],
            ['nis' => '23024', 'nama' => 'Usman Al-Qarni', 'gender' => 'male', 'kelas' => 'Kelas 4B'],
            ['nis' => '23025', 'nama' => 'Ruqayyah Mufidah', 'gender' => 'female', 'kelas' => 'Kelas 3A'],
            ['nis' => '23026', 'nama' => 'Zubair Bin Awwam', 'gender' => 'male', 'kelas' => 'Kelas 3A'],
            ['nis' => '23027', 'nama' => 'Juwayriyah Azizah', 'gender' => 'female', 'kelas' => 'Kelas 3B'],
            ['nis' => '23028', 'nama' => 'Talhah Ubaidillah', 'gender' => 'male', 'kelas' => 'Kelas 3B'],
            ['nis' => '23029', 'nama' => 'Ummu Sulaim', 'gender' => 'female', 'kelas' => 'Kelas 2A'],
            ['nis' => '23030', 'nama' => 'Sa\'ad Bin Abi Waqqas', 'gender' => 'male', 'kelas' => 'Kelas 2A'],
        ];

        foreach ($additionalSiswa as $s) {
            \App\Models\Student::updateOrCreate(
                ['nis' => $s['nis']],
                [
                    'unit_id' => $unitId,
                    'full_name' => $s['nama'],
                    'gender' => $s['gender'],
                    'birth_place' => '50 Kota',
                    'birth_date' => '2015-05-10',
                    'address' => 'Jl. Lima Puluh Kota',
                    'is_active' => true,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'SDIT 1 Data Seeded Successfully!',
            'employees_sdit1_count' => \App\Models\Employee::where('unit_id', $unitId)->count(),
            'students_sdit1_count' => \App\Models\Student::where('unit_id', $unitId)->count(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage(), 'line' => $e->getLine()], 500);
    }
});

Route::get('/seed-all-units', function (\Illuminate\Http\Request $request) {
    set_time_limit(300);
    ini_set('memory_limit', '512M');
    if ($request->query('check')) {
        $unitBreakdown = \App\Models\EducationUnit::all()->map(function ($u) {
            return [
                'id' => $u->id,
                'code' => $u->code,
                'name' => $u->name,
                'students_count' => \App\Models\Student::where('unit_id', $u->id)->count(),
                'employees_count' => \App\Models\Employee::where('unit_id', $u->id)->count(),
                'classes_count' => \App\Models\Kelas::where('unit_pendidikan_id', $u->id)->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'units_count' => \App\Models\EducationUnit::count(),
            'employees_count' => \App\Models\Employee::count(),
            'students_count' => \App\Models\Student::count(),
            'breakdown' => $unitBreakdown,
        ]);
    }
    $results = [];
    try {
        try {
            (new \Database\Seeders\DataDummyUnitPendidikanSeeder())->run();
            $results['units_seeder'] = 'OK';
        } catch (\Throwable $e) {
            $results['units_seeder_error'] = $e->getMessage() . ' line ' . $e->getLine() . ' in ' . $e->getFile();
        }

        try {
            (new \Database\Seeders\MasterJabatanSeeder())->run();
            $results['jabatan_seeder'] = 'OK';
        } catch (\Throwable $e) {
            $results['jabatan_seeder_error'] = $e->getMessage() . ' line ' . $e->getLine() . ' in ' . $e->getFile();
        }

        try {
            (new \Database\Seeders\DataDummyPegawaiSeeder())->run();
            $results['pegawai_seeder'] = 'OK';
        } catch (\Throwable $e) {
            $results['pegawai_seeder_error'] = $e->getMessage() . ' line ' . $e->getLine() . ' in ' . $e->getFile();
        }

        try {
            (new \Database\Seeders\DataDummySiswaSeeder())->run();
            $results['siswa_seeder'] = 'OK';
        } catch (\Throwable $e) {
            $results['siswa_seeder_error'] = $e->getMessage() . ' line ' . $e->getLine() . ' in ' . $e->getFile();
        }

        try {
            (new \Database\Seeders\DefaultRoleUserSeeder())->run();
            $results['default_role_user_seeder'] = 'OK';
        } catch (\Throwable $e) {
            $results['default_role_user_seeder_error'] = $e->getMessage() . ' line ' . $e->getLine() . ' in ' . $e->getFile();
        }

        if (request()->has('check')) {
            $unitBreakdown = \App\Models\EducationUnit::all()->map(function ($u) {
                return [
                    'id' => $u->id,
                    'code' => $u->code,
                    'name' => $u->name,
                    'students_count' => \App\Models\Student::where('unit_id', $u->id)->count(),
                    'employees_count' => \App\Models\Employee::where('unit_id', $u->id)->count(),
                    'classes_count' => \App\Models\Kelas::where('unit_pendidikan_id', $u->id)->count(),
                ];
            });

            return response()->json([
                'success' => true,
                'units_count' => \App\Models\EducationUnit::count(),
                'employees_count' => \App\Models\Employee::count(),
                'students_count' => \App\Models\Student::count(),
                'breakdown' => $unitBreakdown,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Proses seeding 15 Unit Pendidikan selesai!',
            'results' => $results,
            'units_count' => \App\Models\EducationUnit::count(),
            'employees_count' => \App\Models\Employee::count(),
            'students_count' => \App\Models\Student::count(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
    }
});
use App\Http\Controllers\Api\V1\LmsRaporController;
use App\Http\Controllers\Api\V1\LmsReferensiController;
use App\Http\Controllers\Api\V1\MasterKurikulumController;
use App\Http\Controllers\Api\V1\ModulSemesterController;
use App\Http\Controllers\Api\V1\MutabaahAnalyticsController;
use App\Http\Controllers\Api\V1\MutabaahDailyController;
use App\Http\Controllers\Api\V1\MutabaahEnterpriseController;
use App\Http\Controllers\Api\V1\MutabaahPortalController;
use App\Http\Controllers\Api\V1\MusyrifModuleController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\SiteSettingController;
use App\Http\Controllers\Api\V1\Step04TeacherController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentParentPortalController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TahfizhController;
use App\Http\Controllers\Api\V1\TahunAjaranController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\TeacherPortalController;
use App\Http\Controllers\Api\V1\TujuanPembelajaranController;
use App\Http\Controllers\Api\V1\UserAccountController;
use App\Http\Controllers\Api\V1\WilayahController;
use App\Http\Controllers\Api\V1\WorshipAttendanceController;
use Illuminate\Support\Facades\Route;

Route::get('/site-settings', [SiteSettingController::class, 'show']);
Route::get('/equran/surah', [EQuranController::class, 'surahs']);
Route::get('/equran/surah/{id}', [EQuranController::class, 'show']);
Route::get('/equran/jadwal-sholat', [EQuranController::class, 'jadwalSholat']);

// Wilayah / Region Endpoints
Route::get('/v1/wilayah/provinsi', [WilayahController::class, 'provinsi']);
Route::get('/v1/wilayah/kota', [WilayahController::class, 'kota']);
Route::get('/v1/wilayah/kecamatan', [WilayahController::class, 'kecamatan']);
Route::get('/v1/wilayah/kelurahan', [WilayahController::class, 'kelurahan']);

Route::get('/wilayah/provinsi', [WilayahController::class, 'provinsi']);
Route::get('/wilayah/kota', [WilayahController::class, 'kota']);
Route::get('/wilayah/kecamatan', [WilayahController::class, 'kecamatan']);
Route::get('/wilayah/kelurahan', [WilayahController::class, 'kelurahan']);

// Doa & Dzikir Endpoints (Standard /api/doa & /api/equran/doa)
Route::get('/doa', [EQuranController::class, 'doas']);
Route::get('/doa/{id}', [EQuranController::class, 'doaDetail']);

Route::get('/equran/doa', [EQuranController::class, 'doas']);
Route::get('/equran/doa/{id}', [EQuranController::class, 'doaDetail']);

// Shalat Master Data & EQuran API v2 Compatibility Endpoints
Route::get('/v2/shalat/provinsi', [EQuranController::class, 'provinsi']);
Route::get('/shalat/provinsi', [EQuranController::class, 'provinsi']);
Route::post('/v2/shalat/kabkota', [EQuranController::class, 'kabkota']);
Route::post('/shalat/kabkota', [EQuranController::class, 'kabkota']);
Route::post('/v2/shalat', [EQuranController::class, 'shalat']);
Route::post('/shalat', [EQuranController::class, 'shalat']);

Route::get('/v2/shalat/master-list', [EQuranController::class, 'masterList']);
Route::get('/shalat/master-list', [EQuranController::class, 'masterList']);

Route::middleware(['auth:sanctum', 'can:sistem.master_data'])->group(function () {
    Route::post('/equran/surah', [EQuranController::class, 'store']);
    Route::put('/equran/surah/{id}', [EQuranController::class, 'update']);
    Route::delete('/equran/surah/{id}', [EQuranController::class, 'destroy']);
    Route::post('/equran/sync-surah', [EQuranController::class, 'syncSurah']);

    Route::post('/doa', [EQuranController::class, 'storeDoa']);
    Route::put('/doa/{id}', [EQuranController::class, 'updateDoa']);
    Route::delete('/doa/{id}', [EQuranController::class, 'destroyDoa']);
    Route::post('/doa/sync', [EQuranController::class, 'syncDoa']);

    Route::post('/equran/doa', [EQuranController::class, 'storeDoa']);
    Route::put('/equran/doa/{id}', [EQuranController::class, 'updateDoa']);
    Route::delete('/equran/doa/{id}', [EQuranController::class, 'destroyDoa']);
    Route::post('/equran/sync-doa', [EQuranController::class, 'syncDoa']);

    Route::post('/v2/shalat/save-master', [EQuranController::class, 'saveMaster']);
    Route::post('/shalat/save-master', [EQuranController::class, 'saveMaster']);
    Route::delete('/v2/shalat/master-list/{id}', [EQuranController::class, 'deleteMaster']);
    Route::delete('/shalat/master-list/{id}', [EQuranController::class, 'deleteMaster']);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/login/admin', [AuthController::class, 'loginAdmin'])->middleware('throttle:10,1');
    Route::post('/login/employee', [AuthController::class, 'loginEmployee'])->middleware('throttle:10,1');
    Route::post('/login/employee-qr', [AuthController::class, 'loginEmployeeQr'])->middleware('throttle:10,1');
    Route::post('/login/parent-student', [AuthController::class, 'loginParentStudent'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::get('/me', [AuthController::class, 'profile']);
        Route::post('/impersonate', [AuthController::class, 'impersonate']);

        Route::middleware('can:sistem.master_data')->group(function () {
            Route::post('/qr/employee/{employeeId}', [QrCredentialController::class, 'generateEmployeeQr']);
            Route::post('/qr/student/{studentId}', [QrCredentialController::class, 'generateStudentQr']);
            Route::post('/qr/{id}/revoke', [QrCredentialController::class, 'revokeQr']);
        });
    });
});

Route::prefix('v2/auth')->group(function () {
    Route::post('/login/admin', [AuthController::class, 'loginAdmin'])->middleware('throttle:10,1');
    Route::post('/login/employee', [AuthController::class, 'loginEmployee'])->middleware('throttle:10,1');
    Route::post('/login/employee-qr', [AuthController::class, 'loginEmployeeQr'])->middleware('throttle:10,1');
    Route::post('/login/parent-student', [AuthController::class, 'loginParentStudent'])->middleware('throttle:10,1');
});

Route::middleware('auth:sanctum')->prefix('v2/approval')->group(function () {
    Route::get('/delete-requests', [DeleteRequestController::class, 'index']);
    Route::post('/delete-requests', [DeleteRequestController::class, 'store']);
    Route::post('/delete-requests/{id}/approve', [DeleteRequestController::class, 'approve']);
    Route::post('/delete-requests/{id}/reject', [DeleteRequestController::class, 'reject']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Foundation Board Dashboard Monitoring Endpoints
    Route::prefix('foundation')->middleware('can:foundation.dashboard.view')->group(function () {
        Route::get('/dashboard', [FoundationDashboardController::class, 'index']);
        Route::get('/units', [FoundationDashboardController::class, 'units']);
        Route::get('/units/{id}', [FoundationDashboardController::class, 'unitDetail']);
        Route::get('/units/{id}/structure', [FoundationDashboardController::class, 'unitStructure']);
        Route::get('/structure', [FoundationDashboardController::class, 'structure']);
        Route::get('/employees', [FoundationDashboardController::class, 'employees']);
        Route::get('/employees/{id}', [FoundationDashboardController::class, 'employeeDetail']);
        Route::get('/teachers', [FoundationDashboardController::class, 'teachers']);
        Route::get('/teachers/{id}', [FoundationDashboardController::class, 'teacherDetail']);
        Route::get('/students', [FoundationDashboardController::class, 'students']);
        Route::get('/students/{id}', [FoundationDashboardController::class, 'studentDetail']);
        Route::get('/new-students', [FoundationDashboardController::class, 'newStudents']);
        Route::get('/student-mutations', [FoundationDashboardController::class, 'studentMutations']);
        Route::get('/graduations', [FoundationDashboardController::class, 'graduations']);
        Route::get('/alumni', [FoundationDashboardController::class, 'alumni']);
        Route::get('/alumni/{id}', [FoundationDashboardController::class, 'alumniDetail']);
        Route::get('/parents', [FoundationDashboardController::class, 'parents']);
        Route::get('/parents/{id}', [FoundationDashboardController::class, 'parentDetail']);
        Route::get('/classes', [FoundationDashboardController::class, 'classes']);
        Route::get('/classes/{id}', [FoundationDashboardController::class, 'classDetail']);
        Route::get('/rombel', [FoundationDashboardController::class, 'rombel']);
        Route::get('/rombel/{id}', [FoundationDashboardController::class, 'rombelDetail']);
        Route::get('/information', [FoundationDashboardController::class, 'information']);
        Route::get('/reports', [FoundationDashboardController::class, 'reports'])
            ->middleware('can:foundation.report.view');

        // Comprehensive Foundation Reports
        Route::prefix('laporan')->middleware('can:foundation.report.view')->group(function () {
            Route::get('/sdm', [FoundationReportController::class, 'sdm']);
            Route::get('/sdm/detail/{id}', [FoundationReportController::class, 'sdmDetail']);
            Route::get('/siswa', [FoundationReportController::class, 'siswa']);
            Route::get('/siswa/detail/{id}', [FoundationReportController::class, 'siswaDetail']);
            Route::get('/mutasi', [FoundationReportController::class, 'mutasi']);
            Route::get('/mutasi/detail/{id}', [FoundationReportController::class, 'mutasiDetail']);
            Route::get('/kelulusan', [FoundationReportController::class, 'kelulusan']);
            Route::get('/kelulusan/detail/{id}', [FoundationReportController::class, 'kelulusanDetail']);
            Route::get('/alumni', [FoundationReportController::class, 'alumni']);
            Route::get('/alumni/detail/{id}', [FoundationReportController::class, 'alumniDetail']);
            Route::get('/lintas-unit', [FoundationReportController::class, 'lintasUnit']);
            Route::get('/prestasi', [FoundationReportController::class, 'prestasi']);
            Route::get('/prestasi/detail/{id}', [FoundationReportController::class, 'prestasiDetail']);
            Route::get('/{type}/export', [FoundationReportController::class, 'export'])
                ->middleware('can:foundation.report.export');
        });
    });

    Route::prefix('parent/mutabaah')->group(function () {
        Route::get('/children', [MutabaahPortalController::class, 'children']);
        Route::post('/{dailyHeaderId}/signature', [MutabaahPortalController::class, 'signature'])->whereUuid('dailyHeaderId');
        Route::get('/{studentId}/history', [MutabaahPortalController::class, 'parentHistory'])->whereUuid('studentId');
        Route::get('/{studentId}', [MutabaahPortalController::class, 'parentOverview'])->whereUuid('studentId');
    });
    Route::prefix('student/mutabaah')->group(function () {
        Route::get('/', [MutabaahPortalController::class, 'studentOverview']);
        Route::get('/history', [MutabaahPortalController::class, 'studentHistory']);
    });
    Route::prefix('mutabaah')->group(function () {
        Route::get('/analytics/dashboard', [MutabaahAnalyticsController::class, 'dashboard']);
        Route::get('/analytics/recap', [MutabaahAnalyticsController::class, 'recap']);
        Route::get('/analytics/recap/export', [MutabaahAnalyticsController::class, 'export']);
        Route::get('/daily/context', [MutabaahDailyController::class, 'context']);
        Route::get('/daily/students', [MutabaahDailyController::class, 'students']);
        Route::post('/daily/save-cell', [MutabaahDailyController::class, 'saveCell']);
        Route::post('/daily/bulk-save', [MutabaahDailyController::class, 'bulkSave']);
        Route::post('/daily/copy-previous-day', [MutabaahDailyController::class, 'copyPreviousDay']);
        Route::post('/daily/finalize-student', [MutabaahDailyController::class, 'finalizeStudent']);
        Route::post('/daily/finalize-bulk', [MutabaahDailyController::class, 'finalizeBulk']);
        Route::post('/daily/reopen', [MutabaahDailyController::class, 'reopen']);
        Route::get('/daily/{studentId}', [MutabaahDailyController::class, 'show'])->whereUuid('studentId');

        // Compatibility alias for the existing dashboard client. Keep this
        // scoped through the enterprise handler instead of the legacy options
        // endpoint, which returned every student and employee.
        Route::get('/options', [MutabaahEnterpriseController::class, 'options']);
        Route::get('/enterprise/options', [MutabaahEnterpriseController::class, 'options']);
        Route::get('/enterprise/audit', [MutabaahEnterpriseController::class, 'audit']);
        Route::get('/enterprise/{resource}/export', [MutabaahEnterpriseController::class, 'export']);
        Route::post('/enterprise/{resource}/import', [MutabaahEnterpriseController::class, 'import']);
        Route::delete('/enterprise/{resource}/bulk', [MutabaahEnterpriseController::class, 'bulkDelete']);
        Route::post('/enterprise/{resource}/{id}/restore', [MutabaahEnterpriseController::class, 'restore']);
        Route::delete('/enterprise/{resource}/{id}/force', [MutabaahEnterpriseController::class, 'forceDelete']);
        Route::get('/enterprise/{resource}', [MutabaahEnterpriseController::class, 'index']);
        Route::post('/enterprise/{resource}', [MutabaahEnterpriseController::class, 'store']);
        Route::get('/enterprise/{resource}/{id}', [MutabaahEnterpriseController::class, 'show']);
        Route::put('/enterprise/{resource}/{id}', [MutabaahEnterpriseController::class, 'update']);
        Route::delete('/enterprise/{resource}/{id}', [MutabaahEnterpriseController::class, 'destroy']);

        foreach (['categories', 'agendas', 'templates', 'template-assignments', 'supervisor-assignments'] as $resource) {
            Route::get("/{$resource}", [MutabaahEnterpriseController::class, 'index'])->defaults('resource', $resource);
            Route::post("/{$resource}", [MutabaahEnterpriseController::class, 'store'])->defaults('resource', $resource);
            Route::delete("/{$resource}/bulk", [MutabaahEnterpriseController::class, 'bulkDelete'])->defaults('resource', $resource);
            Route::post("/{$resource}/bulk-restore", [MutabaahEnterpriseController::class, 'bulkRestore'])->defaults('resource', $resource);
            Route::get("/{$resource}/{id}", [MutabaahEnterpriseController::class, 'show'])->defaults('resource', $resource);
            Route::put("/{$resource}/{id}", [MutabaahEnterpriseController::class, 'update'])->defaults('resource', $resource);
            Route::delete("/{$resource}/{id}", [MutabaahEnterpriseController::class, 'destroy'])->defaults('resource', $resource);
            Route::post("/{$resource}/{id}/restore", [MutabaahEnterpriseController::class, 'restore'])->defaults('resource', $resource);
            Route::delete("/{$resource}/{id}/force", [MutabaahEnterpriseController::class, 'forceDelete'])->defaults('resource', $resource);
        }
        Route::post('/templates/{id}/items', [MutabaahEnterpriseController::class, 'storeTemplateItem'])->defaults('resource', 'template-items');
        Route::put('/template-items/{id}', [MutabaahEnterpriseController::class, 'updateTemplateItem'])->defaults('resource', 'template-items');
        Route::delete('/template-items/{id}', [MutabaahEnterpriseController::class, 'destroyTemplateItem']);
        Route::post('/templates/{id}/reorder', [MutabaahEnterpriseController::class, 'reorderTemplate']);

    });
    Route::post('/site-settings', [SiteSettingController::class, 'update'])
        ->middleware('can:sistem.pengaturan');
    Route::get('/dashboard', [DashboardPemantauanController::class, 'ringkasan'])
        ->middleware('can:dashboard.pemantauan.lihat');
    Route::get('/dashboard/super-admin', [SuperAdminDashboardController::class, 'index'])
        ->middleware('can:dashboard.super-admin.view');
    Route::get('/dashboard/kepala-sekolah', [KepalaSekolahDashboardController::class, 'index'])
        ->middleware('can:dashboard.kepala-sekolah.view');
    Route::get('/dashboard/divisi-pendidikan', [DivisiPendidikanDashboardController::class, 'index'])
        ->middleware('can:dashboard.divisi-pendidikan.view');
    Route::get('/dashboard/divisi-pendidikan/kpi/{type}', [DivisiPendidikanDashboardController::class, 'kpiDetail'])
        ->middleware('can:dashboard.divisi-pendidikan.view');
    Route::get('/dashboard/waka-kurikulum', [WakaKurikulumDashboardController::class, 'index'])
        ->middleware('can:dashboard.waka-kurikulum.view');
    Route::get('/dashboard/waka-kesiswaan', [WakaKesiswaanDashboardController::class, 'index'])
        ->middleware('can:dashboard.waka-kesiswaan.view');
    Route::get('/dashboard/tata-usaha', [TataUsahaDashboardController::class, 'index'])
        ->middleware('can:dashboard.tata-usaha.view');
    Route::get('/dashboard/tata-usaha/kpi/{type}', [TataUsahaDashboardController::class, 'kpiDetail'])
        ->middleware('can:dashboard.tata-usaha.view');
    Route::get('/dashboard/wali-kelas', [WaliKelasDashboardController::class, 'index'])
        ->middleware('can:dashboard.guru.view');
    Route::get('/dashboard/guru-tahfizh', [GuruTahfizhDashboardController::class, 'index'])
        ->middleware('can:dashboard.guru-tahfizh.view');
    Route::get('/dashboard/guru-bk', [GuruBkDashboardController::class, 'index'])
        ->middleware('can:dashboard.guru-bk.view');
    Route::get('/dashboard/operator', [OperatorDashboardController::class, 'index'])
        ->middleware('can:dashboard.operator.view');
    Route::get('/dashboard-v1', DashboardController::class)
        ->middleware('can:dashboard.view');

    Route::prefix('dashboard-pemantauan')->group(function () {
        Route::middleware('can:dashboard.pemantauan.lihat')->group(function () {
            Route::get('/ringkasan', [DashboardPemantauanController::class, 'ringkasan']);
            Route::get('/pemantauan-divisi', [DashboardPemantauanController::class, 'daftarPemantauanDivisi']);
            Route::get('/pemantauan-divisi/{id}', [DashboardPemantauanController::class, 'detailPemantauanDivisi']);
            Route::get('/laporan-bulanan', [DashboardPemantauanController::class, 'daftarLaporanBulanan']);
            Route::get('/laporan-bulanan/{id}', [DashboardPemantauanController::class, 'detailLaporanBulanan']);
            Route::get('/rekap-prestasi-siswa', [DashboardPemantauanController::class, 'daftarRekapPrestasiSiswa']);
            Route::get('/rekap-prestasi-siswa/{id}', [DashboardPemantauanController::class, 'detailRekapPrestasiSiswa']);
            Route::get('/pengumuman-sekolah', [DashboardPemantauanController::class, 'daftarPengumumanSekolah']);
            Route::get('/pengumuman-sekolah/{id}', [DashboardPemantauanController::class, 'detailPengumumanSekolah']);
            Route::get('/indikator-kinerja-utama', [DashboardPemantauanController::class, 'daftarIndikatorKinerjaUtama']);
            Route::get('/indikator-kinerja-utama/{id}', [DashboardPemantauanController::class, 'detailIndikatorKinerjaUtama']);
        });

        Route::middleware(['can:dashboard.pemantauan.kelola', 'can:divisi.monitoring'])->group(function () {
            Route::post('/pemantauan-divisi', [DashboardPemantauanController::class, 'simpanPemantauanDivisi']);
            Route::put('/pemantauan-divisi/{id}', [DashboardPemantauanController::class, 'ubahPemantauanDivisi']);
            Route::delete('/pemantauan-divisi/{id}', [DashboardPemantauanController::class, 'hapusPemantauanDivisi']);
        });

        Route::middleware(['can:dashboard.pemantauan.kelola', 'can:divisi.laporan_bulanan'])->group(function () {
            Route::post('/laporan-bulanan', [DashboardPemantauanController::class, 'simpanLaporanBulanan']);
            Route::put('/laporan-bulanan/{id}', [DashboardPemantauanController::class, 'ubahLaporanBulanan']);
            Route::delete('/laporan-bulanan/{id}', [DashboardPemantauanController::class, 'hapusLaporanBulanan']);
        });

        Route::middleware('can:dashboard.pemantauan.kelola')->group(function () {
            Route::post('/rekap-prestasi-siswa', [DashboardPemantauanController::class, 'simpanRekapPrestasiSiswa']);
            Route::put('/rekap-prestasi-siswa/{id}', [DashboardPemantauanController::class, 'ubahRekapPrestasiSiswa']);
            Route::delete('/rekap-prestasi-siswa/{id}', [DashboardPemantauanController::class, 'hapusRekapPrestasiSiswa']);
            Route::post('/pengumuman-sekolah', [DashboardPemantauanController::class, 'simpanPengumumanSekolah']);
            Route::put('/pengumuman-sekolah/{id}', [DashboardPemantauanController::class, 'ubahPengumumanSekolah']);
            Route::delete('/pengumuman-sekolah/{id}', [DashboardPemantauanController::class, 'hapusPengumumanSekolah']);
            Route::post('/indikator-kinerja-utama', [DashboardPemantauanController::class, 'simpanIndikatorKinerjaUtama']);
            Route::put('/indikator-kinerja-utama/{id}', [DashboardPemantauanController::class, 'ubahIndikatorKinerjaUtama']);
            Route::delete('/indikator-kinerja-utama/{id}', [DashboardPemantauanController::class, 'hapusIndikatorKinerjaUtama']);
        });
    });

    Route::get('/teacher-monitoring', [Step04TeacherController::class, 'monitoring'])
        ->middleware('can:teacher_monitoring.view');

    // Direct Database Read Endpoints for Master Data
    Route::get('/employees/dashboard', [EmployeeController::class, 'dashboard'])
        ->middleware('permission:employee.view|employee.view_all|foundation.employee.view|sistem.master_data');
    Route::get('/employees/positions', [EmployeeController::class, 'positions'])
        ->middleware('permission:employee.view|employee.view_all|foundation.employee.view|sistem.master_data');
    Route::get('/employees/export', [EmployeeController::class, 'export'])
        ->middleware('permission:employee.export|foundation.employee.view|sistem.master_data');
    Route::get('/employees', [EmployeeController::class, 'index'])
        ->middleware('permission:employee.view|employee.view_all|foundation.employee.view|sistem.master_data');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])
        ->middleware('permission:employee.view|employee.view_all|foundation.employee.view|sistem.master_data');

    Route::post('/employees/import', [EmployeeController::class, 'import'])
        ->middleware('permission:employee.import|sistem.master_data');
    Route::post('/employees/{id}/teachings', [EmployeeController::class, 'assignTeaching'])
        ->middleware('permission:employee.update|academic.schedule.update|sistem.master_data');
    Route::post('/employees', [EmployeeController::class, 'store'])
        ->middleware('permission:employee.create|sistem.master_data');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])
        ->middleware('permission:employee.update|sistem.master_data');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
        ->middleware('permission:employee.delete|sistem.master_data');

    Route::get('/students/dashboard', [StudentController::class, 'dashboard'])
        ->middleware('permission:student.view|student.view_all|kesiswaan.data_lengkap_siswa|foundation.student.view|sistem.master_data');
    Route::apiResource('students', StudentController::class)->only(['index', 'show'])
        ->middleware('permission:student.view|student.view_all|kesiswaan.data_lengkap_siswa|foundation.student.view|sistem.master_data');
    Route::apiResource('students', StudentController::class)->only(['store'])
        ->middleware('permission:student.create|sistem.master_data');
    Route::apiResource('students', StudentController::class)->only(['update'])
        ->middleware('permission:student.update|student.edit|academic.manage|academic.view|kesiswaan.data_lengkap_siswa|foundation.student.view|sistem.master_data');
    Route::apiResource('students', StudentController::class)->only(['destroy'])
        ->middleware('permission:student.delete|sistem.master_data');
    Route::get('/student-card-settings', [\App\Http\Controllers\Api\V1\StudentCardSettingController::class, 'show']);
    Route::post('/student-card-settings', [\App\Http\Controllers\Api\V1\StudentCardSettingController::class, 'store']);
    Route::apiResource('education-units', EducationUnitController::class)->only(['index', 'show'])
        ->middleware('permission:unit.view|unit.view_all|foundation.unit.view|sistem.master_data');
    Route::apiResource('education-units', EducationUnitController::class)->only(['store'])
        ->middleware('permission:unit.create|sistem.master_data');
    Route::apiResource('education-units', EducationUnitController::class)->only(['update'])
        ->middleware('permission:unit.update|sistem.master_data');
    Route::apiResource('education-units', EducationUnitController::class)->only(['destroy'])
        ->middleware('permission:unit.delete|sistem.master_data');
    Route::apiResource('teachers', TeacherController::class)->only(['index'])
        ->middleware('permission:employee.view|employee.view_all|foundation.teacher.view|sistem.master_data');
    Route::apiResource('classes', ClassController::class)->only(['index'])
        ->middleware('permission:kesiswaan.kelas_rombel|academic.schedule.view|sistem.master_data');

    // Rute Master Data Kelas / Rombongan Belajar (Rombel)
    Route::get('/kelas/options', [KelasController::class, 'options'])
        ->middleware('permission:kesiswaan.kelas_rombel|academic.schedule.view|sistem.master_data');
    Route::get('/kelas/stats', [KelasController::class, 'stats'])
        ->middleware('permission:kesiswaan.kelas_rombel|academic.schedule.view|sistem.master_data');
    Route::post('/kelas/import', [KelasController::class, 'import'])
        ->middleware('permission:master.create|academic.schedule.create|sistem.master_data');
    Route::post('/kelas/{id}/restore', [KelasController::class, 'restore'])
        ->middleware('permission:master.update|academic.schedule.update|sistem.master_data');
    Route::get('/kelas/{id}/siswa', [KelasController::class, 'siswa'])
        ->middleware('permission:kesiswaan.kelas_rombel|academic.schedule.view|sistem.master_data');
    Route::apiResource('kelas', KelasController::class)->only(['index', 'show'])
        ->middleware('permission:kesiswaan.kelas_rombel|academic.schedule.view|master.view|sistem.master_data');
    Route::apiResource('kelas', KelasController::class)->only(['store'])
        ->middleware('permission:master.create|academic.schedule.create|sistem.master_data');
    Route::apiResource('kelas', KelasController::class)->only(['update'])
        ->middleware('permission:master.update|academic.schedule.update|sistem.master_data');
    Route::apiResource('kelas', KelasController::class)->only(['destroy'])
        ->middleware('permission:master.delete|academic.schedule.delete|sistem.master_data');
    // Rute Master Data Jabatan
    Route::get('/jabatan/options', [JabatanController::class, 'options'])
        ->middleware('permission:master.view|sistem.master_data');
    Route::get('/jabatan/stats', [JabatanController::class, 'stats'])
        ->middleware('permission:master.view|sistem.master_data');
    Route::get('/jabatan/export', [JabatanController::class, 'export'])
        ->middleware('permission:master.view|report.export|sistem.master_data');
    Route::post('/jabatan/import', [JabatanController::class, 'import'])
        ->middleware('permission:master.create|master.update|sistem.master_data');
    Route::post('/jabatan/{id}/restore', [JabatanController::class, 'restore'])
        ->middleware('permission:master.update|employee.position.manage|sistem.master_data');
    Route::apiResource('jabatan', JabatanController::class)->only(['index', 'show'])
        ->middleware('permission:master.view|sistem.master_data');
    Route::apiResource('jabatan', JabatanController::class)->only(['store'])
        ->middleware('permission:master.create|sistem.master_data');
    Route::apiResource('jabatan', JabatanController::class)->only(['update'])
        ->middleware('permission:master.update|employee.position.manage|sistem.master_data');
    Route::apiResource('jabatan', JabatanController::class)->only(['destroy'])
        ->middleware('permission:master.delete|employee.position.manage|sistem.master_data');

    // Rute Master Jenis Unit Pendidikan & Mata Pelajaran
    Route::prefix('master')->group(function () {
        Route::get('/jenis-unit/dropdown', [JenisUnitPendidikanController::class, 'dropdown'])
            ->middleware('permission:master.view|sistem.master_data');
        Route::get('/jenis-unit/stats', [JenisUnitPendidikanController::class, 'stats'])
            ->middleware('permission:master.view|sistem.master_data');
        Route::get('/jenis-unit/export', [JenisUnitPendidikanController::class, 'export'])
            ->middleware('permission:master.view|report.export|sistem.master_data');
        Route::post('/jenis-unit/import', [JenisUnitPendidikanController::class, 'import'])
            ->middleware('permission:master.create|master.update|sistem.master_data');
        Route::post('/jenis-unit/{id}/restore', [JenisUnitPendidikanController::class, 'restore'])
            ->middleware('permission:master.update|sistem.master_data');
        Route::apiResource('jenis-unit', JenisUnitPendidikanController::class)->only(['index', 'show'])
            ->middleware('permission:master.view|sistem.master_data');
        Route::apiResource('jenis-unit', JenisUnitPendidikanController::class)->only(['store'])
            ->middleware('permission:master.create|sistem.master_data');
        Route::apiResource('jenis-unit', JenisUnitPendidikanController::class)->only(['update'])
            ->middleware('permission:master.update|sistem.master_data');
        Route::apiResource('jenis-unit', JenisUnitPendidikanController::class)->only(['destroy'])
            ->middleware('permission:master.delete|sistem.master_data');

        // Subjects (Mata Pelajaran)
        Route::get('/subjects/dropdown', [SubjectController::class, 'dropdown'])
            ->middleware('permission:academic.subject.view|master.view|sistem.master_data');
        Route::get('/subjects/stats', [SubjectController::class, 'stats'])
            ->middleware('permission:academic.subject.view|master.view|sistem.master_data');
        Route::post('/subjects/bulk-status', [SubjectController::class, 'bulkStatus'])
            ->middleware('permission:academic.subject.update|sistem.master_data');
        Route::post('/subjects/bulk-delete', [SubjectController::class, 'bulkDelete'])
            ->middleware('permission:academic.subject.delete|sistem.master_data');
        Route::get('/subjects/export/excel', [SubjectController::class, 'exportExcel'])
            ->middleware('permission:academic.subject.view|report.export|sistem.master_data');
        Route::get('/subjects/export/pdf', [SubjectController::class, 'exportPdf'])
            ->middleware('permission:academic.subject.view|report.export|sistem.master_data');
        Route::post('/subjects/import', [SubjectController::class, 'import'])
            ->middleware('permission:academic.subject.create|academic.subject.update|sistem.master_data');
        Route::post('/subjects/{id}/restore', [SubjectController::class, 'restore'])
            ->middleware('permission:academic.subject.update|sistem.master_data');
        Route::apiResource('subjects', SubjectController::class)->only(['index', 'show'])
            ->middleware('permission:academic.subject.view|master.view|sistem.master_data');
        Route::apiResource('subjects', SubjectController::class)->only(['store'])
            ->middleware('permission:academic.subject.create|sistem.master_data');
        Route::apiResource('subjects', SubjectController::class)->only(['update'])
            ->middleware('permission:academic.subject.update|sistem.master_data');
        Route::apiResource('subjects', SubjectController::class)->only(['destroy'])
            ->middleware('permission:academic.subject.delete|sistem.master_data');

        // Tahun Ajaran (Academic Year)
        Route::get('/tahun-ajaran/dropdown', [TahunAjaranController::class, 'dropdown'])
            ->middleware('permission:master.view|sistem.master_data');
        Route::get('/tahun-ajaran/stats', [TahunAjaranController::class, 'stats'])
            ->middleware('permission:master.view|sistem.master_data');
        Route::get('/tahun-ajaran/export', [TahunAjaranController::class, 'export'])
            ->middleware('permission:master.view|report.export|sistem.master_data');
        Route::post('/tahun-ajaran/import', [TahunAjaranController::class, 'import'])
            ->middleware('permission:master.create|master.update|sistem.master_data');
        Route::post('/tahun-ajaran/{id}/set-aktif', [TahunAjaranController::class, 'setAktif'])
            ->middleware('permission:master.update|sistem.master_data');
        Route::post('/tahun-ajaran/{id}/restore', [TahunAjaranController::class, 'restore'])
            ->middleware('permission:master.update|sistem.master_data');
        Route::apiResource('tahun-ajaran', TahunAjaranController::class)->only(['index', 'show'])
            ->middleware('permission:master.view|sistem.master_data');
        Route::apiResource('tahun-ajaran', TahunAjaranController::class)->only(['store'])
            ->middleware('permission:master.create|sistem.master_data');
        Route::apiResource('tahun-ajaran', TahunAjaranController::class)->only(['update'])
            ->middleware('permission:master.update|sistem.master_data');
        Route::apiResource('tahun-ajaran', TahunAjaranController::class)->only(['destroy'])
            ->middleware('permission:master.delete|sistem.master_data');

        // Master Modul Semester
        Route::get('/modul-semester/options', [ModulSemesterController::class, 'options'])
            ->middleware('permission:master.view|sistem.master_data');
        Route::get('/modul-semester/stats', [ModulSemesterController::class, 'stats'])
            ->middleware('permission:master.view|sistem.master_data');
        Route::post('/modul-semester/{id}/restore', [ModulSemesterController::class, 'restore'])
            ->middleware('permission:master.update|sistem.master_data');
        Route::post('/modul-semester/{id}/duplicate', [ModulSemesterController::class, 'duplicate'])
            ->middleware('permission:master.create|sistem.master_data');
        Route::post('/modul-semester/{id}/toggle-status', [ModulSemesterController::class, 'toggleStatus'])
            ->middleware('permission:master.update|sistem.master_data');
        Route::apiResource('modul-semester', ModulSemesterController::class)->only(['index', 'show'])
            ->middleware('permission:master.view|sistem.master_data');
        Route::apiResource('modul-semester', ModulSemesterController::class)->only(['store'])
            ->middleware('permission:master.create|sistem.master_data');
        Route::apiResource('modul-semester', ModulSemesterController::class)->only(['update'])
            ->middleware('permission:master.update|sistem.master_data');
        Route::apiResource('modul-semester', ModulSemesterController::class)->only(['destroy'])
            ->middleware('permission:master.delete|sistem.master_data');

        // Master Kurikulum
        Route::get('/kurikulum/dropdown', [MasterKurikulumController::class, 'dropdown'])
            ->middleware('permission:academic.curriculum.view|master.view|sistem.master_data');
        Route::get('/kurikulum/stats', [MasterKurikulumController::class, 'stats'])
            ->middleware('permission:academic.curriculum.view|master.view|sistem.master_data');
        Route::get('/kurikulum/export', [MasterKurikulumController::class, 'export'])
            ->middleware('permission:academic.curriculum.view|report.export|sistem.master_data');
        Route::post('/kurikulum/import', [MasterKurikulumController::class, 'import'])
            ->middleware('permission:academic.curriculum.create|academic.curriculum.update|sistem.master_data');
        Route::post('/kurikulum/{id}/restore', [MasterKurikulumController::class, 'restore'])
            ->middleware('permission:academic.curriculum.update|sistem.master_data');
        Route::apiResource('kurikulum', MasterKurikulumController::class)->only(['index', 'show'])
            ->middleware('permission:academic.curriculum.view|master.view|sistem.master_data');
        Route::apiResource('kurikulum', MasterKurikulumController::class)->only(['store'])
            ->middleware('permission:academic.curriculum.create|sistem.master_data');
        Route::apiResource('kurikulum', MasterKurikulumController::class)->only(['update'])
            ->middleware('permission:academic.curriculum.update|sistem.master_data');
        Route::apiResource('kurikulum', MasterKurikulumController::class)->only(['destroy'])
            ->middleware('permission:academic.curriculum.delete|sistem.master_data');

        // Capaian Pembelajaran (CP)
        Route::get('/capaian-pembelajaran/dropdown', [CapaianPembelajaranController::class, 'dropdown'])
            ->middleware('permission:academic.curriculum.view|master.view|sistem.master_data');
        Route::get('/capaian-pembelajaran/stats', [CapaianPembelajaranController::class, 'stats'])
            ->middleware('permission:academic.curriculum.view|master.view|sistem.master_data');
        Route::post('/capaian-pembelajaran/{id}/restore', [CapaianPembelajaranController::class, 'restore'])
            ->middleware('permission:academic.curriculum.update|sistem.master_data');
        Route::apiResource('capaian-pembelajaran', CapaianPembelajaranController::class)->only(['index', 'show'])
            ->middleware('permission:academic.curriculum.view|master.view|sistem.master_data');
        Route::apiResource('capaian-pembelajaran', CapaianPembelajaranController::class)->only(['store'])
            ->middleware('permission:academic.curriculum.create|sistem.master_data');
        Route::apiResource('capaian-pembelajaran', CapaianPembelajaranController::class)->only(['update'])
            ->middleware('permission:academic.curriculum.update|sistem.master_data');
        Route::apiResource('capaian-pembelajaran', CapaianPembelajaranController::class)->only(['destroy'])
            ->middleware('permission:academic.curriculum.delete|sistem.master_data');
    });

    // Rute Master Hak Akses (Role & Permission — Spatie)
    Route::prefix('hak-akses')->middleware('permission:sistem.hak_akses|employee.role_access.manage')->group(function () {
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

        // Pegawai Hak Akses (Menarik Data Pegawai)
        Route::get('/pegawai', [HakAksesController::class, 'indexPegawaiHakAkses']);
        Route::post('/pegawai/{id}/assign-role', [HakAksesController::class, 'assignPegawaiRole']);

        // CRUD akun login dan reset password
        Route::get('/users', [UserAccountController::class, 'index']);
        Route::post('/users', [UserAccountController::class, 'store']);
        Route::get('/users/{user}', [UserAccountController::class, 'show']);
        Route::put('/users/{user}', [UserAccountController::class, 'update']);
        Route::put('/users/{user}/password', [UserAccountController::class, 'resetPassword']);
        Route::delete('/users/{user}', [UserAccountController::class, 'destroy']);
    });

    Route::get('/attendance/stats', [AttendanceController::class, 'stats']);
    Route::post('/attendance/checkin', [AttendanceController::class, 'absenMasuk']);
    Route::post('/attendance/checkout', [AttendanceController::class, 'absenPulang']);
    Route::get('/attendance/report', [AttendanceController::class, 'rekapKehadiran']);
    Route::apiResource('attendance', AttendanceController::class);

    // Presensi pembelajaran berbasis jadwal (route lama /lms/presensi tetap dipertahankan).
    Route::prefix('lesson-attendance')->group(function () {
        Route::get('/active-schedules', [AttendanceWorkflowController::class, 'activeSchedules']);
        Route::get('/my-schedules', [AttendanceWorkflowController::class, 'schedules']);
        Route::get('/my-schedules/{schedule}/students', [AttendanceWorkflowController::class, 'scheduleStudents']);
        Route::get('/sessions', [AttendanceWorkflowController::class, 'sessions']);
        Route::post('/sessions', [AttendanceWorkflowController::class, 'storeSession']);
        Route::get('/sessions/{session}', [AttendanceWorkflowController::class, 'showSession']);
        Route::post('/sessions/{session}/finalize', [AttendanceWorkflowController::class, 'finalize']);
        Route::post('/sessions/{session}/unlock', [AttendanceWorkflowController::class, 'unlock']);
        Route::post('/sessions/{session}/cancel', [AttendanceWorkflowController::class, 'cancelSession']);
        Route::get('/corrections', [AttendanceWorkflowController::class, 'corrections']);
        Route::post('/corrections', [AttendanceWorkflowController::class, 'correction']);
        Route::post('/corrections/{correction}/review', [AttendanceWorkflowController::class, 'reviewCorrection']);
        Route::post('/corrections/{correction}/cancel', [AttendanceWorkflowController::class, 'cancelCorrection']);
        Route::get('/report', [AttendanceWorkflowController::class, 'report']);
        Route::post('/sessions/{session}/start-session', [AttendanceCaptureController::class, 'start']);
        Route::post('/sessions/{session}/close-session', [AttendanceCaptureController::class, 'close']);
        Route::post('/sessions/{session}/manual-check', [AttendanceCaptureController::class, 'manual']);
        Route::post('/sessions/{session}/scan/{method}', [AttendanceCaptureController::class, 'scan'])->whereIn('method', ['qr', 'rfid', 'barcode', 'face']);
        Route::post('/identify-card/{method}', [AttendanceCaptureController::class, 'identifyCard'])->whereIn('method', ['qr', 'rfid']);
        Route::get('/sessions/{session}/scan-logs', [AttendanceCaptureController::class, 'logs']);
        Route::get('/students/{student}/qr-token', [AttendanceCaptureController::class, 'studentToken']);
    });
    Route::get('/student-attendance/me', [AttendanceWorkflowController::class, 'myAttendance'])->middleware('role:Siswa|siswa|student');
    Route::get('/student-attendance/permissions', [AttendanceWorkflowController::class, 'permissions'])->middleware('role:Siswa|siswa|student');
    // Siswa may read their history, but leave/sick transactions are parent-controlled.
    Route::post('/student-attendance/permissions', [AttendanceWorkflowController::class, 'permissions'])->middleware('role:Orang Tua|orang_tua|Orangtua|Wali Murid|parent');
    Route::put('/student-attendance/permissions/{permission}', [AttendanceWorkflowController::class, 'updatePermission'])->middleware('role:Orang Tua|orang_tua|Orangtua|Wali Murid|parent');
    Route::post('/student-attendance/permissions/{permission}/submit', [AttendanceWorkflowController::class, 'submitPermission'])->middleware('role:Orang Tua|orang_tua|Orangtua|Wali Murid|parent');
    Route::post('/student-attendance/permissions/{permission}/cancel', [AttendanceWorkflowController::class, 'cancelPermission'])->middleware('role:Orang Tua|orang_tua|Orangtua|Wali Murid|parent');
    Route::get('/homeroom-attendance/permissions', [AttendanceWorkflowController::class, 'homeroomPermissions']);
    Route::post('/homeroom-attendance/permissions/{permission}/review', [AttendanceWorkflowController::class, 'reviewPermission']);
    Route::get('/homeroom-attendance/dashboard', [AttendanceWorkflowController::class, 'homeroomDashboard']);
    Route::match(['get', 'post'], '/homeroom-attendance/follow-ups', [AttendanceWorkflowController::class, 'followUps']);
    Route::put('/homeroom-attendance/follow-ups/{followUp}', [AttendanceWorkflowController::class, 'updateFollowUp']);
    Route::post('/homeroom-attendance/follow-ups/{followUp}/complete', [AttendanceWorkflowController::class, 'completeFollowUp']);
    Route::post('/homeroom-attendance/follow-ups/{followUp}/close', [AttendanceWorkflowController::class, 'closeFollowUp']);

    Route::post('/attendance/devices/heartbeat', [AttendanceCaptureController::class, 'heartbeat'])->withoutMiddleware('auth:sanctum');
    Route::post('/attendance/devices/events/fingerprint', [AttendanceCaptureController::class, 'fingerprint'])->withoutMiddleware('auth:sanctum');

    // URL publik API Modul Absensi; route lama tetap aktif untuk kompatibilitas.
    Route::get('/attendance/teacher/dashboard', [AttendanceWorkflowController::class, 'teacherDashboard']);
    Route::get('/attendance/homeroom/dashboard', [AttendanceWorkflowController::class, 'homeroomDashboard']);
    Route::get('/attendance/student/me', [AttendanceWorkflowController::class, 'myAttendance']);
    Route::get('/attendance/teacher/schedules', [AttendanceWorkflowController::class, 'schedules']);
    Route::get('/attendance/schedules/{schedule}/students', [AttendanceWorkflowController::class, 'scheduleStudents']);

    Route::get('/lesson-attendances', [AttendanceWorkflowController::class, 'sessions']);
    Route::post('/lesson-attendances', [AttendanceWorkflowController::class, 'storeSession']);
    Route::get('/lesson-attendances/{session}', [AttendanceWorkflowController::class, 'showSession']);
    Route::put('/lesson-attendances/{session}', [AttendanceWorkflowController::class, 'updateSession']);
    Route::post('/lesson-attendances/{session}/finalize', [AttendanceWorkflowController::class, 'finalize']);
    Route::post('/lesson-attendances/{session}/unlock', [AttendanceWorkflowController::class, 'unlock']);
    Route::post('/lesson-attendances/{session}/cancel', [AttendanceWorkflowController::class, 'cancelSession']);

    Route::get('/attendance-permissions', [AttendanceWorkflowController::class, 'permissionIndex']);
    Route::post('/attendance-permissions', [AttendanceWorkflowController::class, 'permissionCreate']);
    Route::get('/attendance-permissions/{permission}', [AttendanceWorkflowController::class, 'showPermission']);
    Route::put('/attendance-permissions/{permission}', [AttendanceWorkflowController::class, 'updatePermission']);
    Route::post('/attendance-permissions/{permission}/submit', [AttendanceWorkflowController::class, 'submitPermission']);
    Route::post('/attendance-permissions/{permission}/{action}', [AttendanceWorkflowController::class, 'permissionReviewAction'])->whereIn('action', ['approve', 'reject', 'revision']);
    Route::post('/attendance-permissions/{permission}/cancel', [AttendanceWorkflowController::class, 'cancelPermission']);

    Route::get('/attendance-corrections', [AttendanceWorkflowController::class, 'corrections']);
    Route::post('/attendance-corrections', [AttendanceWorkflowController::class, 'correction']);
    Route::get('/attendance-corrections/{correction}', [AttendanceWorkflowController::class, 'showCorrection']);
    Route::post('/attendance-corrections/{correction}/{action}', [AttendanceWorkflowController::class, 'correctionReviewAction'])->whereIn('action', ['approve', 'reject']);
    Route::post('/attendance-corrections/{correction}/cancel', [AttendanceWorkflowController::class, 'cancelCorrection']);

    Route::get('/attendance-follow-ups', [AttendanceWorkflowController::class, 'followUps']);
    Route::post('/attendance-follow-ups', [AttendanceWorkflowController::class, 'followUps']);
    Route::get('/attendance-follow-ups/{followUp}', [AttendanceWorkflowController::class, 'showFollowUp']);
    Route::put('/attendance-follow-ups/{followUp}', [AttendanceWorkflowController::class, 'updateFollowUp']);
    Route::post('/attendance-follow-ups/{followUp}/complete', [AttendanceWorkflowController::class, 'completeFollowUp']);
    Route::post('/attendance-follow-ups/{followUp}/close', [AttendanceWorkflowController::class, 'closeFollowUp']);
    Route::get('/attendance/reports/summary', [AttendanceWorkflowController::class, 'report']);
    Route::get('/attendance/reports/export', [AttendanceWorkflowController::class, 'report']);

    Route::get('/tahfizh/weekly-sheet', [TahfizhController::class, 'getWeeklySheet']);
    Route::post('/tahfizh/daily-log', [TahfizhController::class, 'saveDailyLog']);
    Route::post('/tahfizh/upload-audio', [TahfizhController::class, 'uploadAudio']);
    Route::get('/tahfizh/student-progress/{studentId}', [TahfizhController::class, 'getStudentProgress']);
    Route::post('/tahfizh/store', [TahfizhController::class, 'inputSetoran']);
    Route::get('/tahfizh/report', [TahfizhController::class, 'rekapTahfizh']);

    Route::get('/mutabaah', fn () => app(FeaturePlaceholderController::class)('mutabaah'));
    Route::get('/materials', fn () => app(FeaturePlaceholderController::class)('materials'));
    Route::get('/assignments', fn () => app(FeaturePlaceholderController::class)('assignments'));
    Route::get('/alumni', [AlumniController::class, 'index']);
    Route::get('/alumni/stats', [AlumniController::class, 'stats']);
    Route::post('/alumni', [AlumniController::class, 'store']);
    Route::put('/alumni/{id}', [AlumniController::class, 'update']);
    Route::post('/alumni/{id}/pindah-unit', [AlumniController::class, 'pindahUnit']);
    Route::post('/alumni/{id}/pindah-keluar', [AlumniController::class, 'pindahKeluar']);
    Route::delete('/alumni/{id}', [AlumniController::class, 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // Foundation Notification Endpoints
    Route::get('/foundation/notifications', [NotificationController::class, 'index']);
    Route::get('/foundation/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/foundation/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/foundation/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // Logged-in User Profile Management Endpoints
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);

    // Foundation Profile Endpoints
    Route::get('/foundation/profile', [ProfileController::class, 'show']);
    Route::put('/foundation/profile', [ProfileController::class, 'update']);
    Route::post('/foundation/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::put('/foundation/profile/password', [ProfileController::class, 'changePassword']);

    // =========================================================================
    // SAFE REFACTOR — Routes Baru (tidak mengubah routes di atas)
    // =========================================================================

    // Gate Attendance (Absensi Kedatangan & Pulang Sekolah)
    Route::prefix('gate-attendance')->group(function () {
        Route::get('/logs', [GateAttendanceController::class, 'index'])->middleware('can:gate_attendance.view');
        Route::get('/stats', [GateAttendanceController::class, 'stats'])->middleware('can:gate_attendance.view');
        Route::get('/schedule-config', [GateAttendanceController::class, 'getScheduleConfig'])->middleware('can:gate_attendance.config');
        Route::get('/schedule-config/all', [GateAttendanceController::class, 'getAllScheduleConfigs'])->middleware('can:gate_attendance.config');
        Route::post('/schedule-config', [GateAttendanceController::class, 'saveScheduleConfig'])->middleware('can:gate_attendance.config');
        Route::post('/scan-in', [GateAttendanceController::class, 'scanCheckIn'])->middleware('can:gate_attendance.scan');
        Route::post('/scan-out', [GateAttendanceController::class, 'scanCheckOut'])->middleware('can:gate_attendance.scan');
    });

    // Santri Worship Attendance (Absensi Ibadah Santri)
    Route::prefix('worship-attendance')->group(function () {
        Route::get('/templates', [WorshipAttendanceController::class, 'templates']);
        Route::post('/templates', [WorshipAttendanceController::class, 'storeTemplate']);
        Route::get('/sessions', [WorshipAttendanceController::class, 'sessions']);
        Route::get('/sessions/{session}', [WorshipAttendanceController::class, 'showSession']);
        Route::post('/sessions/{session}/scan', [WorshipAttendanceController::class, 'scan']);
        Route::post('/sessions/{session}/verify', [WorshipAttendanceController::class, 'verifyStudent']);
        Route::post('/sessions/{session}/close', [WorshipAttendanceController::class, 'closeSession']);
    });

    // Direct Capaian Pembelajaran Dropdown
    Route::get('/capaian-pembelajaran/dropdown', [CapaianPembelajaranController::class, 'dropdown'])
        ->middleware('permission:academic.curriculum.view|master.view|sistem.master_data');

    // Master Divisi
    Route::get('/divisions/dropdown', [DivisionController::class, 'dropdown'])
        ->middleware('permission:master.view|sistem.master_data');
    Route::apiResource('divisions', DivisionController::class)->only(['index', 'show'])
        ->middleware('permission:master.view|sistem.master_data');
    Route::apiResource('divisions', DivisionController::class)->only(['store'])
        ->middleware('permission:master.create|sistem.master_data');
    Route::apiResource('divisions', DivisionController::class)->only(['update'])
        ->middleware('permission:master.update|sistem.master_data');
    Route::apiResource('divisions', DivisionController::class)->only(['destroy'])
        ->middleware('permission:master.delete|sistem.master_data');

    // Jadwal Pelajaran
    Route::get('/schedules-options', [ScheduleController::class, 'options'])
        ->middleware('permission:academic.schedule.view|sistem.master_data|pembelajaran.jadwal_pelajaran|teacher.schedule.view');
    Route::apiResource('schedules', ScheduleController::class)->only(['index', 'show'])
        ->middleware('permission:academic.schedule.view|sistem.master_data|pembelajaran.jadwal_pelajaran|teacher.schedule.view');
    Route::apiResource('schedules', ScheduleController::class)->only(['store'])
        ->middleware('permission:academic.schedule.create|academic.schedule.view|sistem.master_data|pembelajaran.jadwal_pelajaran');
    Route::apiResource('schedules', ScheduleController::class)->only(['update'])
        ->middleware('permission:academic.schedule.update|academic.schedule.view|sistem.master_data|pembelajaran.jadwal_pelajaran');
    Route::apiResource('schedules', ScheduleController::class)->only(['destroy'])
        ->middleware('permission:academic.schedule.delete|academic.schedule.view|sistem.master_data|pembelajaran.jadwal_pelajaran');

    // Nilai Siswa / Raport
    Route::get('/grades/rekap', [GradeController::class, 'rekap'])
        ->middleware('permission:academic.grade.view|teacher.grade.view');
    Route::apiResource('grades', GradeController::class)->only(['index', 'show'])
        ->middleware('permission:academic.grade.view|teacher.grade.view');
    Route::apiResource('grades', GradeController::class)->only(['store'])
        ->middleware('permission:academic.grade.create|teacher.grade.create');
    Route::apiResource('grades', GradeController::class)->only(['update'])
        ->middleware('permission:academic.grade.update|teacher.grade.update');

    // LMS Modul Ajar (RPP Digital)
    Route::prefix('lms')->group(function () {
        // Direct Dropdown Route for CP
        Route::get('/capaian-pembelajaran/dropdown', [CapaianPembelajaranController::class, 'dropdown']);
        Route::get('/capaian-pembelajaran/stats', [CapaianPembelajaranController::class, 'stats']);
        Route::post('/capaian-pembelajaran/{id}/restore', [CapaianPembelajaranController::class, 'restore']);
        Route::apiResource('capaian-pembelajaran', CapaianPembelajaranController::class);

        Route::get('/modul-ajar/stats', [LmsModulAjarController::class, 'stats']);
        Route::get('/modul-ajar/options', [LmsModulAjarController::class, 'options']);
        Route::get('/modul-ajar/export/excel', [LmsModulAjarController::class, 'exportExcel']);
        Route::get('/modul-ajar/{id}/export/pdf', [LmsModulAjarController::class, 'exportPdf']);
        Route::post('/modul-ajar/import', [LmsModulAjarController::class, 'import']);
        Route::post('/modul-ajar/{id}/restore', [LmsModulAjarController::class, 'restore']);
        Route::post('/modul-ajar/{id}/publish', [LmsModulAjarController::class, 'publish']);
        Route::post('/modul-ajar/{id}/duplicate', [LmsModulAjarController::class, 'duplicate']);
        Route::get('/modul-ajar/{id}/revisions', [LmsModulAjarController::class, 'revisions']);
        Route::apiResource('modul-ajar', LmsModulAjarController::class);

        // Tujuan Pembelajaran (TP)
        Route::get('/tujuan-pembelajaran/stats', [TujuanPembelajaranController::class, 'stats']);
        Route::get('/tujuan-pembelajaran/options', [TujuanPembelajaranController::class, 'options']);
        Route::post('/tujuan-pembelajaran/{id}/restore', [TujuanPembelajaranController::class, 'restore']);
        Route::apiResource('tujuan-pembelajaran', TujuanPembelajaranController::class);

        // Materi Pembelajaran (Materi)
        Route::get('/materi/stats', [LmsMateriController::class, 'stats']);
        Route::get('/materi/options', [LmsMateriController::class, 'options']);
        Route::post('/materi/{id}/restore', [LmsMateriController::class, 'restore']);
        Route::apiResource('materi', LmsMateriController::class);

        // Media Pembelajaran (Media)
        Route::get('/media/stats', [LmsMediaController::class, 'stats']);
        Route::get('/media/options', [LmsMediaController::class, 'options']);
        Route::post('/media/reorder', [LmsMediaController::class, 'reorder']);
        Route::apiResource('media', LmsMediaController::class);

        // Referensi Pembelajaran (Referensi)
        Route::get('/referensi/stats', [LmsReferensiController::class, 'stats']);
        Route::get('/referensi/options', [LmsReferensiController::class, 'options']);
        Route::post('/referensi/{id}/restore', [LmsReferensiController::class, 'restore']);
        Route::apiResource('referensi', LmsReferensiController::class);

        // Aktivitas Belajar (Aktivitas)
        Route::get('/aktivitas/stats', [LmsAktivitasBelajarController::class, 'stats']);
        Route::get('/aktivitas/options', [LmsAktivitasBelajarController::class, 'options']);
        Route::post('/aktivitas/{id}/restore', [LmsAktivitasBelajarController::class, 'restore']);
        Route::apiResource('aktivitas', LmsAktivitasBelajarController::class);

        // Diskusi Kelas (Diskusi)
        Route::get('/diskusi/stats', [LmsDiskusiController::class, 'stats']);
        Route::get('/diskusi/options', [LmsDiskusiController::class, 'options']);
        Route::post('/diskusi/{id}/restore', [LmsDiskusiController::class, 'restore']);
        Route::post('/diskusi/{id}/toggle-pin', [LmsDiskusiController::class, 'togglePin']);
        Route::post('/diskusi/{id}/toggle-close', [LmsDiskusiController::class, 'toggleClose']);
        Route::post('/diskusi/{id}/komentar', [LmsDiskusiController::class, 'storeKomentar']);
        Route::delete('/diskusi/{diskusiId}/komentar/{komentarId}', [LmsDiskusiController::class, 'destroyKomentar']);
        Route::apiResource('diskusi', LmsDiskusiController::class);

        // Penugasan (Assignments)
        Route::get('/penugasan/stats', [LmsPenugasanController::class, 'stats']);
        Route::get('/penugasan/options', [LmsPenugasanController::class, 'options']);
        Route::post('/penugasan/{id}/restore', [LmsPenugasanController::class, 'restore']);
        Route::post('/penugasan/{id}/toggle-publish', [LmsPenugasanController::class, 'togglePublish']);
        Route::post('/penugasan/{id}/nilai', [LmsPenugasanController::class, 'gradeSubmission']);
        Route::apiResource('penugasan', LmsPenugasanController::class);

        // Pengumpulan Tugas (Assignment Submissions)
        Route::get('/pengumpulan-tugas/stats', [LmsPengumpulanTugasController::class, 'stats']);
        Route::get('/pengumpulan-tugas/options', [LmsPengumpulanTugasController::class, 'options']);
        Route::post('/pengumpulan-tugas/{id}/restore', [LmsPengumpulanTugasController::class, 'restore']);
        Route::apiResource('pengumpulan-tugas', LmsPengumpulanTugasController::class);

        // Presensi Pembelajaran (Learning Attendance)
        Route::get('/presensi/stats', [LmsPresensiController::class, 'stats']);
        Route::get('/presensi/options', [LmsPresensiController::class, 'options']);
        Route::post('/presensi/bulk', [LmsPresensiController::class, 'bulkStore']);
        Route::post('/presensi/{id}/restore', [LmsPresensiController::class, 'restore']);
        Route::apiResource('presensi', LmsPresensiController::class);

        // Kisi-kisi Ujian (Exam Blueprint)
        Route::get('/kisi-kisi/stats', [LmsKisiKisiController::class, 'stats']);
        Route::get('/kisi-kisi/options', [LmsKisiKisiController::class, 'options']);
        Route::post('/kisi-kisi/{id}/restore', [LmsKisiKisiController::class, 'restore']);
        Route::post('/kisi-kisi/{id}/duplicate', [LmsKisiKisiController::class, 'duplicate']);
        Route::apiResource('kisi-kisi', LmsKisiKisiController::class);

        // Bank Soal (Question Bank)
        Route::get('/bank-soal/stats', [LmsBankSoalController::class, 'stats']);
        Route::get('/bank-soal/options', [LmsBankSoalController::class, 'options']);
        Route::post('/bank-soal/{id}/restore', [LmsBankSoalController::class, 'restore']);
        Route::post('/bank-soal/{id}/duplicate', [LmsBankSoalController::class, 'duplicate']);
        Route::apiResource('bank-soal', LmsBankSoalController::class);

        // CBT Ujian Online Engine
        Route::get('/ujian/stats', [LmsUjianController::class, 'stats']);
        Route::get('/ujian/options', [LmsUjianController::class, 'options']);
        Route::post('/ujian/{id}/restore', [LmsUjianController::class, 'restore']);
        Route::post('/ujian/{id}/duplicate', [LmsUjianController::class, 'duplicate']);
        Route::post('/ujian/{id}/toggle-publish', [LmsUjianController::class, 'togglePublish']);
        Route::post('/ujian/{id}/start-session', [LmsUjianController::class, 'startSession']);
        Route::get('/ujian/{id}/results', [LmsUjianController::class, 'results']);
        Route::post('/ujian/sesi/{sesiId}/submit-answers', [LmsUjianController::class, 'submitAnswers']);
        Route::post('/ujian/sesi/{sesiId}/finish-session', [LmsUjianController::class, 'finishSession']);
        Route::post('/ujian/jawaban/{jawabanId}/grade-essay', [LmsUjianController::class, 'gradeEssay']);
        Route::apiResource('ujian', LmsUjianController::class);

        // Penilaian & Rekap Rapor (Configurable Weight & Formula Engine)
        Route::get('/penilaian/stats', [LmsPenilaianController::class, 'stats']);
        Route::get('/penilaian/options', [LmsPenilaianController::class, 'options']);
        Route::post('/penilaian/calculate-auto', [LmsPenilaianController::class, 'calculateAuto']);
        Route::post('/penilaian/{id}/restore', [LmsPenilaianController::class, 'restore']);
        Route::apiResource('penilaian', LmsPenilaianController::class);

        // Rapor Digital & Cetak PDF
        Route::get('/rapor/stats', [LmsRaporController::class, 'stats']);
        Route::get('/rapor/options', [LmsRaporController::class, 'options']);
        Route::post('/rapor/generate-class', [LmsRaporController::class, 'generateClass']);
        Route::get('/rapor/{id}/pdf', [LmsRaporController::class, 'exportPdf']);
        Route::post('/rapor/{id}/restore', [LmsRaporController::class, 'restore']);
        Route::post('/rapor/{id}/approve', [LmsRaporController::class, 'approve']);
        Route::post('/rapor/{id}/publish', [LmsRaporController::class, 'publish']);
        Route::apiResource('rapor', LmsRaporController::class);
    });

    // Teacher Portal Routes (/api/teacher/*)
    Route::prefix('teacher')->middleware('role:Guru|guru|Guru Mata Pelajaran|guru_mata_pelajaran|Guru PAI|Pembimbing|Wali Kelas|walas|wali_kelas|Guru Tahfizh|guru_tahfizh|Musyrif|musyrif|Musyrifah|Musyrif / Musyrifah|Guru BK|guru_bk|Super Admin|super_admin|super-admin|Superadmin|Kepala Sekolah|kepala_sekolah|KepalaSekolah|kepsek|Divisi Pendidikan|divisi_pendidikan|Kepala Bidang Pendidikan|Yayasan|Ketua Yayasan|ketua_yayasan|sekretaris_yayasan|bendahara_yayasan|pengurus_yayasan|Pengurus Yayasan|Pengurus')->group(function () {
             Route::get('/step04/schedules', [Step04TeacherController::class, 'schedules'])
                 ->middleware('permission:teacher.schedule.view');
             Route::post('/teaching-attendance/scan', [Step04TeacherController::class, 'scan'])
                 ->middleware('permission:teaching_attendance.scan');
             Route::post('/teaching-sessions/{session}/start', [Step04TeacherController::class, 'startSession'])
                 ->middleware('permission:teaching_session.start');
             Route::post('/teaching-sessions/{session}/close', [Step04TeacherController::class, 'closeSession'])
                 ->middleware('permission:teaching_session.close');
             Route::post('/presence/heartbeat', [Step04TeacherController::class, 'heartbeat'])
                 ->middleware('permission:teacher_presence.heartbeat');
             Route::get('/dashboard', [TeacherPortalController::class, 'dashboard'])
                 ->middleware('permission:teacher.dashboard.view');
            Route::get('/schedules', [TeacherPortalController::class, 'schedules'])
                ->middleware('permission:teacher.schedule.view');
            Route::get('/classes', [TeacherPortalController::class, 'classes'])
                ->middleware('permission:teacher.schedule.view|teacher.attendance.view|teacher.tahfizh.view|teacher.mutabaah.view|teacher.student_note.view');
            Route::get('/students', [TeacherPortalController::class, 'students'])
                ->middleware('permission:teacher.schedule.view|teacher.attendance.view|teacher.tahfizh.view|teacher.mutabaah.view|teacher.student_note.view');
            Route::get('/attendance', [TeacherPortalController::class, 'attendance'])
                ->middleware('permission:teacher.attendance.view');
            Route::post('/attendance', [TeacherPortalController::class, 'saveAttendance'])
                ->middleware('permission:teacher.attendance.create');
            Route::get('/materials', [TeacherPortalController::class, 'materials'])
                ->middleware('permission:teacher.material.view');
            Route::post('/materials', [TeacherPortalController::class, 'saveMaterial'])
                ->middleware('permission:teacher.material.create');
            Route::put('/materials/{id}', [TeacherPortalController::class, 'updateMaterial'])
                ->middleware('permission:teacher.material.update');
            Route::delete('/materials/{id}', [TeacherPortalController::class, 'deleteMaterial'])
                ->middleware('permission:teacher.material.delete');
            Route::get('/assignments', [TeacherPortalController::class, 'assignments'])
                ->middleware('permission:teacher.assignment.view');
            Route::post('/assignments', [TeacherPortalController::class, 'saveAssignment'])
                ->middleware('permission:teacher.assignment.create');
            Route::get('/submissions', [TeacherPortalController::class, 'submissions'])
                ->middleware('permission:teacher.submission.view');
            Route::post('/submissions/{id}/grade', [TeacherPortalController::class, 'gradeSubmission'])
                ->middleware('permission:teacher.grade.create|teacher.grade.update');
            Route::get('/grades', [TeacherPortalController::class, 'grades'])
                ->middleware('permission:teacher.grade.view');
            Route::post('/grades', [TeacherPortalController::class, 'saveGrades'])
                ->middleware('permission:teacher.grade.create|teacher.grade.update');
            Route::get('/tahfizh', [TeacherPortalController::class, 'tahfizh'])
                ->middleware('permission:teacher.tahfizh.view');
            Route::post('/tahfizh', [TeacherPortalController::class, 'saveTahfizh'])
                ->middleware('permission:teacher.tahfizh.create|teacher.tahfizh.update');
            Route::get('/mutabaah', [TeacherPortalController::class, 'mutabaah'])
                ->middleware('permission:teacher.mutabaah.view');
            Route::post('/mutabaah/{id}/verify', [TeacherPortalController::class, 'verifyMutabaah'])
                ->middleware('permission:teacher.mutabaah.update');
            Route::get('/student-notes', [TeacherPortalController::class, 'studentNotes'])
                ->middleware('permission:teacher.student_note.view');
            Route::post('/student-notes', [TeacherPortalController::class, 'saveStudentNote'])
                ->middleware('permission:teacher.student_note.create');
            Route::get('/student-notes/{id}', [TeacherPortalController::class, 'showStudentNote'])
                ->middleware('permission:teacher.student_note.view');
            Route::put('/student-notes/{id}', [TeacherPortalController::class, 'updateStudentNote'])
                ->middleware('permission:teacher.student_note.update');
            Route::delete('/student-notes/{id}', [TeacherPortalController::class, 'deleteStudentNote'])
                ->middleware('permission:teacher.student_note.update');
            Route::get('/notifications', [TeacherPortalController::class, 'notifications'])
                ->middleware('permission:teacher.dashboard.view');
            Route::get('/profile', [TeacherPortalController::class, 'profile'])
                ->middleware('permission:teacher.dashboard.view');
            Route::post('/profile', [TeacherPortalController::class, 'updateProfile'])
                ->middleware('permission:teacher.dashboard.view');

            // Teacher Chat Routes
            Route::get('/chat/conversations', [TeacherPortalController::class, 'chatConversations'])
                ->middleware('permission:chat.conversation.view');
            Route::get('/chat/parent/{parentUserId}/student/{studentId}', [TeacherPortalController::class, 'chatMessages'])
                ->middleware('permission:chat.message.view');
            Route::post('/chat/parent/{parentUserId}/student/{studentId}', [TeacherPortalController::class, 'sendChatMessage'])
                ->middleware('permission:chat.message.send');
        });

        // Parent & Student Portal Routes (/api/portal/*)
        Route::prefix('portal')->middleware('role:Orang Tua|orang_tua|orang-tua|Orangtua|Wali Murid|parent|Siswa|siswa|student')->group(function () {
            Route::get('/dashboard', [StudentParentPortalController::class, 'dashboard']);
            Route::get('/children', [StudentParentPortalController::class, 'children']);
            Route::put('/children/{childId}/password', [StudentParentPortalController::class, 'updateChildPassword'])->middleware('role:Orang Tua|orang_tua|orang-tua|Orangtua|Wali Murid|parent');
             Route::get('/profile', [StudentParentPortalController::class, 'profile']);
             Route::get('/attendance-qr', [StudentParentPortalController::class, 'attendanceQr']);
             Route::get('/schedules', [StudentParentPortalController::class, 'schedules']);
            Route::get('/attendance', [StudentParentPortalController::class, 'attendance']);
            Route::post('/permissions', [StudentParentPortalController::class, 'submitPermission'])->middleware('role:Orang Tua|orang_tua|orang-tua|Orangtua|Wali Murid|parent');
            Route::get('/permissions', [StudentParentPortalController::class, 'permissionsHistory']);
            Route::get('/materials', [StudentParentPortalController::class, 'materials']);
            Route::get('/assignments', [StudentParentPortalController::class, 'assignments']);
            Route::post('/assignments/{id}/submit', [StudentParentPortalController::class, 'submitAssignment'])->middleware('role:Siswa|siswa|student');
            Route::get('/grades', [StudentParentPortalController::class, 'grades']);
            Route::get('/tahfizh', [StudentParentPortalController::class, 'tahfizh']);
            Route::get('/mutabaah', [StudentParentPortalController::class, 'mutabaah']);
            Route::post('/mutabaah', [StudentParentPortalController::class, 'saveMutabaahStudent'])->middleware('role:Orang Tua|orang_tua|orang-tua|Orangtua|Wali Murid|parent');
            Route::get('/student-notes', [StudentParentPortalController::class, 'studentNotes']);
            Route::post('/student-notes/{id}/sign', [StudentParentPortalController::class, 'signStudentNote'])->middleware('role:Orang Tua|orang_tua|orang-tua|Orangtua|Wali Murid|parent');
            Route::get('/achievements', [StudentParentPortalController::class, 'achievements']);
            Route::get('/announcements', [StudentParentPortalController::class, 'announcements']);
            Route::get('/school-information', [StudentParentPortalController::class, 'schoolInformation']);
            Route::get('/school-information/summary', [StudentParentPortalController::class, 'schoolInformationSummary']);
            Route::patch('/school-information/read-all', [StudentParentPortalController::class, 'markAllSchoolInformationRead']);
            Route::patch('/school-information/{informationId}/state', [StudentParentPortalController::class, 'updateSchoolInformationState']);
            Route::get('/notifications', [StudentParentPortalController::class, 'notifications']);
            Route::get('/bills', [StudentParentPortalController::class, 'bills']);
            Route::get('/reports', [StudentParentPortalController::class, 'reports']);
            Route::get('/reports/{id}/download', [StudentParentPortalController::class, 'downloadReport']);

            // Parent Chat Routes
            Route::get('/chat/contacts', [StudentParentPortalController::class, 'chatContacts']);
            Route::get('/chat/available-teachers', [StudentParentPortalController::class, 'chatContacts']);
            Route::get('/chat/{teacherUserId}', [StudentParentPortalController::class, 'chatMessages']);
            Route::post('/chat/{teacherUserId}', [StudentParentPortalController::class, 'sendChatMessage']);

            Route::get('/lms/exams', [StudentParentPortalController::class, 'examOverview']);
            Route::get('/exam-grids', [StudentParentPortalController::class, 'examGrids']);
            Route::get('/results', [StudentParentPortalController::class, 'results']);
            Route::post('/lms/exams/{id}/start', [StudentParentPortalController::class, 'startExam'])->middleware('role:Siswa|siswa|student');
            Route::post('/lms/exam-sessions/{sesiId}/answers', [StudentParentPortalController::class, 'saveExamAnswers'])->middleware('role:Siswa|siswa|student');
        });

        // Alumni Portal Routes (/api/portal/alumni/*)
        Route::prefix('portal/alumni')->middleware('role:Alumni|Super Admin|super_admin')->group(function () {
            Route::get('/dashboard', [AlumniPortalController::class, 'dashboard']);
            Route::put('/profile', [AlumniPortalController::class, 'updateProfile']);
        });

        // Unified Chat Alias Routes (/api/chat/*)
        // Role-scoped: portal (Orang Tua/Siswa) + seluruh role staf sekolah.
        Route::prefix('chat')->middleware('role:Orang Tua|Siswa|Guru|Guru Mata Pelajaran|Guru PAI|Pembimbing|Wali Kelas|Guru Tahfizh|Musyrif|Musyrifah|Musyrif / Musyrifah|Guru BK|Kepala Sekolah|Tata Usaha|TU|Operator|Divisi Pendidikan|Waka Kurikulum|Waka Kesiswaan|Wakil Kepala Sekolah|Admin|Super Admin|Pengurus Yayasan|Ketua Yayasan|Sekretaris Yayasan|Bendahara Yayasan')->group(function () {
            Route::get('/contacts', [StudentParentPortalController::class, 'chatContacts']);
            Route::get('/available-teachers', [StudentParentPortalController::class, 'chatContacts']);
            Route::get('/conversations', [TeacherPortalController::class, 'chatConversations']);
            Route::get('/messages/{teacherUserId}', [StudentParentPortalController::class, 'chatMessages']);
            Route::post('/messages/{teacherUserId}', [StudentParentPortalController::class, 'sendChatMessage']);

            // Employee Chat Alias Routes
            Route::get('/employee/contacts', [EmployeeChatController::class, 'employeeContacts']);
            Route::get('/employee/conversations', [EmployeeChatController::class, 'employeeConversations']);
            Route::get('/employee/messages/{recipientUserId}', [EmployeeChatController::class, 'employeeMessages']);
            Route::post('/employee/messages/{recipientUserId}', [EmployeeChatController::class, 'sendEmployeeMessage']);
        });

        // Dedicated Musyrif & Boarding Module Routes (/api/v1/musyrif/*)
        Route::prefix('musyrif')->middleware('role:Musyrif|musyrif|Musyrifah|Musyrif / Musyrifah|Pengasuh|Wali Asrama|Pembimbing|Super Admin|Admin|super_admin')->group(function () {
            // Master Santri, Presensi Sholat 5 Waktu & Setoran Hafalan Harian
            Route::get('/students', [MusyrifModuleController::class, 'students']);
            Route::post('/worship-attendance', [MusyrifModuleController::class, 'storeWorshipAttendance']);
            Route::get('/tahfizh/last-log', [MusyrifModuleController::class, 'lastLog']);
            Route::post('/tahfizh', [MusyrifModuleController::class, 'storeSetoranTahfizh']);

            // Ujian Tasmi' / Tahfizh Sekali Duduk
            Route::get('/exams', [MusyrifModuleController::class, 'indexExams']);
            Route::post('/exams', [MusyrifModuleController::class, 'storeExam']);

            // Bank Poin Kedisiplinan & Ledger
            Route::get('/discipline-categories', [MusyrifModuleController::class, 'indexDisciplineCategories']);
            Route::get('/point-transactions', [MusyrifModuleController::class, 'indexPointTransactions']);
            Route::post('/point-transactions', [MusyrifModuleController::class, 'storePointTransaction']);

            // Klinik & Log Kesehatan Santri
            Route::get('/clinic-logs', [MusyrifModuleController::class, 'indexClinicLogs']);
            Route::post('/clinic-logs', [MusyrifModuleController::class, 'storeClinicLog']);

            // Penitipan Barang Berharga Asrama (HP/Laptop)
            Route::get('/deposits', [MusyrifModuleController::class, 'indexDeposits']);
            Route::post('/deposits', [MusyrifModuleController::class, 'storeDeposit']);
            Route::patch('/deposits/{id}/retrieve', [MusyrifModuleController::class, 'retrieveDeposit']);
        });
    });
