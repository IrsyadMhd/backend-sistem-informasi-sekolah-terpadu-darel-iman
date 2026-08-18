<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsKisiKisi;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Seeder;

class LmsUjianSeeder extends Seeder
{
    public function run(): void
    {
        $kisi = LmsKisiKisi::first();
        $kelas = Kelas::first();
        $semester = Semester::first();
        $guru = Employee::first();
        $siswa = Student::first();

        if (! $kisi || ! $kelas || ! $semester) {
            return;
        }

        $ujian = LmsUjian::updateOrCreate(
            ['judul_ujian' => 'Ujian Harian CBT — Pendidikan Pancasila Kelas X'],
            [
                'kisi_kisi_id' => $kisi->id,
                'kelas_id' => $kelas->id,
                'semester_id' => $semester->id,
                'guru_id' => $guru?->id,
                'instruksi' => 'Kerjakan seluruh butir soal dengan jujur dan teliti. Waktu akan berjalan otomatis saat tombol Mulai Ujian diklik.',
                'waktu_mulai' => now()->subHours(2),
                'waktu_selesai' => now()->addDays(2),
                'durasi_menit' => 45,
                'acak_soal' => true,
                'acak_jawaban' => true,
                'tampilkan_nilai_langsung' => true,
                'nilai_kkm' => 75.0,
                'max_attempt' => 1,
                'status' => 'berlangsung',
            ]
        );

        if ($siswa) {
            LmsUjianSesi::updateOrCreate(
                [
                    'ujian_id' => $ujian->id,
                    'siswa_id' => $siswa->id,
                ],
                [
                    'waktu_mulai' => now()->subMinutes(30),
                    'waktu_selesai' => now()->subMinutes(5),
                    'durasi_aktual_detik' => 1500,
                    'jumlah_benar' => 4,
                    'jumlah_salah' => 1,
                    'jumlah_kosong' => 0,
                    'nilai_raw' => 19.0,
                    'nilai_final' => 85.0,
                    'status' => 'selesai',
                    'ip_address' => '127.0.0.1',
                ]
            );
        }
    }
}
