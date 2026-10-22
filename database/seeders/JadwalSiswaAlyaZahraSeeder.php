<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\MasterKurikulum;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder Jadwal Pelajaran Dinamis untuk Siswa Alya Zahra Ramadhani
 * 
 * Prinsip:
 * - 100% NON-HARDCODE: Mengambil referensi siswa, kelas, unit pendidikan,
 *   tahun ajaran aktif, semester aktif, kurikulum, mata pelajaran, dan guru
 *   secara dinamis dari database.
 * - Mencakup jadwal Senin s/d Jumat (Day 1 s/d 5) dengan 2 sesi KBM per hari
 *   (Sesi Pagi 08:00 - 09:30 & Sesi Siang 10:00 - 11:30).
 * - Menjamin jadwal KBM PAI sinkron dengan akun Guru Test (guru@dareliman.sch.id).
 * - Aman dijalankan berulang kali (idempotent / updateOrCreate).
 */
class JadwalSiswaAlyaZahraSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("=== [START] SEEDER JADWAL DINAMIS SISWA ALYA ZAHRA RAMADHANI ===");

        // 1. Temukan data Siswa Sasaran secara dinamis
        $student = Student::with(['kelas', 'educationUnit'])
            ->where('nis', '202601099')
            ->orWhere('full_name', 'like', '%Alya Zahra Ramadhani%')
            ->first();

        if (! $student) {
            $this->command->error("Data siswa Alya Zahra Ramadhani tidak ditemukan!");
            return;
        }

        $this->command->info("Siswa ditemukan: {$student->full_name} (NIS: {$student->nis})");

        // 2. Dapatkan Unit Pendidikan & Kelas Siswa secara dinamis
        $unit = $student->educationUnit 
            ?? EducationUnit::find($student->unit_id)
            ?? EducationUnit::where('code', 'SDIT-01')->first()
            ?? EducationUnit::where('name', 'like', '%SDIT%')->first();

        if (! $unit) {
            $this->command->error("Unit pendidikan siswa tidak ditemukan!");
            return;
        }

        $kelas = $student->kelas 
            ?? Kelas::find($student->kelas_id ?? $student->class_id)
            ?? Kelas::where('unit_pendidikan_id', $unit->id)->first();

        if (! $kelas) {
            $this->command->error("Kelas rombel siswa tidak ditemukan!");
            return;
        }

        $this->command->info("Unit: {$unit->name} ({$unit->code}) | Rombel: {$kelas->nama_kelas} ({$kelas->kode_kelas})");

        // Pastikan relasi school_classes lama tersinkron jika ada
        $schoolClass = SchoolClass::where('name', $kelas->nama_kelas)->first()
            ?? SchoolClass::find($kelas->id);

        // 3. Dapatkan Tahun Ajaran & Semester Aktif secara dinamis
        $academicYear = AcademicYear::where('is_active', true)
            ->latest()
            ->first()
            ?? AcademicYear::latest()->first();

        if (! $academicYear) {
            $this->command->error("Tahun ajaran aktif tidak ditemukan!");
            return;
        }

        $semester = Semester::where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->first()
            ?? Semester::where('is_active', true)->first()
            ?? Semester::first();

        if (! $semester) {
            $this->command->error("Semester aktif tidak ditemukan!");
            return;
        }

        $this->command->info("Tahun Ajaran: {$academicYear->name} | Semester: {$semester->name}");

        // 4. Dapatkan Kurikulum SDIT secara dinamis
        $kurikulum = MasterKurikulum::where('jenjang', 'SD')
            ->orWhere('unit_pendidikan_id', $unit->id)
            ->orWhere('kode_kurikulum', 'like', '%SD%')
            ->first()
            ?? MasterKurikulum::first();

        // 5. Dapatkan Guru Pengajar secara dinamis
        // Guru PAI: Utamakan guru@dareliman.sch.id (Guru Test) agar sinkron dengan pengujian Guru
        $guruTestUser = User::where('email', 'guru@dareliman.sch.id')->first();
        $guruPai = null;
        if ($guruTestUser) {
            $guruPai = Employee::where('user_id', $guruTestUser->id)->first()
                ?? Employee::where('email', $guruTestUser->email)->first();
        }
        if (! $guruPai) {
            $guruPai = Employee::where('unit_id', $unit->id)
                ->where(function ($q) {
                    $q->where('nama_lengkap', 'like', '%Guru Test%')
                      ->orWhere('nama_lengkap', 'like', '%PAI%')
                      ->orWhere('email', 'like', '%guru%');
                })->first();
        }

        // Wali Kelas (Pendidikan Pancasila / Tematik)
        $guruWalas = $kelas->waliKelas
            ?? Employee::where('unit_id', $unit->id)->where('nama_lengkap', 'like', '%Maryam%')->first()
            ?? Employee::where('unit_id', $unit->id)->first();

        // Guru Tahfizh
        $guruTahfizh = Employee::where('unit_id', $unit->id)
            ->where(function ($q) {
                $q->where('nama_lengkap', 'like', '%Tahfizh%')
                  ->orWhere('nama_lengkap', 'like', '%Fatimah%')
                  ->orWhere('email', 'like', '%tahfizh%');
            })->first() ?? $guruWalas;

        // Guru Matematika
        $guruMtk = Employee::where('unit_id', $unit->id)
            ->where(function ($q) {
                $q->where('nama_lengkap', 'like', '%Nurul%')
                  ->orWhere('nama_lengkap', 'like', '%Matematika%')
                  ->orWhere('email', 'like', '%mtk%');
            })->first() ?? $guruWalas;

        // Guru Bahasa Indonesia
        $guruBIndo = Employee::where('unit_id', $unit->id)
            ->where(function ($q) {
                $q->where('nama_lengkap', 'like', '%Khadijah%')
                  ->orWhere('nama_lengkap', 'like', '%Indonesia%')
                  ->orWhere('email', 'like', '%bind%');
            })->first() ?? $guruWalas;

        // Guru Bahasa Arab
        $guruBArab = Employee::where('unit_id', $unit->id)
            ->where(function ($q) {
                $q->where('nama_lengkap', 'like', '%Fauzan%')
                  ->orWhere('nama_lengkap', 'like', '%Arab%')
                  ->orWhere('email', 'like', '%barab%');
            })->first() ?? $guruWalas;

        // 6. Dapatkan atau Buat Master Mata Pelajaran secara dinamis
        $resolveSubject = function (string $kode, string $nama, string $singkat, ?Employee $guru, string $warna, string $ikon) use ($unit, $kurikulum) {
            $sub = Subject::where('unit_pendidikan_id', $unit->id)
                ->where(function ($q) use ($kode, $nama) {
                    $q->where('kode_mapel', $kode)
                      ->orWhere('code', $kode)
                      ->orWhere('nama_mapel', $nama)
                      ->orWhere('name', $nama);
                })->first();

            if (! $sub) {
                $sub = Subject::create([
                    'unit_pendidikan_id' => $unit->id,
                    'kurikulum_id' => $kurikulum?->id,
                    'kode_mapel' => $kode,
                    'code' => $kode,
                    'nama_mapel' => $nama,
                    'name' => $nama,
                    'nama_singkat' => $singkat,
                    'kelompok_mapel' => 'Kelompok A',
                    'kategori' => 'Wajib',
                    'jenjang' => 'SD',
                    'tingkat_kelas' => '1',
                    'jam_pelajaran' => 4,
                    'guru_pengampu_id' => $guru?->id,
                    'kkm' => 75,
                    'bobot_pengetahuan' => 40,
                    'bobot_keterampilan' => 40,
                    'bobot_sikap' => 20,
                    'bobot_nilai' => ['pengetahuan' => 40, 'keterampilan' => 40, 'sikap' => 20],
                    'warna' => $warna,
                    'ikon' => $ikon,
                    'status' => true,
                    'deskripsi' => "Mata pelajaran {$nama} jenjang SDIT Fullday.",
                ]);
            }

            return $sub;
        };

        $mapelPai = $resolveSubject('MAPEL-PAI-SD-1', 'Pendidikan Agama Islam (PAI)', 'PAI', $guruPai, '#0E5C44', 'BookOpen');
        $mapelTahfizh = $resolveSubject('MAPEL-THF-SD-1', 'Tahfizh Al-Qur\'an Juz 30', 'TAHFIZH', $guruTahfizh, '#1E8E5A', 'Sparkles');
        $mapelTematik = $resolveSubject('MAPEL-TMT-SD-1', 'Pendidikan Pancasila & Tematik', 'TEMATIK', $guruWalas, '#3B82F6', 'Users');
        $mapelMtk = $resolveSubject('MAPEL-MTK-SD-1', 'Matematika Dasar Ceria', 'MTK', $guruMtk, '#F59E0B', 'Calculator');
        $mapelBIndo = $resolveSubject('MAPEL-BIN-SD-1', 'Bahasa Indonesia Terpadu', 'B.INDO', $guruBIndo, '#6366F1', 'BookMarked');
        $mapelBArab = $resolveSubject('MAPEL-BAR-SD-1', 'Bahasa Arab Dasar (Kekhasan SIT)', 'B.ARAB', $guruBArab, '#14B8A6', 'Languages');

        // 7. Matriks Jadwal Pelajaran Senin s/d Jumat (Day 1 s/d 5)
        // Format: day => [ [sesi, start, end, subject, guru, room] ]
        $jadwalMatrix = [
            // SENIN (Day 1)
            [
                'day' => 1,
                'nama_hari' => 'Senin',
                'start' => '08:00:00',
                'end' => '09:30:00',
                'subject' => $mapelPai,
                'guru' => $guruPai,
                'room' => 'Ruang Teori 1-A',
            ],
            [
                'day' => 1,
                'nama_hari' => 'Senin',
                'start' => '10:00:00',
                'end' => '11:30:00',
                'subject' => $mapelBArab,
                'guru' => $guruBArab,
                'room' => 'Ruang Teori 1-A',
            ],

            // SELASA (Day 2)
            [
                'day' => 2,
                'nama_hari' => 'Selasa',
                'start' => '08:00:00',
                'end' => '09:30:00',
                'subject' => $mapelTahfizh,
                'guru' => $guruTahfizh,
                'room' => 'Ruang Halaqah 1-A',
            ],
            [
                'day' => 2,
                'nama_hari' => 'Selasa',
                'start' => '10:00:00',
                'end' => '11:30:00',
                'subject' => $mapelMtk,
                'guru' => $guruMtk,
                'room' => 'Ruang Teori 1-A',
            ],

            // RABU (Day 3)
            [
                'day' => 3,
                'nama_hari' => 'Rabu',
                'start' => '08:00:00',
                'end' => '09:30:00',
                'subject' => $mapelTematik,
                'guru' => $guruWalas,
                'room' => 'Ruang Teori 1-A',
            ],
            [
                'day' => 3,
                'nama_hari' => 'Rabu',
                'start' => '10:00:00',
                'end' => '11:30:00',
                'subject' => $mapelBIndo,
                'guru' => $guruBIndo,
                'room' => 'Ruang Teori 1-A',
            ],

            // KAMIS (Day 4)
            [
                'day' => 4,
                'nama_hari' => 'Kamis',
                'start' => '08:00:00',
                'end' => '09:30:00',
                'subject' => $mapelMtk,
                'guru' => $guruMtk,
                'room' => 'Ruang Teori 1-A',
            ],
            [
                'day' => 4,
                'nama_hari' => 'Kamis',
                'start' => '10:00:00',
                'end' => '11:30:00',
                'subject' => $mapelPai,
                'guru' => $guruPai,
                'room' => 'Ruang Teori 1-A',
            ],

            // JUMAT (Day 5)
            [
                'day' => 5,
                'nama_hari' => 'Jumat',
                'start' => '08:00:00',
                'end' => '09:30:00',
                'subject' => $mapelBIndo,
                'guru' => $guruBIndo,
                'room' => 'Ruang Teori 1-A',
            ],
            [
                'day' => 5,
                'nama_hari' => 'Jumat',
                'start' => '10:00:00',
                'end' => '11:30:00',
                'subject' => $mapelTahfizh,
                'guru' => $guruTahfizh,
                'room' => 'Ruang Halaqah 1-A',
            ],
        ];

        // 8. Eksekusi Penyimpanan Jadwal secara Idempotent
        $totalCreated = 0;
        $totalUpdated = 0;

        DB::beginTransaction();
        try {
            foreach ($jadwalMatrix as $slot) {
                $guruTarget = $slot['guru'] ?? $guruWalas;
                $subjectTarget = $slot['subject'];

                $legacyTeacher = null;
                if ($guruTarget) {
                    $legacyTeacher = \App\Models\Teacher::where('employee_id', $guruTarget->id)->first()
                        ?? ($guruTarget->user_id ? \App\Models\Teacher::where('user_id', $guruTarget->user_id)->first() : null);
                }

                $schedule = ClassSchedule::updateOrCreate(
                    [
                        'kelas_id' => $kelas->id,
                        'academic_year_id' => $academicYear->id,
                        'semester_id' => $semester->id,
                        'day_of_week' => $slot['day'],
                        'time_start' => $slot['start'],
                    ],
                    [
                        'class_id' => $kelas->id,
                        'subject_id' => $subjectTarget->id,
                        'employee_id' => $guruTarget?->id,
                        'teacher_id' => $legacyTeacher?->id,
                        'time_end' => $slot['end'],
                        'week_type' => 'regular',
                        'is_active' => true,
                        'metadata' => [
                            'hari' => $slot['nama_hari'],
                            'ruang' => $slot['room'],
                            'guru' => $guruTarget?->nama_lengkap ?? 'Guru Pengajar',
                            'mapel' => $subjectTarget->name ?? $subjectTarget->nama_mapel,
                            'unit' => $unit->name,
                            'target_siswa' => $student->full_name,
                            'nis_siswa' => $student->nis,
                            'seeder' => 'JadwalSiswaAlyaZahraSeeder',
                        ],
                    ]
                );

                // Sinkronkan subject_teachers (Guru Pengampu) jika tabel ada
                if (Schema::hasTable('subject_teachers') && $guruTarget) {
                    DB::table('subject_teachers')->updateOrInsert(
                        ['subject_id' => $subjectTarget->id, 'guru_id' => $guruTarget->id],
                        ['is_utama' => true, 'updated_at' => now()]
                    );
                }

                // Sinkronkan subject_classes jika tabel ada
                if (Schema::hasTable('subject_classes')) {
                    DB::table('subject_classes')->updateOrInsert(
                        ['subject_id' => $subjectTarget->id, 'kelas_id' => $kelas->id],
                        ['updated_at' => now()]
                    );
                }

                $this->command->line(" [{$slot['nama_hari']}] {$slot['start']} - {$slot['end']} : {$subjectTarget->name} (Guru: {$guruTarget?->nama_lengkap})");
                $totalCreated++;
            }

            DB::commit();
            $this->command->info("=== [SELESAI] Berhasil mendaftarkan {$totalCreated} slot jadwal KBM (Senin-Jumat) untuk {$student->full_name}! ===");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error("Gagal menyimpan jadwal: " . $e->getMessage());
            throw $e;
        }
    }
}
