<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ModulSemester;
use App\Models\ModulSemesterDetail;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class ModulSemesterSeeder extends Seeder
{
    public function run(): void
    {
        $ta = AcademicYear::first();
        $sem = Semester::first();
        $unit = EducationUnit::first();
        $kelas = Kelas::first();
        $subject = Subject::first();
        $guru = Employee::first();

        if (! $ta || ! $sem || ! $kelas || ! $subject || ! $guru) {
            return;
        }

        $dataModul = [
            'tahun_ajaran_id' => $ta->id,
            'semester_id' => $sem->id,
            'unit_pendidikan_id' => $unit?->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $subject->id,
            'guru_id' => $guru->id,
            'nama_modul' => 'Modul Semester 1 Matematika Terpadu Kelas VII',
            'jenjang' => 'SMP',
            'kurikulum' => 'Kurikulum Merdeka',
            'status' => 'Aktif',
            'atp' => 'Peserta didik dapat memahami konsep persamaan linear, aljabar, dan penerapan dalam kehidupan sehari-hari.',
            'cp' => 'Peserta didik mampu menyelesaikan masalah aljabar serta melakukan estimasi besaran kuantitatif.',
            'tujuan_pembelajaran' => 'Memahami variabel, konstanta, operasi aljabar, dan mampu menyelesaikan soal cerita aljabar.',
            'alokasi_jam' => 36,
            'jumlah_pertemuan' => 18,
            'metode_pembelajaran' => 'Problem Based Learning, Ceramah & Diskusi, Praktikum',
            'model_pembelajaran' => 'Problem Based Learning (PBL)',
            'media_pembelajaran' => 'Modul Cetak, Slide PPT, Geogebra App, Video Pembelajaran',
            'sumber_belajar' => 'Buku Matematika Terpadu JSIT, Lembar Kerja Siswa',
            'target_nilai_minimum' => 75.00,
            'target_kehadiran' => 90.00,
            'target_hafalan' => 'Rumus-rumus Aljabar & Geometri Dasar',
            'target_proyek' => 'Proyek Estimasi Biaya & Desain Arsitektur Sederhana',
            'berlaku_mulai' => '2026-07-01',
            'berlaku_sampai' => '2026-12-31',
            'ditampilkan_di_portal_ortu' => true,
            'ditampilkan_di_aplikasi_siswa' => true,
            'arsip_otomatis' => false,
            'bobot_tugas' => 20.00,
            'bobot_quiz' => 15.00,
            'bobot_projek' => 25.00,
            'bobot_uts' => 20.00,
            'bobot_uas' => 20.00,
        ];

        $modul1 = ModulSemester::withTrashed()->where('kode_modul', 'MDS-20261-SDIT-7A-MTK01')->first();
        if ($modul1) {
            if ($modul1->trashed()) {
                $modul1->restore();
            }
            $modul1->update($dataModul);
        } else {
            $modul1 = ModulSemester::create(array_merge(['kode_modul' => 'MDS-20261-SDIT-7A-MTK01'], $dataModul));
        }

        $materiList = [
            ['minggu' => 1, 'materi' => 'Pengenalan Konsep Aljabar dan Variabel', 'jp' => 2, 'ket' => 'Penjelasan dasar dan latihan'],
            ['minggu' => 2, 'materi' => 'Operasi Penjumlahan & Pengurangan Aljabar', 'jp' => 2, 'ket' => 'Latihan kelompok'],
            ['minggu' => 3, 'materi' => 'Operasi Perkalian & Pembagian Aljabar', 'jp' => 2, 'ket' => 'Kuis kecil'],
            ['minggu' => 4, 'materi' => 'Persamaan Linear Satu Variabel (PLSV)', 'jp' => 2, 'ket' => 'Diskusi PBL'],
            ['minggu' => 5, 'materi' => 'Pertidaksamaan Linear Satu Variabel (PtLSV)', 'jp' => 2, 'ket' => 'Studi kasus'],
        ];

        foreach ($materiList as $m) {
            ModulSemesterDetail::updateOrCreate(
                [
                    'modul_semester_id' => $modul1->id,
                    'minggu' => $m['minggu'],
                ],
                [
                    'materi' => $m['materi'],
                    'atp' => $modul1->atp,
                    'cp' => $modul1->cp,
                    'jp' => $m['jp'],
                    'keterangan' => $m['ket'],
                ]
            );
        }
    }
}
