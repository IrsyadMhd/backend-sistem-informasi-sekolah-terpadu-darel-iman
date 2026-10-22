<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
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
        $kisi = LmsKisiKisi::query()->orderBy('id')->first();
        $kelas = Kelas::query()->orderBy('id')->first();
        $academicYear = AcademicYear::query()->where('is_active', true)->orderByDesc('start_date')->first()
            ?? AcademicYear::query()->orderByDesc('start_date')->first();
        $semester = $academicYear
            ? Semester::query()
                ->where('academic_year_id', $academicYear->id)
                ->orderByDesc('is_active')
                ->orderBy('sequence')
                ->first()
            : null;
        $guru = Employee::query()->orderBy('id')->first();
        $siswa = Student::query()->orderBy('id')->first();

        if (! $kisi || ! $kelas || ! $semester) {
            return;
        }

        $kelases = Kelas::query()->orderBy('id')->get();
        if ($kelases->isEmpty()) {
            $kelases = collect([$kelas]);
        }

        $kurikulumId = \Illuminate\Support\Facades\DB::table('master_kurikulum')->value('id');

        foreach ($kelases as $kls) {
            $unitId = $kls->unit_pendidikan_id;
            
            // Subjek PAI & Pancasila dinamis sesuai unit rombel
            $paiSubj = \App\Models\Subject::where('unit_pendidikan_id', $unitId)
                ->where(fn ($q) => $q->where('name', 'Pendidikan Agama Islam (PAI)')->orWhere('name', 'like', '%Agama Islam%'))
                ->first() ?? \App\Models\Subject::where('name', 'like', '%Agama Islam%')->first();

            $pancasilaSubj = \App\Models\Subject::where('unit_pendidikan_id', $unitId)
                ->where(fn ($q) => $q->where('name', 'Pendidikan Pancasila')->orWhere('name', 'like', '%Pancasila%'))
                ->first() ?? \App\Models\Subject::where('name', 'like', '%Pancasila%')->first();

            $kisiPai = $paiSubj ? LmsKisiKisi::firstOrCreate([
                'mata_pelajaran_id' => $paiSubj->id,
                'kelas_id' => $kls->id,
            ], [
                'kurikulum_id' => $kurikulumId,
                'semester_id' => $semester->id,
                'tahun_ajaran_id' => $academicYear->id,
                'guru_id' => $guru?->id,
                'judul_kisi' => 'Kisi-kisi ' . $paiSubj->name . ' — ' . $kls->nama_kelas,
                'jenis_ujian' => 'UH',
                'jumlah_soal' => 25,
                'alokasi_waktu_menit' => 45,
                'status' => true,
            ]) : $kisi;

            $kisiPancasila = $pancasilaSubj ? LmsKisiKisi::firstOrCreate([
                'mata_pelajaran_id' => $pancasilaSubj->id,
                'kelas_id' => $kls->id,
            ], [
                'kurikulum_id' => $kurikulumId,
                'semester_id' => $semester->id,
                'tahun_ajaran_id' => $academicYear->id,
                'guru_id' => $guru?->id,
                'judul_kisi' => 'Kisi-kisi ' . $pancasilaSubj->name . ' — ' . $kls->nama_kelas,
                'jenis_ujian' => 'UH',
                'jumlah_soal' => 25,
                'alokasi_waktu_menit' => 45,
                'status' => true,
            ]) : $kisi;

            $ujianPai = LmsUjian::updateOrCreate(
                [
                    'judul_ujian' => 'Ujian Harian CBT — Pendidikan Agama Islam (PAI) '.$kls->nama_kelas,
                    'kelas_id' => $kls->id,
                ],
                [
                    'kisi_kisi_id' => $kisiPai->id,
                    'kelas_id' => $kls->id,
                    'semester_id' => $semester->id,
                    'guru_id' => $guru?->id,
                    'instruksi' => 'Kerjakan seluruh butir soal Pendidikan Agama Islam (PAI) dengan jujur dan teliti.',
                    'waktu_mulai' => now()->startOfDay(),
                    'waktu_selesai' => '2026-12-31 23:59:59',
                    'durasi_menit' => 45,
                    'acak_soal' => true,
                    'acak_jawaban' => true,
                    'tampilkan_nilai_langsung' => true,
                    'nilai_kkm' => 75.0,
                    'max_attempt' => 3,
                    'status' => 'berlangsung',
                ]
            );

            $ujianPancasila = LmsUjian::updateOrCreate(
                [
                    'judul_ujian' => 'Ujian Harian CBT — Pendidikan Pancasila '.$kls->nama_kelas,
                    'kelas_id' => $kls->id,
                ],
                [
                    'kisi_kisi_id' => $kisiPancasila->id,
                    'kelas_id' => $kls->id,
                    'semester_id' => $semester->id,
                    'guru_id' => $guru?->id,
                    'instruksi' => 'Kerjakan seluruh butir soal Pendidikan Pancasila dengan jujur dan teliti.',
                    'waktu_mulai' => now()->startOfDay(),
                    'waktu_selesai' => '2026-12-31 23:59:59',
                    'durasi_menit' => 45,
                    'acak_soal' => true,
                    'acak_jawaban' => true,
                    'tampilkan_nilai_langsung' => true,
                    'nilai_kkm' => 75.0,
                    'max_attempt' => 3,
                    'status' => 'berlangsung',
                ]
            );
        }

        if ($siswa) {
            LmsUjianSesi::updateOrCreate(
                [
                    'ujian_id' => $ujianPai->id,
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
