<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JadwalPelajaranSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Dapatkan atau Buat Tahun Ajaran Aktif
        $tahunAjaran = AcademicYear::query()->where('is_active', true)->first();
        if (! $tahunAjaran) {
            $tahunAjaran = AcademicYear::query()->firstOrCreate([
                'name' => '2025/2026',
            ], [
                'start_date' => '2025-07-01',
                'end_date' => '2026-06-30',
                'is_active' => true,
                'metadata' => ['source' => 'JadwalPelajaranSeeder'],
            ]);
        }

        // 2. Dapatkan atau Buat Semester Ganjil & Genap. Dates must follow
        // the selected academic year; hard-coded dates can put the semester
        // outside the active year and make downstream fixtures disappear.
        $yearStart = Carbon::parse($tahunAjaran->start_date);
        $yearEnd = Carbon::parse($tahunAjaran->end_date);
        $ganjilEnd = $yearStart->copy()->addMonths(5)->endOfMonth();
        if ($ganjilEnd->gt($yearEnd)) {
            $ganjilEnd = $yearEnd->copy();
        }
        $genapStart = $ganjilEnd->copy()->addDay();

        $semesterGanjil = Semester::query()->updateOrCreate([
            'academic_year_id' => $tahunAjaran->id,
            'sequence' => 1,
        ], [
            'name' => 'Ganjil',
            'start_date' => $yearStart->toDateString(),
            'end_date' => $ganjilEnd->toDateString(),
            'is_active' => true,
            'metadata' => ['source' => 'JadwalPelajaranSeeder'],
        ]);

        $semesterGenap = Semester::query()->updateOrCreate([
            'academic_year_id' => $tahunAjaran->id,
            'sequence' => 2,
        ], [
            'name' => 'Genap',
            'start_date' => $genapStart->toDateString(),
            'end_date' => $yearEnd->toDateString(),
            'is_active' => false,
            'metadata' => ['source' => 'JadwalPelajaranSeeder'],
        ]);

        // 3. Ambil / Pastikan Unit Pendidikan Tersedia
        $units = EducationUnit::query()->orderBy('code')->get();

        // 4. Ambil / Pastikan Guru / Pegawai Tersedia (Dinamis)
        $guruList = Employee::query()->orderBy('id')->get();
        if ($guruList->isEmpty()) {
            $namaGuruDummy = [
                'Ustadz Abdullah Faqih, S.Pd.I',
                'Ustadzah Maryam Safitri, M.Pd',
                'Ustadz Hamzah Fansuri, S.Si',
                'Ustadzah Khadijah Azzahra, S.Pd',
                'Ustadz Ahmad Zaki, S.T',
                'Ustadzah Fatimah Azzahra, M.Ag',
                'Ustadz Muhammad Rizky, S.Pd',
                'Ustadz Ibrahim Al-Hafiz, S.Ag',
            ];
            $guruList = new Collection;
            foreach ($namaGuruDummy as $index => $nama) {
                $employee = Employee::create([
                    'nama_lengkap' => $nama,
                    'niy' => 'NIY-'.str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                    'email' => 'guru'.($index + 1).'@dareliman.sch.id',
                    'status' => 'Aktif',
                    'jenis_kelamin' => $index % 2 === 0 ? 'L' : 'P',
                ]);
                $guruList->push($employee);
            }
        }

        // 5. Ambil / Pastikan Mata Pelajaran Tersedia (Dinamis)
        $mapelList = Subject::query()->orderBy('id')->get();
        if ($mapelList->isEmpty()) {
            $mapelDummy = [
                ['code' => 'PAI-01', 'name' => 'Pendidikan Agama Islam', 'kelompok' => 'A', 'category' => 'Wajib'],
                ['code' => 'THF-01', 'name' => 'Al-Qur\'an & Tahfizh', 'kelompok' => 'A', 'category' => 'Wajib Khusus SIT'],
                ['code' => 'BIN-01', 'name' => 'Bahasa Indonesia', 'kelompok' => 'A', 'category' => 'Wajib'],
                ['code' => 'MTK-01', 'name' => 'Matematika', 'kelompok' => 'A', 'category' => 'Wajib'],
                ['code' => 'IPA-01', 'name' => 'Ilmu Pengetahuan Alam', 'kelompok' => 'A', 'category' => 'Wajib'],
                ['code' => 'IPS-01', 'name' => 'Ilmu Pengetahuan Sosial', 'kelompok' => 'A', 'category' => 'Wajib'],
                ['code' => 'BIG-01', 'name' => 'Bahasa Inggris', 'kelompok' => 'B', 'category' => 'Wajib'],
                ['code' => 'BAR-01', 'name' => 'Bahasa Arab', 'kelompok' => 'B', 'category' => 'Muatan Lokal SIT'],
            ];
            $mapelList = new Collection;
            foreach ($mapelDummy as $m) {
                $subject = Subject::create([
                    'kode_mapel' => $m['code'],
                    'nama_mapel' => $m['name'],
                    'nama_singkat' => explode(' ', $m['name'])[0],
                    'kelompok_mapel' => $m['kelompok'],
                    'kategori' => $m['category'],
                    'status' => true,
                ]);
                $mapelList->push($subject);
            }
        }

        // 6. Ambil / Pastikan Kelas & Rombel Tersedia (Dinamis)
        $kelasList = Kelas::query()
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->orderBy('id')
            ->get();
        if ($kelasList->isEmpty()) {
            $kelasList = Kelas::query()->orderBy('id')->get();
        }
        if ($kelasList->isEmpty()) {
            $kelasDummy = [
                ['kode' => '7A-SMP', 'nama' => 'VII Al-Farabi', 'tingkat' => '7', 'jenjang' => 'SMP'],
                ['kode' => '7B-SMP', 'nama' => 'VII Al-Khawarizmi', 'tingkat' => '7', 'jenjang' => 'SMP'],
                ['kode' => '8A-SMP', 'nama' => 'VIII Ibnu Sina', 'tingkat' => '8', 'jenjang' => 'SMP'],
                ['kode' => '10A-SMA', 'nama' => 'X MIPA 1', 'tingkat' => '10', 'jenjang' => 'SMA'],
                ['kode' => '10B-SMA', 'nama' => 'X MIPA 2', 'tingkat' => '10', 'jenjang' => 'SMA'],
                ['kode' => '1A-SD', 'nama' => 'I Abu Bakar', 'tingkat' => '1', 'jenjang' => 'SD'],
            ];
            $unitFirst = $units->first();
            $kelasList = new Collection;
            foreach ($kelasDummy as $k) {
                $kelasObj = Kelas::create([
                    'kode_kelas' => $k['kode'],
                    'nama_kelas' => $k['nama'],
                    'tingkat' => $k['tingkat'],
                    'jenjang' => $k['jenjang'],
                    'unit_pendidikan_id' => $unitFirst?->id,
                    'tahun_ajaran_id' => $tahunAjaran->id,
                    'semester_id' => $semesterGanjil->id,
                    'kapasitas' => 30,
                    'ruangan' => 'Ruang '.$k['tingkat'].'A',
                    'status' => 'Aktif',
                ]);
                $kelasList->push($kelasObj);
            }
        }

        // 7. Sesi Slot Waktu Mengajar Standard (Senin - Jumat)
        $jamBelajar = [
            ['mulai' => '07:30:00', 'selesai' => '08:50:00'],
            ['mulai' => '09:00:00', 'selesai' => '10:20:00'],
            ['mulai' => '10:40:00', 'selesai' => '12:00:00'],
            ['mulai' => '13:00:00', 'selesai' => '14:20:00'],
        ];

        $hariBelajar = [1, 2, 3, 4, 5]; // 1: Senin, 2: Selasa, 3: Rabu, 4: Kamis, 5: Jumat

        DB::transaction(function () use (
            $tahunAjaran,
            $semesterGanjil,
            $semesterGenap,
            $kelasList,
            $guruList,
            $mapelList,
            $hariBelajar,
            $jamBelajar
        ) {
            $totalTeachers = $guruList->count();
            $totalSubjects = $mapelList->count();

            foreach ($kelasList as $kelasIndex => $kelas) {
                $scheduleIndex = 0;
                foreach ($hariBelajar as $day) {
                    foreach ($jamBelajar as $slot) {
                        $teacher = $guruList[($kelasIndex + $scheduleIndex) % $totalTeachers];
                        $subject = $mapelList[($scheduleIndex + $kelasIndex * 2) % $totalSubjects];

                        // Jadwal Semester Ganjil
                        ClassSchedule::query()->updateOrCreate([
                            'academic_year_id' => $tahunAjaran->id,
                            'semester_id' => $semesterGanjil->id,
                            'kelas_id' => $kelas->id,
                            'day_of_week' => $day,
                            'time_start' => $slot['mulai'],
                        ], [
                            'employee_id' => $teacher->id,
                            'subject_id' => $subject->id,
                            'class_id' => null,
                            'teacher_id' => null,
                            'time_end' => $slot['selesai'],
                            'week_type' => 'all',
                            'is_active' => true,
                            'metadata' => [
                                'source' => 'JadwalPelajaranSeeder',
                                'semester' => 'Ganjil',
                                'room' => $kelas->ruangan ?: 'Ruang Belajar '.$kelas->nama_kelas,
                                'dummy' => false,
                            ],
                        ]);

                        // Jadwal Semester Genap
                        ClassSchedule::query()->updateOrCreate([
                            'academic_year_id' => $tahunAjaran->id,
                            'semester_id' => $semesterGenap->id,
                            'kelas_id' => $kelas->id,
                            'day_of_week' => $day,
                            'time_start' => $slot['mulai'],
                        ], [
                            'employee_id' => $teacher->id,
                            'subject_id' => $subject->id,
                            'class_id' => null,
                            'teacher_id' => null,
                            'time_end' => $slot['selesai'],
                            'week_type' => 'all',
                            'is_active' => true,
                            'metadata' => [
                                'source' => 'JadwalPelajaranSeeder',
                                'semester' => 'Genap',
                                'room' => $kelas->ruangan ?: 'Ruang Belajar '.$kelas->nama_kelas,
                                'dummy' => false,
                            ],
                        ]);

                        $scheduleIndex++;
                    }
                }
            }
        });

        $totalJadwal = ClassSchedule::query()->where('academic_year_id', $tahunAjaran->id)->count();
        if ($this->command) {
            $this->command->info("JadwalPelajaranSeeder berhasil dijalankan dinamis: {$totalJadwal} jadwal pelajaran untuk {$kelasList->count()} kelas telah dibuat.");
        }
    }
}
