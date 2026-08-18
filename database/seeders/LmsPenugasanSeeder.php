<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsModulAjar;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LmsPenugasanSeeder extends Seeder
{
    public function run(): void
    {
        $modulAjarList = LmsModulAjar::all();
        $subjects = Subject::orderBy('id')->get();
        $kelases = Kelas::orderBy('id')->get();
        $gurus = Employee::orderBy('id')->get();
        $students = Student::orderBy('id')->get();
        $semesters = Semester::orderBy('id')->get();
        $academicYears = AcademicYear::orderBy('id')->get();
        $adminUser = User::orderBy('id')->first();

        if ($modulAjarList->isEmpty()) {
            return;
        }

        $penugasans = [
            [
                'judul_tugas' => 'Penugasan 1: Analisis Hukum II Newton pada Benda Bergerak',
                'deskripsi' => 'Lakukan eksperimen sederhana pengukuran percepatan benda pada bidang miring. Catat data waktu dan jarak, lalu hitung percepatan serta gaya geseknya.',
                'instruksi' => '1. Kerjakan secara individu dalam format PDF.\n2. Sertakan foto alat dan grafik hubungan waktu vs jarak.\n3. Unggah sebelum deadline.',
                'tipe_tugas' => 'individu',
                'jenis_tugas' => 'tugas',
                'nilai_maksimal' => 100,
                'bobot_persen' => 15,
                'tanggal_mulai' => now()->subDays(5),
                'deadline' => now()->addDays(5),
                'izin_kumpul_terlambat' => true,
                'is_published' => true,
                'file_lampiran' => 'https://example.com/lampiran_hukum_newton.pdf',
            ],
            [
                'judul_tugas' => 'Proyek Kelompok: Rancang Bangun Maket Sel Tumbuhan & Hewan',
                'deskripsi' => 'Buatlah maket 3D organel sel tumbuhan dan sel hewan menggunakan bahan ramah lingkungan atau daur ulang.',
                'instruksi' => '1. Dibagi dalam kelompok berisi 4-5 siswa.\n2. Buat laporan singkat struktur dan fungsi setiap organel.\n3. Presentasikan maket di depan kelas.',
                'tipe_tugas' => 'kelompok',
                'jenis_tugas' => 'proyek',
                'nilai_maksimal' => 100,
                'bobot_persen' => 25,
                'tanggal_mulai' => now()->subDays(2),
                'deadline' => now()->addDays(12),
                'izin_kumpul_terlambat' => false,
                'is_published' => true,
                'file_lampiran' => 'https://example.com/rubrik_penilaian_proyek_sel.pdf',
            ],
            [
                'judul_tugas' => 'Latihan Soal Pemfaktoran Persamaan Kuadrat',
                'deskripsi' => 'Kerjakan 10 soal pemfaktoran persamaan kuadrat dari Buku Paket halaman 45 nomor 1 s.d 10.',
                'instruksi' => 'Tuliskan langkah-langkah penyelesaian secara rapi di buku latihan, lalu foto dan unggah dalam bentuk PDF/Gambar.',
                'tipe_tugas' => 'individu',
                'jenis_tugas' => 'latihan',
                'nilai_maksimal' => 100,
                'bobot_persen' => 10,
                'tanggal_mulai' => now()->subDays(1),
                'deadline' => now()->addDays(3),
                'izin_kumpul_terlambat' => true,
                'is_published' => false,
                'file_lampiran' => null,
            ],
        ];

        foreach ($penugasans as $index => $item) {
            $modul = $modulAjarList[$index % $modulAjarList->count()];
            $subject = $subjects->isNotEmpty() ? ($subjects->firstWhere('id', $modul->mata_pelajaran_id) ?? $subjects->first()) : null;
            $kelas = $kelases->isNotEmpty() ? ($kelases->firstWhere('id', $modul->kelas_id) ?? $kelases->first()) : null;
            $guru = $gurus->isNotEmpty() ? ($gurus->firstWhere('id', $modul->guru_id) ?? $gurus->first()) : null;
            $semester = $semesters->first();
            $tahunAjaran = $academicYears->first();

            if (! $subject || ! $kelas || ! $guru || ! $semester || ! $tahunAjaran) {
                continue;
            }

            $penugasan = LmsPenugasan::firstOrCreate(
                ['judul_tugas' => $item['judul_tugas']],
                [
                    'id' => Str::uuid()->toString(),
                    'modul_ajar_id' => $modul->id,
                    'mata_pelajaran_id' => $subject->id,
                    'kelas_id' => $kelas->id,
                    'guru_id' => $guru->id,
                    'semester_id' => $semester->id,
                    'tahun_ajaran_id' => $tahunAjaran->id,
                    'deskripsi' => $item['deskripsi'],
                    'instruksi' => $item['instruksi'],
                    'tipe_tugas' => $item['tipe_tugas'],
                    'jenis_tugas' => $item['jenis_tugas'],
                    'nilai_maksimal' => $item['nilai_maksimal'],
                    'bobot_persen' => $item['bobot_persen'],
                    'tanggal_mulai' => $item['tanggal_mulai'],
                    'deadline' => $item['deadline'],
                    'izin_kumpul_terlambat' => $item['izin_kumpul_terlambat'],
                    'is_published' => $item['is_published'],
                    'file_lampiran' => $item['file_lampiran'],
                    'created_by' => $adminUser ? $adminUser->id : null,
                ]
            );

            // Seed dummy submissions for students if available
            if ($students->isNotEmpty()) {
                $sampleStudents = $students->take(3);
                foreach ($sampleStudents as $sIdx => $student) {
                    LmsPengumpulanTugas::firstOrCreate(
                        [
                            'penugasan_id' => $penugasan->id,
                            'siswa_id' => $student->id,
                        ],
                        [
                            'id' => Str::uuid()->toString(),
                            'jawaban_teks' => 'Alhamdulillah berikut hasil pengerjaan tugas dari saya.',
                            'file_path' => 'https://example.com/jawaban_siswa_'.($sIdx + 1).'.pdf',
                            'url_link' => 'https://drive.google.com/sample_answer',
                            'status' => $sIdx === 0 ? 'dinilai' : 'dikumpulkan',
                            'waktu_kumpul' => now()->subDays(1),
                            'nilai_guru' => $sIdx === 0 ? 92.5 : null,
                            'catatan_guru' => $sIdx === 0 ? 'Pekerjaan sangat rapi dan komprehensif. Masya Allah!' : null,
                            'waktu_dinilai' => $sIdx === 0 ? now() : null,
                            'dinilai_oleh' => $sIdx === 0 && $guru ? $guru->id : null,
                            'created_by' => $adminUser ? $adminUser->id : null,
                        ]
                    );
                }
            }
        }
    }
}
