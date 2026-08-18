<?php

namespace Database\Seeders;

use App\Enums\Mutabaah\DailyStatus;
use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\LmsKisiKisi;
use App\Models\LmsMateri;
use App\Models\LmsModulAjar;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use App\Models\LmsPresensi;
use App\Models\LmsRapor;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\MasterKurikulum;
use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahTemplate;
use App\Models\ParentModel;
use App\Models\PengumumanSekolah;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAttendancePermission;
use App\Models\StudentGrade;
use App\Models\StudentNote;
use App\Models\Subject;
use App\Models\TahfizhDailyLog;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuperadminStudentLinkSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder ini hanya menambah graph demo; akun dan role auth dibuat oleh
        // DefaultRoleUserSeeder dan tidak boleh diperluas menjadi multi-role.
        $superadmin = User::query()
            ->where('email', 'superadmin@school-erp.local')
            ->first();

        if (! $superadmin) {
            return;
        }

        // 2. Dapatkan Tahun Ajaran & Semester Aktif
        $academicYear = AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::create([
                'name' => '2025/2026',
                'start_date' => '2025-07-01',
                'end_date' => '2026-06-30',
                'is_active' => true,
            ]);

        $semester = Semester::query()->where('is_active', true)->first()
            ?? Semester::create([
                'academic_year_id' => $academicYear->id,
                'name' => 'Semester Ganjil',
                'sequence' => 1,
                'start_date' => '2025-07-01',
                'end_date' => '2025-12-31',
                'is_active' => true,
            ]);

        // 3. Dapatkan atau buat Kelas
        $kelasId = DB::table('tbl_kelas')->value('id') ?? DB::table('classes')->value('id');
        if (! $kelasId) {
            $unitId = DB::table('education_units')->value('id');
            if (! $unitId) {
                $unitId = (string) Str::uuid();
                DB::table('education_units')->insert([
                    'id' => $unitId,
                    'name' => 'SDIT Dar el-Iman',
                    'code' => 'SDIT-01',
                    'level' => 'SDIT',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $kelasId = (string) Str::uuid();
            DB::table('tbl_kelas')->insert([
                'id' => $kelasId,
                'unit_pendidikan_id' => $unitId,
                'tahun_ajaran_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'jenjang' => 'SD',
                'tingkat' => '6',
                'kode_kelas' => 'SA-TEST-'.Str::upper(Str::random(5)),
                'nama_kelas' => 'Kelas 6A (Testing Superadmin)',
                'status' => 'Aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Dapatkan atau buat Mata Pelajaran
        $subject = Subject::first() ?? Subject::create([
            'name' => 'Matematika Terpadu',
            'code' => 'MTK-6A',
            'is_active' => true,
        ]);

        // 5. Buat atau hubungkan ParentModel untuk Superadmin
        $parent = ParentModel::firstOrCreate(
            ['user_id' => $superadmin->id],
            [
                'full_name' => 'Orang Tua Superadmin',
                'phone' => '081299998888',
                'address' => 'Jl. Pendidikan Utama No. 1',
            ]
        );

        // 6. Buat atau hubungkan Data Siswa untuk Superadmin
        //    `kelas_id` → tbl_kelas (primer); `class_id` → classes (legacy, JANGAN
        //    diisi dengan id tbl_kelas agar FK students_class_id_foreign tetap valid).
        $student = Student::updateOrCreate(
            ['user_id' => $superadmin->id],
            [
                'parent_id' => $parent->id,
                'kelas_id' => $kelasId,
                'class_id' => null,
                'nis' => '999999',
                'nisn' => '9999999999',
                'full_name' => 'Super Admin (Siswa Test)',
                'gender' => 'male',
                'birth_date' => '2012-05-15',
                'birth_place' => 'Jakarta',
                'address' => 'Jl. Pendidikan Utama No. 1',
                'is_active' => true,
                'tahun_masuk' => 2020,
                'metadata' => [
                    'nama_panggilan' => 'AdminSiswa',
                    'agama' => 'Islam',
                    'golongan_darah' => 'O',
                    'ayah' => ['name' => 'Ayah Superadmin', 'phone' => '081299998888', 'occupation' => 'Wiraswasta'],
                    'ibu' => ['name' => 'Ibu Superadmin', 'phone' => '081288887777', 'occupation' => 'Ibu Rumah Tangga'],
                    'riwayat_pendidikan' => [
                        ['school' => 'TK Islam Terpadu', 'year' => '2018-2020', 'level' => 'TK'],
                        ['school' => 'SD Islam Terpadu', 'year' => '2020-Sekarang', 'level' => 'SD'],
                    ],
                    'prestasi' => [
                        ['title' => 'Juara 1 Olimpiade Matematika', 'category' => 'Akademik', 'year' => '2025'],
                    ],
                ],
            ]
        );

        // 7. Seed Dummy Jadwal Pelajaran
        //    `kelas_id` → tbl_kelas (primer); `class_id` → classes (legacy, read-only).
        $schedule = ClassSchedule::updateOrCreate(
            ['kelas_id' => $kelasId, 'day_of_week' => now()->dayOfWeekIso],
            [
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'class_id' => null,
                'subject_id' => $subject->id,
                'time_start' => '07:30:00',
                'time_end' => '09:00:00',
            ]
        );

        // 8. Seed Dummy Pengumuman Sekolah
        PengumumanSekolah::firstOrCreate(
            ['judul_pengumuman' => 'Pengumuman Ujian & Kegiatan Portal Superadmin'],
            [
                'id_penerbit' => $superadmin->id,
                'isi_pengumuman' => 'Seluruh siswa dan orang tua dapat memantau informasi akademik dan ibadah melalui portal terpadu.',
                'status_aktif' => true,
                'mulai_tampil' => now()->subDays(2),
                'selesai_tampil' => now()->addDays(30),
                'prioritas' => 1,
                'data_tambahan' => [
                    'tipe' => 'pengumuman',
                    'kategori' => 'Akademik',
                    'prioritas' => 'penting',
                ],
            ]
        );

        // 9. Seed Dummy Modul Ajar & Materi
        $kurikulum = MasterKurikulum::first() ?? MasterKurikulum::create([
            'kode_kurikulum' => 'KM-2024',
            'nama_kurikulum' => 'Kurikulum Merdeka 2024',
            'versi' => '2024.1',
            'status' => true,
        ]);

        $guru = Employee::first() ?? Employee::create([
            'niy' => '199001012022011001',
            'nama_lengkap' => 'Ustadz Ahmad Al-Farisi, S.Pd.I',
            'email' => 'ahmad.farisi@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);

        $modulAjar = LmsModulAjar::firstOrCreate(
            ['kode_modul' => 'MA-MTK-6A-01'],
            [
                'kurikulum_id' => $kurikulum->id,
                'guru_id' => $guru->id,
                'judul_modul' => 'Modul Utama Aljabar & Logika Matematika',
                'tahun_ajaran_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'mata_pelajaran_id' => $subject->id,
                'kelas_id' => $kelasId,
                'fase' => 'Fase C',
                'semester' => 'Ganjil',
                'status' => 'published',
            ]
        );

        LmsMateri::firstOrCreate(
            ['judul' => 'Modul 1: Aljabar dan Logika Dasar'],
            [
                'modul_ajar_id' => $modulAjar->id,
                'mata_pelajaran_id' => $subject->id,
                'guru_id' => $guru->id,
                'konten' => 'Pembahasan lengkap aljabar dan himpunan matematika terpadu.',
                'tipe_materi' => 'Teks & PDF',
                'status' => 'published',
            ]
        );

        // 10. Seed Dummy Penugasan & Pengumpulan Tugas
        $penugasan = LmsPenugasan::firstOrCreate(
            ['judul_tugas' => 'Tugas 1: Latihan Soal Aljabar'],
            [
                'tahun_ajaran_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'kelas_id' => $kelasId,
                'mata_pelajaran_id' => $subject->id,
                'guru_id' => $guru->id,
                'deskripsi' => 'Kerjakan latihan soal halaman 45 buku paket.',
                'deadline' => now()->addDays(5),
                'is_published' => true,
            ]
        );

        LmsPengumpulanTugas::updateOrCreate(
            ['penugasan_id' => $penugasan->id, 'siswa_id' => $student->id],
            [
                'jawaban_teks' => 'Jawaban latihan soal aljabar telah selesai dikerjakan.',
                'status' => 'dikumpulkan',
                'waktu_kumpul' => now(),
                'nilai_guru' => 95,
                'catatan_guru' => 'Sangat baik, pertahankan prestasimu!',
            ]
        );

        // 11. Seed Dummy Tahfizh Log
        TahfizhDailyLog::firstOrCreate(
            ['student_id' => $student->id, 'record_date' => now()->toDateString()],
            [
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'class_id' => $kelasId,
                'record_date' => now()->toDateString(),
                'hafalan_surah_name' => 'An-Naba',
                'hafalan_ayah_start' => 1,
                'hafalan_ayah_end' => 40,
                'status' => 'Lancar',
                'notes_teacher' => 'Hafalan sangat lancar dan tajwid tepat.',
            ]
        );

        // 12. Seed Dummy Student Grade
        StudentGrade::updateOrCreate(
            ['student_id' => $student->id, 'subject_id' => $subject->id],
            [
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'kelas_id' => $kelasId,
                'score_assignment' => 95.0,
                'score_midterm' => 90.0,
                'score_final' => 91.0,
                'final_score' => 92.0,
                'grade_letter' => 'A',
                'is_passed' => true,
                'notes' => 'Tuntas dengan predikat Istimewa',
            ]
        );

        // 13. Seed Dummy Catatan Guru
        $teacherModel = Teacher::first() ?? Teacher::create([
            'employee_id' => $guru->id,
            'is_active' => true,
        ]);

        StudentNote::firstOrCreate(
            ['student_id' => $student->id],
            [
                'teacher_id' => $teacherModel->id,
                'note' => 'Siswa menunjukkan kedisiplinan dan keaktifan luar biasa di kelas.',
                'metadata' => json_encode([
                    'category' => 'Akademik',
                    'title' => 'Catatan Perkembangan Positif',
                    'visible_to_parent' => true,
                    'visible_to_student' => true,
                ]),
            ]
        );

        // 14. Seed Dummy Mutabaah Header & Details
        $unitId = DB::table('education_units')->value('id');
        if (! $unitId) {
            $unitId = (string) Str::uuid();
            DB::table('education_units')->insert([
                'id' => $unitId,
                'name' => 'SD Islam Terpadu Superadmin',
                'level' => 'SD',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $mutabaahTemplate = MutabaahTemplate::first() ?? MutabaahTemplate::create([
            'code' => 'TPL-SD-DEFAULT',
            'name' => 'Template SD Default',
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        $supervisorAssignmentId = DB::table('mutabaah_supervisor_assignments')->value('id');
        if (! $supervisorAssignmentId) {
            $supervisorAssignmentId = (string) Str::uuid();
            DB::table('mutabaah_supervisor_assignments')->insert([
                'id' => $supervisorAssignmentId,
                'employee_id' => $guru->id,
                'supervisor_type' => 'wali_kelas',
                'education_unit_id' => $unitId,
                // `kelas_id` adalah FK legacy ke `classes`; rombel aktif
                // disimpan pada `rombel_id` yang menunjuk `tbl_kelas`.
                'kelas_id' => null,
                'rombel_id' => $kelasId,
                'template_id' => $mutabaahTemplate->id,
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'start_date' => '2025-07-01',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $mutabaah = MutabaahDailyHeader::query()
            ->where('student_id', $student->id)
            ->where('template_id', $mutabaahTemplate->id)
            ->first();

        if (! $mutabaah) {
            $mutabaah = MutabaahDailyHeader::create([
                'student_id' => $student->id,
                'activity_date' => now()->startOfDay(),
                'template_id' => $mutabaahTemplate->id,
                'supervisor_assignment_id' => $supervisorAssignmentId,
                'education_unit_id' => $unitId,
                'kelas_id' => null,
                'rombel_id' => $kelasId,
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'status' => DailyStatus::Finalized,
                'score' => 95.0,
            ]);
        }

        // 15. Seed Dummy Presensi & Permission
        LmsPresensi::firstOrCreate(
            ['siswa_id' => $student->id, 'tanggal' => now()->toDateString()],
            [
                'jadwal_pelajaran_id' => $schedule->id,
                'status_hadir' => 'Hadir',
            ]
        );

        StudentAttendancePermission::firstOrCreate(
            ['student_id' => $student->id, 'reason' => 'Izin keperluan keluarga mendesak'],
            [
                'class_id' => $kelasId,
                'start_date' => now()->subDays(5)->toDateString(),
                'end_date' => now()->subDays(4)->toDateString(),
                'type' => 'Izin',
                'status' => 'approved',
                'submitted_at' => now()->subDays(5),
            ]
        );

        // 16. Seed Dummy Kisi-Kisi
        $kisiKisi = LmsKisiKisi::firstOrCreate(
            ['judul_kisi' => 'Kisi-Kisi Ujian Akhir Semester Matematika'],
            [
                'kurikulum_id' => $kurikulum->id,
                'tahun_ajaran_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'kelas_id' => $kelasId,
                'mata_pelajaran_id' => $subject->id,
                'guru_id' => $guru->id,
                'jenis_ujian' => 'PAS',
                'jumlah_soal' => 25,
                'alokasi_waktu_menit' => 90,
                'kompetensi_dasar' => 'Memahami persamaan aljabar dan pengukuran geometri.',
                'level_kognitif' => 'L2 - L3',
                'status' => true,
            ]
        );

        // 17. Seed Dummy CBT Exam & Sesi Ujian Selesai
        $ujian = LmsUjian::firstOrCreate(
            ['judul_ujian' => 'Ujian Akhir Semester (CBT) Matematika'],
            [
                'kisi_kisi_id' => $kisiKisi->id,
                'kelas_id' => $kelasId,
                'semester_id' => $semester->id,
                'guru_id' => $guru->id,
                'durasi_menit' => 90,
                'nilai_kkm' => 75,
                'max_attempt' => 3,
                'waktu_mulai' => now()->subDays(1),
                'waktu_selesai' => now()->addDays(5),
                'status' => 'published',
                'tampilkan_nilai_langsung' => true,
            ]
        );

        LmsUjianSesi::updateOrCreate(
            ['ujian_id' => $ujian->id, 'siswa_id' => $student->id],
            [
                'status' => 'selesai',
                'nilai_final' => 88.5,
                'jumlah_benar' => 22,
                'jumlah_salah' => 3,
                'jumlah_kosong' => 0,
                'waktu_mulai' => now()->subHours(2),
                'waktu_selesai' => now()->subHours(1),
            ]
        );

        // 18. Seed Dummy Rapor
        LmsRapor::firstOrCreate(
            ['siswa_id' => $student->id, 'semester_id' => $semester->id],
            [
                'kelas_id' => $kelasId,
                'tahun_ajaran_id' => $academicYear->id,
                'status_rapor' => 'published',
                'rata_rata' => 91.5,
                'catatan_wali_kelas' => 'Siswa berprestasi tinggi dan beradab mulia.',
                'tanggal_terbit' => now()->subDays(10)->toDateString(),
            ]
        );
    }
}
