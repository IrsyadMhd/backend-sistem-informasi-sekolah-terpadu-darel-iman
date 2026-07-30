<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AttendanceWorkflowController;
use App\Http\Controllers\Api\V1\AttendanceCaptureController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\DashboardPemantauanController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DivisionController;
use App\Http\Controllers\Api\V1\EducationUnitController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\FeaturePlaceholderController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\HakAksesController;
use App\Http\Controllers\Api\V1\JabatanController;
use App\Http\Controllers\Api\V1\JenisUnitPendidikanController;
use App\Http\Controllers\Api\V1\KelasController;
use App\Http\Controllers\Api\V1\LmsAktivitasBelajarController;
use App\Http\Controllers\Api\V1\LmsDiskusiController;
use App\Http\Controllers\Api\LmsKisiKisiController;
use App\Http\Controllers\Api\LmsBankSoalController;
use App\Http\Controllers\Api\LmsUjianController;
use App\Http\Controllers\Api\LmsPenilaianController;
use App\Http\Controllers\Api\V1\LmsMateriController;
use App\Http\Controllers\Api\V1\LmsMediaController;
use App\Http\Controllers\Api\V1\LmsModulAjarController;
use App\Http\Controllers\Api\V1\LmsPenugasanController;
use App\Http\Controllers\Api\V1\LmsPengumpulanTugasController;
use App\Http\Controllers\Api\V1\LmsPresensiController;
use App\Http\Controllers\Api\V1\LmsReferensiController;
use App\Http\Controllers\Api\V1\MasterKurikulumController;
use App\Http\Controllers\Api\V1\ModulSemesterController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TahfizhController;
use App\Http\Controllers\Api\V1\TahunAjaranController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\TujuanPembelajaranController;
use App\Http\Controllers\Api\V1\UserAccountController;
use App\Http\Controllers\Api\V1\CapaianPembelajaranController;
use App\Http\Controllers\Api\V1\LmsRaporController;
use App\Http\Controllers\Api\V1\SiteSettingController;
use App\Http\Controllers\Api\V1\MutabaahController;
use Illuminate\Support\Facades\Route;

Route::get('/site-settings', [SiteSettingController::class, 'show']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('mutabaah')->group(function () {
        Route::get('/options', [MutabaahController::class, 'options']);
        Route::get('/agendas', [MutabaahController::class, 'agendas']);
        Route::post('/agendas', [MutabaahController::class, 'storeAgenda']);
        Route::put('/agendas/{agenda}', [MutabaahController::class, 'updateAgenda']);
        Route::delete('/agendas/{agenda}', [MutabaahController::class, 'destroyAgenda']);
        Route::get('/daily', [MutabaahController::class, 'daily']);
        Route::post('/daily', [MutabaahController::class, 'saveDaily']);
        Route::get('/history', [MutabaahController::class, 'history']);
    });
    Route::post('/site-settings', [SiteSettingController::class, 'update'])
        ->middleware('can:sistem.pengaturan');
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
        Route::post('/subjects/bulk-status', [SubjectController::class, 'bulkStatus']);
        Route::post('/subjects/bulk-delete', [SubjectController::class, 'bulkDelete']);
        Route::get('/subjects/export/excel', [SubjectController::class, 'exportExcel']);
        Route::get('/subjects/export/pdf', [SubjectController::class, 'exportPdf']);
        Route::post('/subjects/import', [SubjectController::class, 'import']);
        Route::post('/subjects/{id}/restore', [SubjectController::class, 'restore']);
        Route::apiResource('subjects', SubjectController::class);

        // Tahun Ajaran (Academic Year)
        Route::get('/tahun-ajaran/dropdown', [TahunAjaranController::class, 'dropdown']);
        Route::get('/tahun-ajaran/stats', [TahunAjaranController::class, 'stats']);
        Route::get('/tahun-ajaran/export', [TahunAjaranController::class, 'export']);
        Route::post('/tahun-ajaran/import', [TahunAjaranController::class, 'import']);
        Route::post('/tahun-ajaran/{id}/set-aktif', [TahunAjaranController::class, 'setAktif']);
        Route::post('/tahun-ajaran/{id}/restore', [TahunAjaranController::class, 'restore']);
        Route::apiResource('tahun-ajaran', TahunAjaranController::class);

        // Master Modul Semester
        Route::get('/modul-semester/options', [ModulSemesterController::class, 'options']);
        Route::get('/modul-semester/stats', [ModulSemesterController::class, 'stats']);
        Route::post('/modul-semester/{id}/restore', [ModulSemesterController::class, 'restore']);
        Route::post('/modul-semester/{id}/duplicate', [ModulSemesterController::class, 'duplicate']);
        Route::post('/modul-semester/{id}/toggle-status', [ModulSemesterController::class, 'toggleStatus']);
        Route::apiResource('modul-semester', ModulSemesterController::class);

        // Master Kurikulum
        Route::get('/kurikulum/dropdown', [MasterKurikulumController::class, 'dropdown']);
        Route::get('/kurikulum/stats', [MasterKurikulumController::class, 'stats']);
        Route::get('/kurikulum/export', [MasterKurikulumController::class, 'export']);
        Route::post('/kurikulum/import', [MasterKurikulumController::class, 'import']);
        Route::post('/kurikulum/{id}/restore', [MasterKurikulumController::class, 'restore']);
        Route::apiResource('kurikulum', MasterKurikulumController::class);

        // Capaian Pembelajaran (CP)
        Route::get('/capaian-pembelajaran/dropdown', [CapaianPembelajaranController::class, 'dropdown']);
        Route::get('/capaian-pembelajaran/stats', [CapaianPembelajaranController::class, 'stats']);
        Route::post('/capaian-pembelajaran/{id}/restore', [CapaianPembelajaranController::class, 'restore']);
        Route::apiResource('capaian-pembelajaran', CapaianPembelajaranController::class);
    });

    // Rute Master Hak Akses (Role & Permission — Spatie)
    Route::prefix('hak-akses')->middleware('can:sistem.hak_akses')->group(function () {
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
        Route::post('/sessions/{session}/scan/{method}', [AttendanceCaptureController::class, 'scan'])->whereIn('method', ['qr','barcode','face']);
        Route::get('/sessions/{session}/scan-logs', [AttendanceCaptureController::class, 'logs']);
        Route::get('/students/{student}/qr-token', [AttendanceCaptureController::class, 'studentToken']);
    });
    Route::get('/student-attendance/me', [AttendanceWorkflowController::class, 'myAttendance']);
    Route::match(['get','post'], '/student-attendance/permissions', [AttendanceWorkflowController::class, 'permissions']);
    Route::put('/student-attendance/permissions/{permission}', [AttendanceWorkflowController::class, 'updatePermission']);
    Route::post('/student-attendance/permissions/{permission}/submit', [AttendanceWorkflowController::class, 'submitPermission']);
    Route::post('/student-attendance/permissions/{permission}/cancel', [AttendanceWorkflowController::class, 'cancelPermission']);
    Route::get('/homeroom-attendance/permissions', [AttendanceWorkflowController::class, 'homeroomPermissions']);
    Route::post('/homeroom-attendance/permissions/{permission}/review', [AttendanceWorkflowController::class, 'reviewPermission']);
    Route::get('/homeroom-attendance/dashboard', [AttendanceWorkflowController::class, 'homeroomDashboard']);
    Route::match(['get','post'], '/homeroom-attendance/follow-ups', [AttendanceWorkflowController::class, 'followUps']);
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
    Route::post('/attendance-permissions/{permission}/{action}', [AttendanceWorkflowController::class, 'permissionReviewAction'])->whereIn('action', ['approve','reject','revision']);
    Route::post('/attendance-permissions/{permission}/cancel', [AttendanceWorkflowController::class, 'cancelPermission']);

    Route::get('/attendance-corrections', [AttendanceWorkflowController::class, 'corrections']);
    Route::post('/attendance-corrections', [AttendanceWorkflowController::class, 'correction']);
    Route::get('/attendance-corrections/{correction}', [AttendanceWorkflowController::class, 'showCorrection']);
    Route::post('/attendance-corrections/{correction}/{action}', [AttendanceWorkflowController::class, 'correctionReviewAction'])->whereIn('action', ['approve','reject']);
    Route::post('/attendance-corrections/{correction}/cancel', [AttendanceWorkflowController::class, 'cancelCorrection']);

    Route::get('/attendance-follow-ups', [AttendanceWorkflowController::class, 'followUps']);
    Route::post('/attendance-follow-ups', [AttendanceWorkflowController::class, 'followUps']);
    Route::get('/attendance-follow-ups/{followUp}', [AttendanceWorkflowController::class, 'showFollowUp']);
    Route::put('/attendance-follow-ups/{followUp}', [AttendanceWorkflowController::class, 'updateFollowUp']);
    Route::post('/attendance-follow-ups/{followUp}/complete', [AttendanceWorkflowController::class, 'completeFollowUp']);
    Route::post('/attendance-follow-ups/{followUp}/close', [AttendanceWorkflowController::class, 'closeFollowUp']);
    Route::get('/attendance/reports/summary', [AttendanceWorkflowController::class, 'report']);
    Route::get('/attendance/reports/export', [AttendanceWorkflowController::class, 'report']);

    Route::post('/tahfizh/store', [TahfizhController::class, 'inputSetoran']);
    Route::get('/tahfizh/report', [TahfizhController::class, 'rekapTahfizh']);

    Route::get('/mutabaah', fn () => app(FeaturePlaceholderController::class)('mutabaah'));
    Route::get('/materials', fn () => app(FeaturePlaceholderController::class)('materials'));
    Route::get('/assignments', fn () => app(FeaturePlaceholderController::class)('assignments'));
    Route::get('/exams', fn () => app(FeaturePlaceholderController::class)('exams'));
    Route::get('/alumni', fn () => app(FeaturePlaceholderController::class)('alumni'));
    Route::get('/notifications', fn () => app(FeaturePlaceholderController::class)('notifications'));

    // =========================================================================
    // SAFE REFACTOR — Routes Baru (tidak mengubah routes di atas)
    // =========================================================================

    // Direct Capaian Pembelajaran Dropdown
    Route::get('/capaian-pembelajaran/dropdown', [CapaianPembelajaranController::class, 'dropdown']);

    // Master Divisi
    Route::get('/divisions/dropdown', [DivisionController::class, 'dropdown']);
    Route::apiResource('divisions', DivisionController::class)->except(['create', 'edit']);

    // Jadwal Pelajaran
    Route::get('/schedules-options', [ScheduleController::class, 'options']);
    Route::apiResource('schedules', ScheduleController::class)->except(['create', 'edit']);

    // Nilai Siswa / Raport
    Route::get('/grades/rekap', [GradeController::class, 'rekap']);
    Route::apiResource('grades', GradeController::class)->except(['create', 'edit', 'destroy']);

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
        Route::apiResource('rapor', LmsRaporController::class);
    });
});
