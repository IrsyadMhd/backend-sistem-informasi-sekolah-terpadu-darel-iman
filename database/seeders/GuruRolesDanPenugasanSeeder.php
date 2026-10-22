<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class GuruRolesDanPenugasanSeeder extends Seeder
{
    /**
     * Run the database seeds for assigning teacher roles and penugasan KBM.
     */
    public function run(): void
    {
        $this->command?->info('=== MEMULAI SEEDER PENYELARASAN ROLE & PENUGASAN GURU TERPADU ===');

        // 1. Pastikan seluruh role guru tersedia
        $availableRoles = Role::pluck('name')->flip();

        // 2. Penyelarasan Akun & Role Guru Berdasarkan Posisi / Jabatan
        $this->syncTeacherRolesByPosition($availableRoles);

        // 3. Sinkronisasi tabel teachers legacy agar memuat seluruh guru pendidik
        $this->syncTeachersTable();

        // 4. Penugasan Wali Kelas ke Seluruh Rombel Kelas (100% Cakupan)
        $this->assignHomeroomTeachers();

        // 5. Distribusi Guru Pengampu ke Jadwal KBM (class_schedules)
        $this->distributeTeachersToSchedules();

        $this->command?->info('=== [SELESAI] PENYELARASAN ROLE & PENUGASAN GURU BERHASIL DISELESAIKAN ===');
    }

    /**
     * 1. Menyelaraskan akun User & Role spesifik untuk seluruh pegawai pendidik.
     */
    private function syncTeacherRolesByPosition($availableRoles): void
    {
        $this->command?->info('1. Menyelaraskan akun User dan Role spesifik pendidik berdasarkan jabatan...');

        $employees = Employee::with(['position', 'unit', 'user.roles'])->get();
        $updatedCount = 0;

        foreach ($employees as $emp) {
            // Pastikan employee memiliki User
            $user = $emp->user;
            if (! $user) {
                $email = $emp->email ?: sprintf('guru.%s@dareliman.sch.id', Str::slug($emp->niy ?: $emp->id));
                $user = User::where('email', $email)->first();

                if (! $user) {
                    $user = User::create([
                        'id' => (string) Str::uuid(),
                        'name' => $emp->nama_lengkap,
                        'email' => $email,
                        'password' => Hash::make('Bismillah2026!'),
                        'is_active' => true,
                    ]);
                }

                $emp->update(['user_id' => $user->id]);
            }

            $posName = strtolower($emp->position?->name ?? '');
            $rolesToAssign = [];

            // Evaluasi berdasarkan nama jabatan
            if (str_contains($posName, 'mapel') || str_contains($posName, 'pengajar')) {
                $rolesToAssign[] = 'Guru';
                $rolesToAssign[] = 'Guru Mata Pelajaran';
            } elseif (str_contains($posName, 'tahfizh')) {
                $rolesToAssign[] = 'Guru';
                $rolesToAssign[] = 'Guru Tahfizh';
            } elseif (str_contains($posName, 'bk') || str_contains($posName, 'konseling')) {
                $rolesToAssign[] = 'Guru';
                $rolesToAssign[] = 'Guru BK';
            } elseif (str_contains($posName, 'wali')) {
                $rolesToAssign[] = 'Guru';
                $rolesToAssign[] = 'Wali Kelas';
            } elseif (str_contains($posName, 'musyrif') || str_contains($posName, 'asrama')) {
                $rolesToAssign[] = 'Guru';
                $isPutri = str_contains(strtolower($emp->unit?->name ?? ''), 'putri') || str_contains(strtolower($emp->nama_lengkap), 'usth');
                $rolesToAssign[] = $isPutri ? 'Musyrifah' : 'Musyrif';
            } elseif (str_contains($posName, 'kurikulum')) {
                $rolesToAssign[] = 'Guru';
                $rolesToAssign[] = 'Waka Kurikulum';
                $rolesToAssign[] = 'Wakil Kurikulum';
            } elseif (str_contains($posName, 'kesiswaan') || str_contains($posName, 'kesantrian')) {
                $rolesToAssign[] = 'Guru';
                $rolesToAssign[] = 'Waka Kesiswaan';
                $rolesToAssign[] = 'Wakil Kesiswaan';
            } elseif (str_contains($posName, 'kepala sekolah')) {
                $rolesToAssign[] = 'Kepala Sekolah';
            } elseif (str_contains($posName, 'divisi') || str_contains($posName, 'pembina')) {
                $rolesToAssign[] = 'Guru';
            }

            // Filter hanya role yang terdaftar di Spatie
            $validRoles = array_filter($rolesToAssign, fn ($r) => isset($availableRoles[$r]));

            if (! empty($validRoles)) {
                $user->assignRole($validRoles);
                $updatedCount++;
            }
        }

        $this->command?->info("✓ Berhasil menyelaraskan {$updatedCount} profil pegawai dengan role pendidik yang sesuai.");
    }

    /**
     * 2. Menyelaraskan tabel teachers legacy agar memuat seluruh guru pendidik.
     */
    private function syncTeachersTable(): void
    {
        $this->command?->info('2. Menyelaraskan tabel teachers legacy dengan pegawai pendidik...');

        $employees = Employee::with('user')->get();
        $syncedCount = 0;

        foreach ($employees as $emp) {
            $existing = DB::table('teachers')->where('employee_id', $emp->id)->first();
            if (! $existing) {
                DB::table('teachers')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $emp->user_id,
                    'employee_id' => $emp->id,
                    'employee_number' => $emp->niy ?: ($emp->nik ?: 'NIY-' . substr($emp->id, 0, 8)),
                    'full_name' => $emp->nama_lengkap,
                    'phone' => $emp->no_hp,
                    'email' => $emp->email,
                    'migrated_to_employee' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $syncedCount++;
            }
        }

        $this->command?->info("✓ Berhasil menyelaraskan {$syncedCount} entri baru pada tabel teachers legacy.");
    }

    /**
     * 3. Menetapkan Wali Kelas ke Seluruh Rombel Kelas (100% Cakupan).
     */
    private function assignHomeroomTeachers(): void
    {
        $this->command?->info('3. Menugaskan Wali Kelas ke seluruh rombel kelas...');

        $classes = Kelas::whereIn('status', ['Aktif', 'aktif'])->get();
        $assignedTeacherIds = [];
        $totalAssigned = 0;

        foreach ($classes as $kelas) {
            // Jika sudah ada wali kelas yang valid, pastikan rolenya dan sinkronkan ke classes
            if ($kelas->wali_kelas_id) {
                $currentTeacher = Employee::find($kelas->wali_kelas_id);
                if ($currentTeacher?->user) {
                    $currentTeacher->user->assignRole(['Guru', 'Wali Kelas']);
                    $assignedTeacherIds[$currentTeacher->id] = true;

                    $legacyTeacher = DB::table('teachers')->where('employee_id', $currentTeacher->id)->first();
                    if ($legacyTeacher) {
                        DB::table('classes')->where('id', $kelas->id)->update([
                            'homeroom_teacher_id' => $legacyTeacher->id,
                            'updated_at' => now(),
                        ]);
                    }
                    continue;
                }
            }

            // Cari kandidat guru dari unit yang sama
            $unitId = $kelas->unit_pendidikan_id;
            $candidates = Employee::where('unit_id', $unitId)
                ->whereNotIn('id', array_keys($assignedTeacherIds))
                ->get();

            // Prioritas 1: Guru dengan jabatan 'Wali Kelas' atau 'Guru Mapel'
            $selected = $candidates->first(function ($e) {
                $p = strtolower($e->position?->name ?? '');
                return str_contains($p, 'wali') || str_contains($p, 'mapel');
            });

            // Prioritas 2: Guru manapun di unit tersebut yang belum bertugas sebagai wali kelas
            $selected ??= $candidates->first();

            // Prioritas 3: Jika di unit tersebut seluruh guru sudah bertugas, gunakan guru unit secara rotasi
            $selected ??= Employee::where('unit_id', $unitId)->first() ?? Employee::first();

            if ($selected) {
                $kelas->update(['wali_kelas_id' => $selected->id]);

                // Sinkronkan ke tabel legacy classes
                $legacyTeacher = DB::table('teachers')->where('employee_id', $selected->id)->first();
                if ($legacyTeacher) {
                    DB::table('classes')->where('id', $kelas->id)->update([
                        'homeroom_teacher_id' => $legacyTeacher->id,
                        'updated_at' => now(),
                    ]);
                }

                $assignedTeacherIds[$selected->id] = true;

                // Pastikan user dari pegawai ini memiliki role Wali Kelas dan Guru
                if ($selected->user) {
                    $selected->user->assignRole(['Guru', 'Wali Kelas']);
                }

                $totalAssigned++;
            }
        }

        $this->command?->info("✓ Berhasil menugaskan Wali Kelas ke {$totalAssigned} rombel kelas. Seluruh rombel kini memiliki Wali Kelas terverifikasi!");
    }

    /**
     * 4. Mendistribusikan Guru Pengampu ke Jadwal KBM (class_schedules).
     */
    private function distributeTeachersToSchedules(): void
    {
        $this->command?->info('4. Mendistribusikan guru pengampu secara merata ke jadwal KBM (class_schedules)...');

        $teachersByUnit = DB::table('teachers')
            ->join('employees', 'teachers.employee_id', '=', 'employees.id')
            ->select('teachers.id as teacher_id', 'employees.id as employee_id', 'employees.unit_id', 'employees.user_id')
            ->whereNotNull('employees.unit_id')
            ->get()
            ->groupBy('unit_id');

        $defaultTeacher = DB::table('teachers')->first();
        $subjects = Subject::all()->keyBy('id');
        $updatedSchedules = 0;

        DB::table('class_schedules')
            ->orderBy('id')
            ->chunkById(200, function ($schedules) use ($teachersByUnit, $subjects, $defaultTeacher, &$updatedSchedules) {
                foreach ($schedules as $sch) {
                    $subject = $subjects->get($sch->subject_id);
                    $unitId = $sch->unit_id ?? $subject?->unit_pendidikan_id;

                    $unitTeachers = $teachersByUnit->get($unitId, collect());
                    if ($unitTeachers->isEmpty()) {
                        $unitTeachers = collect([$defaultTeacher]);
                    }

                    // Tentukan guru pengampu berdasarkan mapel atau rotasi deterministik
                    $teacherRow = null;
                    if ($subject?->guru_pengampu_id) {
                        $teacherRow = $unitTeachers->firstWhere('employee_id', $subject->guru_pengampu_id);
                    }

                    if (! $teacherRow) {
                        $hashIndex = abs(crc32($sch->class_id . '|' . $sch->subject_id)) % $unitTeachers->count();
                        $teacherRow = $unitTeachers->values()->get($hashIndex) ?? $defaultTeacher;
                    }

                    if ($teacherRow && $teacherRow->teacher_id !== $sch->teacher_id) {
                        DB::table('class_schedules')
                            ->where('id', $sch->id)
                            ->update([
                                'teacher_id' => $teacherRow->teacher_id,
                                'updated_at' => now(),
                            ]);

                        // Berikan role Guru Mata Pelajaran ke guru pengampu
                        if ($teacherRow->user_id) {
                            $user = User::find($teacherRow->user_id);
                            $user?->assignRole(['Guru', 'Guru Mata Pelajaran']);
                        }

                        $updatedSchedules++;
                    }
                }
            });

        $this->command?->info("✓ Berhasil mendistribusikan {$updatedSchedules} jadwal KBM ke guru pengampu mata pelajaran terkait.");
    }
}
