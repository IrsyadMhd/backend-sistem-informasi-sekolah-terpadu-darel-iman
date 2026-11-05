<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\AllMasterDataExport;
use App\Exports\StudentExport;
use App\Http\Controllers\Controller;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Position;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemMaintenanceController extends Controller
{
    /**
     * Helper aman untuk menghitung baris tabel tanpa throw exception jika tabel/kolom tidak ada.
     */
    private function safeCount(string $table, ?\Closure $queryCallback = null): int
    {
        try {
            if (!Schema::hasTable($table)) {
                return 0;
            }
            $query = DB::table($table);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            if ($queryCallback) {
                $queryCallback($query);
            }
            return (int) $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Dapatkan status dan ringkasan metrik database serta status cache server.
     */
    public function status(): JsonResponse
    {
        try {
            $metrics = [
                'students' => [
                    'total' => $this->safeCount('students'),
                    'active' => $this->safeCount('students', function ($q) {
                        if (Schema::hasColumn('students', 'is_active')) {
                            $q->where('is_active', true);
                        } elseif (Schema::hasColumn('students', 'status')) {
                            $q->whereIn('status', ['Aktif', 'aktif']);
                        }
                    }),
                    'male' => $this->safeCount('students', function ($q) {
                        if (Schema::hasColumn('students', 'gender')) {
                            $q->whereIn('gender', ['male', 'L', 'Laki-laki']);
                        }
                    }),
                    'female' => $this->safeCount('students', function ($q) {
                        if (Schema::hasColumn('students', 'gender')) {
                            $q->whereIn('gender', ['female', 'P', 'Perempuan']);
                        }
                    }),
                ],
                'employees' => [
                    'total' => $this->safeCount('employees'),
                    'active' => $this->safeCount('employees', function ($q) {
                        if (Schema::hasColumn('employees', 'status')) {
                            $q->whereIn('status', ['Aktif', 'aktif']);
                        } elseif (Schema::hasColumn('employees', 'is_active')) {
                            $q->where('is_active', true);
                        }
                    }),
                ],
                'positions' => [
                    'total' => $this->safeCount('positions'),
                    'active' => $this->safeCount('positions', function ($q) {
                        if (Schema::hasColumn('positions', 'is_active')) {
                            $q->where('is_active', true);
                        } elseif (Schema::hasColumn('positions', 'status')) {
                            $q->whereIn('status', ['Aktif', 'aktif']);
                        }
                    }),
                ],
                'education_units' => [
                    'total' => $this->safeCount('education_units'),
                    'active' => $this->safeCount('education_units', function ($q) {
                        if (Schema::hasColumn('education_units', 'is_active')) {
                            $q->where('is_active', true);
                        } elseif (Schema::hasColumn('education_units', 'status')) {
                            $q->whereIn('status', ['Aktif', 'aktif']);
                        }
                    }),
                ],
                'classes' => [
                    'total' => $this->safeCount('tbl_kelas'),
                    'active' => $this->safeCount('tbl_kelas', function ($q) {
                        if (Schema::hasColumn('tbl_kelas', 'status')) {
                            $q->whereIn('status', ['Aktif', 'aktif']);
                        } elseif (Schema::hasColumn('tbl_kelas', 'is_active')) {
                            $q->where('is_active', true);
                        }
                    }),
                ],
                'subjects' => [
                    'total' => $this->safeCount('subjects'),
                    'active' => $this->safeCount('subjects', function ($q) {
                        if (Schema::hasColumn('subjects', 'status')) {
                            $q->where('status', true);
                        } elseif (Schema::hasColumn('subjects', 'is_active')) {
                            $q->where('is_active', true);
                        }
                    }),
                ],
                'parents' => [
                    'total' => $this->safeCount('parents'),
                ],
                'operational' => [
                    'attendances' => $this->safeCount('attendances'),
                    'gate_attendances' => $this->safeCount('gate_attendances'),
                    'grades' => $this->safeCount('grades'),
                    'mutabaah' => $this->safeCount('mutabaah_daily_records') ?: $this->safeCount('mutabaah_daily_headers'),
                    'tahfizh' => $this->safeCount('tahfizh_records'),
                ],
            ];

            $server = [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'database_driver' => DB::getDriverName(),
                'database_name' => DB::getDatabaseName(),
                'cache_driver' => config('cache.default'),
                'config_cached' => app()->configurationIsCached(),
                'routes_cached' => app()->routesAreCached(),
                'events_cached' => app()->eventsAreCached(),
                'environment' => app()->environment(),
                'server_time' => now()->toIso8601String(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Status pemeliharaan sistem berhasil dimuat.',
                'data' => [
                    'metrics' => $metrics,
                    'server' => $server,
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('SystemMaintenance status failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat status sistem: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Jalankan optimize:clear dan cache:clear di backend Laravel.
     */
    public function optimizeClear(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $logs = [];

        try {
            Artisan::call('optimize:clear');
            $logs[] = trim(Artisan::output());

            Artisan::call('cache:clear');
            $logs[] = trim(Artisan::output());

            // Clear temporary import files if any
            $tmpDir = storage_path('app/imports');
            if (is_dir($tmpDir)) {
                $files = glob($tmpDir . '/*');
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
                $logs[] = 'Temporary import storage directory cleared.';
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'success' => true,
                'message' => 'Pembersihan cache dan optimasi sistem berhasil dijalankan.',
                'data' => [
                    'duration_ms' => $duration,
                    'logs' => array_filter($logs),
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membersihkan cache sistem: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus / Reset data database (Zona Bahaya).
     * Memerlukan konfirmasi teks "RESET DATABASE".
     */
    public function resetDatabase(Request $request): JsonResponse
    {
        $user = $request->user();
        $isSuperAdmin = false;
        if ($user) {
            if (method_exists($user, 'hasAnyRole')) {
                try {
                    $isSuperAdmin = $user->hasAnyRole(['Super Admin', 'super_admin', 'superadmin', 'Admin', 'admin']);
                } catch (\Throwable) {
                    $isSuperAdmin = false;
                }
            }
            if (! $isSuperAdmin && method_exists($user, 'can')) {
                try {
                    $isSuperAdmin = $user->can('sistem.pengaturan');
                } catch (\Throwable) {
                    $isSuperAdmin = false;
                }
            }
            if (! $isSuperAdmin) {
                $userEmail = strtolower((string) $user->email);
                $userName = strtolower((string) ($user->username ?? ''));
                if (str_contains($userEmail, 'superadmin') || str_contains($userName, 'superadmin')) {
                    $isSuperAdmin = true;
                }
            }
        }

        if (! $isSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Super Admin yang berhak melakukan reset database.',
            ], 403);
        }

        $request->validate([
            'confirmation_key' => 'required|string',
            'scope' => 'nullable|string|in:operational,all_except_superadmin',
        ]);

        if (trim(strtoupper($request->confirmation_key)) !== 'RESET DATABASE') {
            return response()->json([
                'success' => false,
                'message' => 'Kata kunci konfirmasi salah. Ketik "RESET DATABASE" untuk melanjutkan.',
            ], 422);
        }

        $scope = $request->input('scope', 'all_except_superadmin');
        $cleared = [];
        $driver = DB::getDriverName();

        try {
            DB::beginTransaction();

            // Nonaktifkan foreign key checks untuk menghindari integrity constraint violation saat bulk delete
            if ($driver === 'pgsql') {
                DB::statement("SET session_replication_role = 'replica';");
            } elseif ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
            }

            // 1. Bersihkan tabel operasional (Presensi, Nilai, LMS, Mutabaah, Tahfizh, Notifikasi, Chat)
            $operationalTables = [
                'attendance_scan_logs',
                'student_attendance_permissions',
                'homeroom_attendance_follow_ups',
                'worship_attendance_details',
                'worship_attendances',
                'lesson_attendances',
                'teaching_attendances',
                'gate_attendances',
                'attendances',
                'mutabaah_daily_details',
                'mutabaah_daily_notes',
                'mutabaah_parent_confirmations',
                'mutabaah_daily_headers',
                'mutabaah_daily_records',
                'mutabaah_evaluations',
                'mutabaah_entries',
                'mutabaah_activity_notes',
                'mutabaah_activity_logs',
                'tahfizh_ayah_achievements',
                'tahfizh_exams',
                'tahfizh_records',
                'tahfizh_targets',
                'student_prayer_assessments',
                'assessment_scores',
                'student_grades',
                'grades',
                'lms_pengumpulan_tugas',
                'lms_penugasan',
                'lms_ujian_sesi',
                'lms_ujian',
                'lms_aktivitas_belajar',
                'lms_diskusi',
                'lms_rapors',
                'lms_rapor',
                'lms_modul_ajar',
                'lms_materi',
                'lms_bank_soal',
                'lms_kisi_kisi',
                'dormitory_permits',
                'dormitory_deposits',
                'clinic_logs',
                'student_medical_histories',
                'student_point_transactions',
                'student_achievements',
                'student_violations',
                'counseling_records',
                'student_mutations',
                'student_notes',
                'portal_message_reactions',
                'portal_messages',
                'conversation_participants',
                'conversations',
                'employee_chats',
                'notifications',
                'delete_requests',
                'login_events',
                'user_devices',
                'user_presences',
            ];

            foreach ($operationalTables as $tbl) {
                if (Schema::hasTable($tbl)) {
                    $cnt = DB::table($tbl)->count();
                    DB::table($tbl)->delete();
                    $cleared[$tbl] = $cnt;
                }
            }

            // 2. Jika scope all_except_superadmin: Bersihkan Siswa, Orang Tua, dan Pegawai Non-Admin
            if ($scope === 'all_except_superadmin') {
                $superAdminUserIds = [];

                try {
                    $roleIds = DB::table('roles')
                        ->whereIn('name', ['Super Admin', 'super_admin', 'superadmin', 'Admin', 'admin'])
                        ->pluck('id');

                    if ($roleIds->isNotEmpty()) {
                        $superAdminUserIds = DB::table('model_has_roles')
                            ->whereIn('role_id', $roleIds)
                            ->pluck('model_id')
                            ->all();
                    }
                } catch (\Throwable) {}

                try {
                    $emailIds = DB::table('users')
                        ->where('email', 'like', '%superadmin%')
                        ->orWhere('username', 'like', '%superadmin%')
                        ->pluck('id')
                        ->all();
                    $superAdminUserIds = array_unique(array_merge($superAdminUserIds, $emailIds));
                } catch (\Throwable) {}

                if ($user && ! in_array($user->id, $superAdminUserIds)) {
                    $superAdminUserIds[] = $user->id;
                }

                // Tabel pivot & relasi langsung siswa / santri
                foreach (['student_parents', 'qr_credentials', 'student_card_settings', 'alumni'] as $sRelTbl) {
                    if (Schema::hasTable($sRelTbl)) {
                        DB::table($sRelTbl)->delete();
                    }
                }

                if (Schema::hasTable('students')) {
                    $cnt = DB::table('students')->count();
                    DB::table('students')->delete();
                    $cleared['students'] = $cnt;
                }

                if (Schema::hasTable('parents')) {
                    $cnt = DB::table('parents')->count();
                    DB::table('parents')->delete();
                    $cleared['parents'] = $cnt;
                }

                if (Schema::hasTable('employees')) {
                    $cnt = DB::table('employees')
                        ->whereNotIn('user_id', $superAdminUserIds)
                        ->count();
                    DB::table('employees')
                        ->whereNotIn('user_id', $superAdminUserIds)
                        ->delete();
                    $cleared['employees'] = $cnt;
                }

                // Hapus token & role untuk user non-superadmin
                if (Schema::hasTable('personal_access_tokens')) {
                    DB::table('personal_access_tokens')
                        ->whereNotIn('tokenable_id', $superAdminUserIds)
                        ->delete();
                }

                if (Schema::hasTable('model_has_roles')) {
                    DB::table('model_has_roles')
                        ->where('model_type', User::class)
                        ->whereNotIn('model_id', $superAdminUserIds)
                        ->delete();
                }

                if (Schema::hasTable('model_has_permissions')) {
                    DB::table('model_has_permissions')
                        ->where('model_type', User::class)
                        ->whereNotIn('model_id', $superAdminUserIds)
                        ->delete();
                }

                // Hapus user non-superadmin
                $userCnt = DB::table('users')
                    ->whereNotIn('id', $superAdminUserIds)
                    ->count();
                DB::table('users')
                    ->whereNotIn('id', $superAdminUserIds)
                    ->delete();
                $cleared['users'] = $userCnt;
            }

            // Kembalikan foreign key checks
            if ($driver === 'pgsql') {
                DB::statement("SET session_replication_role = 'origin';");
            } elseif ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            }

            DB::commit();

            // Refresh cache
            Artisan::call('cache:clear');

            return response()->json([
                'success' => true,
                'message' => 'Reset database berhasil dieksekusi dengan aman.',
                'data' => [
                    'cleared_tables' => $cleared,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            // Pastikan foreign key checks dipulihkan kembali saat rollback
            try {
                if ($driver === 'pgsql') {
                    DB::statement("SET session_replication_role = 'origin';");
                } elseif ($driver === 'mysql') {
                    DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
                }
            } catch (\Throwable) {}

            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan reset database: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ekspor data tabel (parsial per tabel atau semua tabel sekaligus).
     */
    public function export(string $table): BinaryFileResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        require_once app_path('Exports/AllMasterDataExport.php');

        $timestamp = date('Ymd_His');

        switch ($table) {
            case 'all':
                return Excel::download(new AllMasterDataExport(), "master_data_terpadu_{$timestamp}.xlsx");

            case 'students':
                return Excel::download(new StudentExport(Student::with(['educationUnit', 'kelas', 'parent'])), "master_siswa_{$timestamp}.xlsx");

            case 'employees':
                $exportSheet = new \App\Exports\PegawaiExportSheet();
                return Excel::download(new class($exportSheet) implements \Maatwebsite\Excel\Concerns\WithMultipleSheets {
                    public function __construct(private $sheet) {}
                    public function sheets(): array { return [$this->sheet]; }
                }, "master_pegawai_{$timestamp}.xlsx");

            case 'positions':
                $exportSheet = new \App\Exports\JabatanExportSheet();
                return Excel::download(new class($exportSheet) implements \Maatwebsite\Excel\Concerns\WithMultipleSheets {
                    public function __construct(private $sheet) {}
                    public function sheets(): array { return [$this->sheet]; }
                }, "master_jabatan_{$timestamp}.xlsx");

            case 'education_units':
                $exportSheet = new \App\Exports\UnitPendidikanExportSheet();
                return Excel::download(new class($exportSheet) implements \Maatwebsite\Excel\Concerns\WithMultipleSheets {
                    public function __construct(private $sheet) {}
                    public function sheets(): array { return [$this->sheet]; }
                }, "master_unit_pendidikan_{$timestamp}.xlsx");

            case 'classes':
                $exportSheet = new \App\Exports\KelasExportSheet();
                return Excel::download(new class($exportSheet) implements \Maatwebsite\Excel\Concerns\WithMultipleSheets {
                    public function __construct(private $sheet) {}
                    public function sheets(): array { return [$this->sheet]; }
                }, "master_kelas_{$timestamp}.xlsx");

            case 'subjects':
                $exportSheet = new \App\Exports\MapelExportSheet();
                return Excel::download(new class($exportSheet) implements \Maatwebsite\Excel\Concerns\WithMultipleSheets {
                    public function __construct(private $sheet) {}
                    public function sheets(): array { return [$this->sheet]; }
                }, "master_mapel_{$timestamp}.xlsx");

            default:
                abort(404, 'Tabel tidak dikenali.');
        }
    }

    /**
     * Dapatkan template impor data.
     */
    public function template(string $table): BinaryFileResponse
    {
        return $this->export($table);
    }

    /**
     * Impor data (parsial per tabel atau semua tabel sekaligus dari berkas).
     */
    public function import(Request $request, string $table): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        if (! $request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan pilih berkas Excel (.xlsx) atau CSV untuk diimpor.',
            ], 422);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $tmpDir = storage_path('app/imports');
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }
        $tmpName = 'sys_import_' . uniqid() . '.' . $ext;
        $file->move($tmpDir, $tmpName);
        $tmpPath = $tmpDir . '/' . $tmpName;

        try {
            if (in_array($ext, ['csv', 'txt'])) {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            } elseif ($ext === 'xls') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            } else {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            }

            $spreadsheet = $reader->load($tmpPath);

            if ($table === 'all') {
                $result = $this->importAllSheets($spreadsheet);
            } elseif ($table === 'students') {
                $sheet = $spreadsheet->getActiveSheet();
                $result = $this->importStudentsSheet($sheet);
            } elseif ($table === 'employees') {
                $sheet = $spreadsheet->getActiveSheet();
                $result = $this->importEmployeesSheet($sheet);
            } elseif ($table === 'positions') {
                $sheet = $spreadsheet->getActiveSheet();
                $result = $this->importPositionsSheet($sheet);
            } elseif ($table === 'education_units') {
                $sheet = $spreadsheet->getActiveSheet();
                $result = $this->importEducationUnitsSheet($sheet);
            } elseif ($table === 'classes') {
                $sheet = $spreadsheet->getActiveSheet();
                $result = $this->importClassesSheet($sheet);
            } elseif ($table === 'subjects') {
                $sheet = $spreadsheet->getActiveSheet();
                $result = $this->importSubjectsSheet($sheet);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Tabel tidak dikenali.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => "Impor {$table} selesai diproses.",
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses berkas impor: ' . $e->getMessage(),
            ], 422);
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Impor seluruh sheet dalam workbook.
     */
    protected function importAllSheets(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): array
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $summary = [];

        foreach ($sheetNames as $sName) {
            $sheet = $spreadsheet->getSheetByName($sName);
            $cleanName = strtolower(trim($sName));

            if (str_contains($cleanName, 'unit')) {
                $summary['education_units'] = $this->importEducationUnitsSheet($sheet);
            } elseif (str_contains($cleanName, 'jabatan') || str_contains($cleanName, 'struktur')) {
                $summary['positions'] = $this->importPositionsSheet($sheet);
            } elseif (str_contains($cleanName, 'kelas') || str_contains($cleanName, 'rombel')) {
                $summary['classes'] = $this->importClassesSheet($sheet);
            } elseif (str_contains($cleanName, 'mapel') || str_contains($cleanName, 'pelajaran')) {
                $summary['subjects'] = $this->importSubjectsSheet($sheet);
            } elseif (str_contains($cleanName, 'pegawai') || str_contains($cleanName, 'guru')) {
                $summary['employees'] = $this->importEmployeesSheet($sheet);
            } elseif (str_contains($cleanName, 'siswa') || str_contains($cleanName, 'santri')) {
                $summary['students'] = $this->importStudentsSheet($sheet);
            }
        }

        return $summary;
    }

    /**
     * Impor Siswa dari worksheet (Mendukung format Google Sheet 109 kolom).
     */
    protected function importStudentsSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, false);
        if (count($rows) < 2) {
            return ['total' => 0, 'berhasil' => 0, 'diperbarui' => 0, 'gagal' => 0, 'errors' => ['Lembar kerja kosong']];
        }

        $headers = array_map(fn($h) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$h)), $rows[0]);

        $allUnits = EducationUnit::all();
        $allClasses = Kelas::all();

        $berhasil = 0;
        $diperbarui = 0;
        $gagal = 0;
        $errors = [];

        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            if (empty(array_filter($r, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $mapped = [];
            foreach ($headers as $cIdx => $hKey) {
                if ($hKey) {
                    $mapped[$hKey] = isset($r[$cIdx]) ? trim((string)$r[$cIdx]) : '';
                }
            }

            $nama = $mapped['namalengkap'] ?? $mapped['nama'] ?? $mapped['fullname'] ?? '';
            $nis = $mapped['nissekolah'] ?? $mapped['nis'] ?? $mapped['nopendaftaran'] ?? '';
            $nisn = $mapped['nisnnasional'] ?? $mapped['nisn'] ?? null;

            if (empty($nama) || empty($nis)) {
                $gagal++;
                $errors[] = "Baris " . ($i + 1) . ": Nama atau NIS tidak boleh kosong.";
                continue;
            }

            // Resolve Unit Pendidikan
            $unitNameRaw = $mapped['unitpendidikan'] ?? $mapped['unit'] ?? '';
            $matchedUnit = null;
            if ($unitNameRaw) {
                $cleanUnit = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $unitNameRaw));
                $matchedUnit = $allUnits->first(function ($u) use ($cleanUnit) {
                    $uName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $u->name));
                    $uCode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $u->code ?? ''));
                    return str_contains($cleanUnit, $uName) || str_contains($uName, $cleanUnit) || ($uCode && str_contains($cleanUnit, $uCode));
                });
            }

            // Resolve Kelas
            $classNameRaw = $mapped['kelas'] ?? $mapped['keterangankelas'] ?? '';
            $matchedClass = null;
            if ($classNameRaw) {
                $cleanClass = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $classNameRaw));
                $matchedClass = $allClasses->first(function ($c) use ($cleanClass, $matchedUnit) {
                    $cName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $c->nama_kelas));
                    $unitMatch = $matchedUnit ? ($c->unit_pendidikan_id === $matchedUnit->id) : true;
                    return $unitMatch && ($cleanClass === $cName || str_contains($cleanClass, $cName));
                });
            }

            // Map Gender
            $genderRaw = strtolower($mapped['jeniskelamin'] ?? $mapped['gender'] ?? '');
            $gender = (str_contains($genderRaw, 'p') || str_contains($genderRaw, 'wanita')) ? 'female' : 'male';

            // Build Metadata
            $metadata = [];
            foreach ($mapped as $k => $v) {
                if ($v !== '' && !in_array($k, ['namalengkap', 'nama', 'nissekolah', 'nis', 'nisnnasional', 'nisn', 'jeniskelamin', 'gender'])) {
                    $metadata[$k] = $v;
                }
            }

            // Handle Orang Tua if Father/Mother details exist
            $parentId = null;
            $namaAyah = $mapped['namaayah'] ?? '';
            $namaIbu = $mapped['namaibu'] ?? '';
            $noHpOrtu = $mapped['nomorwaayah'] ?? $mapped['hpayah'] ?? $mapped['nomorwaibu'] ?? $mapped['hpibu'] ?? '';

            if ($namaAyah || $namaIbu) {
                $parentName = $namaAyah ?: $namaIbu;
                $parent = ParentModel::firstOrCreate(
                    ['full_name' => $parentName, 'phone' => $noHpOrtu ?: null],
                    [
                        'occupation' => $mapped['pekerjaanayah'] ?? ($mapped['pekerjaanibu'] ?? null),
                        'address' => $mapped['alamatayah'] ?? ($mapped['alamatibu'] ?? ($mapped['alamatsiswa'] ?? null)),
                        'metadata' => [
                            'ayah' => array_filter([
                                'nama' => $namaAyah,
                                'nik' => $mapped['nikayah'] ?? null,
                                'pekerjaan' => $mapped['pekerjaanayah'] ?? null,
                                'penghasilan' => $mapped['penghasilanayah'] ?? null,
                                'no_hp' => $mapped['hpayah'] ?? null,
                            ]),
                            'ibu' => array_filter([
                                'nama' => $namaIbu,
                                'nik' => $mapped['nikibu'] ?? null,
                                'pekerjaan' => $mapped['pekerjaanibu'] ?? null,
                                'penghasilan' => $mapped['penghasilanibu'] ?? null,
                                'no_hp' => $mapped['hpibu'] ?? null,
                            ]),
                        ],
                    ]
                );
                $parentId = $parent->id;
            }

            $existing = Student::withTrashed()->where('nis', $nis)->first();

            $dataPayload = [
                'full_name' => $nama,
                'nis' => $nis,
                'nisn' => $nisn,
                'gender' => $gender,
                'unit_id' => $matchedUnit?->id,
                'kelas_id' => $matchedClass?->id,
                'parent_id' => $parentId,
                'birth_date' => !empty($mapped['tanggallahir']) ? date('Y-m-d', strtotime($mapped['tanggallahir'])) : null,
                'birth_place' => $mapped['tempatlahir'] ?? null,
                'address' => $mapped['alamatsiswa'] ?? null,
                'is_active' => !isset($mapped['statussiswa']) || str_contains(strtolower($mapped['statussiswa']), 'aktif'),
                'tahun_masuk' => !empty($mapped['tahunajaranmasuk']) ? (int) substr($mapped['tahunajaranmasuk'], 0, 4) : 2026,
                'metadata' => $metadata,
            ];

            if ($existing) {
                $existing->update($dataPayload);
                $diperbarui++;
            } else {
                Student::create($dataPayload);
                $berhasil++;
            }
        }

        return [
            'total' => count($rows) - 1,
            'berhasil' => $berhasil,
            'diperbarui' => $diperbarui,
            'gagal' => $gagal,
            'errors' => array_slice($errors, 0, 10),
        ];
    }

    /**
     * Impor Pegawai / Guru dari worksheet (Mendukung format Google Sheet 13 kolom).
     */
    protected function importEmployeesSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, false);
        if (count($rows) < 2) {
            return ['total' => 0, 'berhasil' => 0, 'diperbarui' => 0, 'gagal' => 0, 'errors' => ['Lembar kerja kosong']];
        }

        $headers = array_map(fn($h) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$h)), $rows[0]);

        $allUnits = EducationUnit::all();
        $allPositions = Position::all();
        $allClasses = Kelas::all();

        $berhasil = 0;
        $diperbarui = 0;
        $gagal = 0;
        $errors = [];

        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            if (empty(array_filter($r, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $mapped = [];
            foreach ($headers as $cIdx => $hKey) {
                if ($hKey) {
                    $mapped[$hKey] = isset($r[$cIdx]) ? trim((string)$r[$cIdx]) : '';
                }
            }

            $nama = $mapped['namalengkap'] ?? $mapped['nama'] ?? '';
            $niy = $mapped['niynoindukyayasan'] ?? $mapped['niy'] ?? $mapped['nip'] ?? '';

            if (empty($nama) || empty($niy)) {
                $gagal++;
                $errors[] = "Baris " . ($i + 1) . ": Nama atau NIY pegawai tidak boleh kosong.";
                continue;
            }

            // Resolve Unit
            $unitRaw = $mapped['unitpendidikan'] ?? $mapped['satuankerjaasal'] ?? $mapped['unit'] ?? '';
            $matchedUnit = null;
            if ($unitRaw) {
                $cleanUnit = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $unitRaw));
                $matchedUnit = $allUnits->first(function ($u) use ($cleanUnit) {
                    $uName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $u->name));
                    $uCode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $u->code ?? ''));
                    return str_contains($cleanUnit, $uName) || str_contains($uName, $cleanUnit) || ($uCode && str_contains($cleanUnit, $uCode));
                });
            }

            // Resolve Position / Jabatan
            $jabatanRaw = $mapped['jabatan'] ?? 'Guru';
            $matchedPosition = null;
            if ($jabatanRaw) {
                $cleanPos = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $jabatanRaw));
                $matchedPosition = $allPositions->first(function ($p) use ($cleanPos) {
                    $pName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $p->name));
                    return str_contains($cleanPos, $pName) || str_contains($pName, $cleanPos);
                });
            }

            // Map Gender
            $genderRaw = strtolower($mapped['jeniskelamin'] ?? '');
            $gender = (str_contains($genderRaw, 'p') || str_contains($genderRaw, 'wanita')) ? 'P' : 'L';

            $metadata = [
                'satuan_kerja_asal' => $mapped['satuankerjaasal'] ?? '',
                'kelas_perwalian' => $mapped['kelasperwalian'] ?? '',
                'kelas_diajar' => $mapped['kelasyangdiajar'] ?? '',
                'mapel_diajar' => $mapped['mapelyangdiajar'] ?? '',
            ];

            $existing = Employee::withTrashed()->where('niy', $niy)->first();

            $dataPayload = [
                'nama_lengkap' => $nama,
                'niy' => $niy,
                'nik' => $mapped['nik'] ?? null,
                'jenis_kelamin' => $gender,
                'unit_id' => $matchedUnit?->id,
                'jabatan_id' => $matchedPosition?->id,
                'no_hp' => $mapped['nomorhp'] ?? $mapped['nohp'] ?? null,
                'email' => $mapped['email'] ?? null,
                'status' => 'Aktif',
                'status_pegawai' => 'Tetap',
                'metadata' => $metadata,
            ];

            if ($existing) {
                $existing->update($dataPayload);
                $diperbarui++;
                $empRecord = $existing;
            } else {
                $empRecord = Employee::create($dataPayload);
                $berhasil++;
            }

            // Bind Wali Kelas if Kelas Perwalian is filled
            $kelasWaliRaw = $mapped['kelasperwalian'] ?? '';
            if ($kelasWaliRaw && $kelasWaliRaw !== '-' && $empRecord) {
                $cleanKW = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $kelasWaliRaw));
                $targetClass = $allClasses->first(function ($c) use ($cleanKW, $matchedUnit) {
                    $cName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $c->nama_kelas));
                    $unitMatch = $matchedUnit ? ($c->unit_pendidikan_id === $matchedUnit->id) : true;
                    return $unitMatch && (str_contains($cleanKW, $cName) || str_contains($cName, $cleanKW));
                });
                if ($targetClass) {
                    $targetClass->update(['wali_kelas_id' => $empRecord->id]);
                }
            }
        }

        return [
            'total' => count($rows) - 1,
            'berhasil' => $berhasil,
            'diperbarui' => $diperbarui,
            'gagal' => $gagal,
            'errors' => array_slice($errors, 0, 10),
        ];
    }

    /**
     * Impor Jabatan & Struktur Organisasi (Mendukung format Google Sheet 4 kolom).
     */
    protected function importPositionsSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, false);
        if (count($rows) < 2) {
            return ['total' => 0, 'berhasil' => 0, 'diperbarui' => 0, 'gagal' => 0, 'errors' => ['Lembar kerja kosong']];
        }

        $headers = array_map(fn($h) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$h)), $rows[0]);

        $berhasil = 0;
        $diperbarui = 0;
        $gagal = 0;
        $errors = [];

        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            if (empty(array_filter($r, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $mapped = [];
            foreach ($headers as $cIdx => $hKey) {
                if ($hKey) {
                    $mapped[$hKey] = isset($r[$cIdx]) ? trim((string)$r[$cIdx]) : '';
                }
            }

            $nama = $mapped['nama'] ?? $mapped['namajabatan'] ?? '';
            if (empty($nama) || $nama === 'Siswa' || str_contains($nama, 'Orang Tua')) {
                continue;
            }

            $satuanKerja = $mapped['satuankerja'] ?? 'Unit Pendidikan';
            $code = strtoupper(Str::slug($nama, '_'));

            // Auto level mapping based on nama
            $level = 8;
            if (str_contains(strtolower($nama), 'ketua') || str_contains(strtolower($nama), 'sekretaris') || str_contains(strtolower($nama), 'bendahara yayasan')) {
                $level = 1;
            } elseif (str_contains(strtolower($nama), 'bidang pendidikan') || str_contains(strtolower($nama), 'bagian')) {
                $level = 2;
            } elseif (str_contains(strtolower($nama), 'kepala sekolah')) {
                $level = 3;
            } elseif (str_contains(strtolower($nama), 'wakil')) {
                $level = 4;
            } elseif (str_contains(strtolower($nama), 'tata usaha') || str_contains(strtolower($nama), 'tu')) {
                $level = 6;
            } elseif (str_contains(strtolower($nama), 'operator')) {
                $level = 7;
            } elseif (str_contains(strtolower($nama), 'musyrif')) {
                $level = 9;
            }

            $existing = Position::where('name', $nama)->orWhere('code', $code)->first();

            $dataPayload = [
                'name' => $nama,
                'code' => $code,
                'satuan_kerja' => $satuanKerja,
                'level_jabatan' => $level,
                'is_active' => true,
                'metadata' => ['satuan_kerja' => $satuanKerja, 'level_user' => $level],
            ];

            if ($existing) {
                $existing->update($dataPayload);
                $diperbarui++;
            } else {
                Position::create($dataPayload);
                $berhasil++;
            }
        }

        return [
            'total' => count($rows) - 1,
            'berhasil' => $berhasil,
            'diperbarui' => $diperbarui,
            'gagal' => $gagal,
            'errors' => $errors,
        ];
    }

    /**
     * Impor Unit Pendidikan dari worksheet.
     */
    protected function importEducationUnitsSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, false);
        if (count($rows) < 2) {
            return ['total' => 0, 'berhasil' => 0, 'diperbarui' => 0, 'gagal' => 0, 'errors' => ['Lembar kerja kosong']];
        }

        $headers = array_map(fn($h) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$h)), $rows[0]);

        $berhasil = 0;
        $diperbarui = 0;
        $gagal = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            if (empty(array_filter($r, fn($v) => $v !== null && $v !== ''))) continue;

            $mapped = [];
            foreach ($headers as $cIdx => $hKey) {
                if ($hKey) $mapped[$hKey] = isset($r[$cIdx]) ? trim((string)$r[$cIdx]) : '';
            }

            $nama = $mapped['namaunitpendidikan'] ?? $mapped['nama'] ?? '';
            $code = $mapped['kodeunit'] ?? $mapped['kode'] ?? Str::slug($nama);
            if (empty($nama)) continue;

            $level = $mapped['jenjang'] ?? 'SD';
            $existing = EducationUnit::where('name', $nama)->orWhere('code', $code)->first();

            $payload = [
                'name' => $nama,
                'code' => $code,
                'level' => $level,
                'is_active' => true,
            ];

            if ($existing) {
                $existing->update($payload);
                $diperbarui++;
            } else {
                EducationUnit::create($payload);
                $berhasil++;
            }
        }

        return ['total' => count($rows) - 1, 'berhasil' => $berhasil, 'diperbarui' => $diperbarui, 'gagal' => $gagal, 'errors' => []];
    }

    /**
     * Impor Kelas & Rombel dari worksheet.
     */
    protected function importClassesSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, false);
        if (count($rows) < 2) {
            return ['total' => 0, 'berhasil' => 0, 'diperbarui' => 0, 'gagal' => 0, 'errors' => ['Lembar kerja kosong']];
        }

        $headers = array_map(fn($h) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$h)), $rows[0]);
        $allUnits = EducationUnit::all();

        $berhasil = 0;
        $diperbarui = 0;
        $gagal = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            if (empty(array_filter($r, fn($v) => $v !== null && $v !== ''))) continue;

            $mapped = [];
            foreach ($headers as $cIdx => $hKey) {
                if ($hKey) $mapped[$hKey] = isset($r[$cIdx]) ? trim((string)$r[$cIdx]) : '';
            }

            $nama = $mapped['namakelas'] ?? $mapped['nama'] ?? '';
            if (empty($nama)) continue;

            $unitRaw = $mapped['unitpendidikan'] ?? $mapped['unit'] ?? '';
            $matchedUnit = $allUnits->first(fn($u) => str_contains(strtolower($u->name), strtolower($unitRaw)));

            $payload = [
                'nama_kelas' => $nama,
                'tingkat' => $mapped['tingkat'] ?? '1',
                'kapasitas' => (int) ($mapped['kapasitas'] ?? 30),
                'unit_pendidikan_id' => $matchedUnit?->id,
                'status' => 'Aktif',
            ];

            $existing = Kelas::where('nama_kelas', $nama)
                ->when($matchedUnit, fn($q) => $q->where('unit_pendidikan_id', $matchedUnit->id))
                ->first();

            if ($existing) {
                $existing->update($payload);
                $diperbarui++;
            } else {
                Kelas::create($payload);
                $berhasil++;
            }
        }

        return ['total' => count($rows) - 1, 'berhasil' => $berhasil, 'diperbarui' => $diperbarui, 'gagal' => $gagal, 'errors' => []];
    }

    /**
     * Impor Mata Pelajaran dari worksheet.
     */
    protected function importSubjectsSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, false);
        if (count($rows) < 2) {
            return ['total' => 0, 'berhasil' => 0, 'diperbarui' => 0, 'gagal' => 0, 'errors' => ['Lembar kerja kosong']];
        }

        $headers = array_map(fn($h) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$h)), $rows[0]);

        $berhasil = 0;
        $diperbarui = 0;
        $gagal = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            if (empty(array_filter($r, fn($v) => $v !== null && $v !== ''))) continue;

            $mapped = [];
            foreach ($headers as $cIdx => $hKey) {
                if ($hKey) $mapped[$hKey] = isset($r[$cIdx]) ? trim((string)$r[$cIdx]) : '';
            }

            $nama = $mapped['namamatapelajaran'] ?? $mapped['nama'] ?? '';
            $code = $mapped['kodemapel'] ?? $mapped['kode'] ?? strtoupper(Str::slug($nama, '_'));
            if (empty($nama)) continue;

            $payload = [
                'name' => $nama,
                'code' => $code,
                'description' => $mapped['kategori'] ?? 'Umum',
                'status' => true,
                'metadata' => ['category' => $mapped['kategori'] ?? 'Umum', 'kkm' => (int)($mapped['kkmstandar'] ?? 75)],
            ];

            if (Schema::hasColumn('subjects', 'is_active')) {
                $payload['is_active'] = true;
            }

            $existing = Subject::where('code', $code)->orWhere('name', $nama)->first();

            if ($existing) {
                $existing->update($payload);
                $diperbarui++;
            } else {
                Subject::create($payload);
                $berhasil++;
            }
        }

        return ['total' => count($rows) - 1, 'berhasil' => $berhasil, 'diperbarui' => $diperbarui, 'gagal' => $gagal, 'errors' => []];
    }
}
