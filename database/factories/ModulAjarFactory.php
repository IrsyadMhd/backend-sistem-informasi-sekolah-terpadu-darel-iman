<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsModulAjar;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TujuanPembelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ModulAjarFactory extends Factory
{
    protected $model = LmsModulAjar::class;

    public function definition(): array
    {
        $unit = EducationUnit::inRandomOrder()->first();
        $tahun = AcademicYear::inRandomOrder()->first();
        $semester = Semester::inRandomOrder()->first();
        $kurikulum = MasterKurikulum::inRandomOrder()->first();
        $subject = Subject::inRandomOrder()->first();
        $guru = Employee::inRandomOrder()->first();
        $kelas = Kelas::inRandomOrder()->first();
        $cp = CapaianPembelajaran::inRandomOrder()->first();
        $tp = TujuanPembelajaran::inRandomOrder()->first();

        return [
            'unit_pendidikan_id' => $unit?->id,
            'tahun_ajaran_id' => $tahun?->id ?? (string) Str::uuid(),
            'semester_id' => $semester?->id ?? (string) Str::uuid(),
            'kurikulum_id' => $kurikulum?->id ?? (string) Str::uuid(),
            'mata_pelajaran_id' => $subject?->id ?? (string) Str::uuid(),
            'guru_id' => $guru?->id ?? (string) Str::uuid(),
            'kelas_id' => $kelas?->id ?? (string) Str::uuid(),
            'rombel_id' => $kelas?->id,
            'cp_id' => $cp?->id,
            'tp_id' => $tp?->id,
            'kode_modul' => 'MA-'.$this->faker->unique()->numberBetween(100, 999),
            'judul_modul' => 'Modul Pembelajaran '.$this->faker->words(3, true),
            'fase' => $this->faker->randomElement(['Fase A', 'Fase B', 'Fase C', 'Fase D', 'Fase E', 'Fase F']),
            'semester' => $this->faker->randomElement(['Ganjil', 'Genap']),
            'alokasi_waktu_jp' => $this->faker->numberBetween(2, 8),
            'tujuan_pembelajaran' => $this->faker->sentence(10),
            'profil_pelajar_pancasila' => 'Beriman dan Bertaqwa kepada Tuhan YME, Mandiri, Bernalar Kritis, Gotong Royong',
            'target_peserta_didik' => 'Peserta Didik Reguler/Tipikal (28-32 siswa)',
            'model_pembelajaran' => $this->faker->randomElement(['Problem Based Learning', 'Project Based Learning', 'Discovery Learning', 'Inquiry Learning']),
            'metode_pembelajaran' => 'Diskusi, Ceramah Interaktif, Presentasi Kelompok, Demonstrasi Praktik',
            'media_pembelajaran' => 'Slide PPT, Modul Cetak, Video Pembelajaran YouTube, Quizizz, LKPD',
            'sumber_belajar' => 'Buku Cetak Kemendikbudristek 2024, Perpustakaan Sekolah, Portal LMS',
            'kegiatan_pendahuluan' => '1. Guru membuka pembelajaran dengan salam dan doa bersama.\n2. Mengabsen siswa dan menyampaikan apersepsi.\n3. Menyampaikan tujuan pembelajaran harian.',
            'kegiatan_inti' => '1. Siswa mengamati tayangan slide/video apersepsi.\n2. Diskusi kelompok mengidentifikasi permasalahan dasar.\n3. Menyusun draft laporan dan presentasi kelompok.\n4. Tanya jawab antar kelompok dipandu oleh Guru.',
            'kegiatan_penutup' => '1. Guru bersama siswa melakukan kesimpulan bersama.\n2. Melakukan refleksi dan tes formatif singkat.\n3. Menutup dengan doa dan salam.',
            'asesmen_awal' => 'Kuis diagnosis berupa 5 pertanyaan pilihan ganda awal.',
            'asesmen_proses' => 'Rubrik observasi keaktifan kelompok dan lembar penilaian antar teman.',
            'asesmen_akhir' => 'Penilaian produk LKPD dan tes tertulis akhir sub-bab.',
            'rencana_penilaian' => 'Pengetahuan 40%, Keterampilan 40%, Sikap 20%',
            'refleksi_guru' => '85% siswa mencapai KKM pada pertemuan pertama. Perlu remedial untuk 4 siswa.',
            'lampiran' => [
                ['nama' => 'LKPD Pertemuan 1.pdf', 'url' => '/storage/lampiran/lkpd1.pdf'],
                ['nama' => 'Rubrik Penilaian Presentasi.pdf', 'url' => '/storage/lampiran/rubrik.pdf'],
            ],
            'status' => $this->faker->randomElement(['Draft', 'Review', 'Publish', 'Arsip']),
            'deskripsi' => 'Modul Ajar Kurikulum Merdeka terpadu untuk pembelajaran efektif.',
            'versi' => '1.0',
        ];
    }
}
