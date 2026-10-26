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
            $unitGuru = ($unitId ? Employee::where('unit_id', $unitId)->first() : null) ?? $guru;
            
            // Subjek PAI & Pancasila dinamis sesuai unit rombel (TIDAK BOLEH lintas unit)
            $paiSubj = \App\Models\Subject::where('unit_pendidikan_id', $unitId)
                ->where(fn ($q) => $q->where('name', 'Pendidikan Agama Islam (PAI)')->orWhere('name', 'like', '%Agama Islam%'))
                ->first() ?? \App\Models\Subject::where('unit_pendidikan_id', $unitId)->first();

            $pancasilaSubj = \App\Models\Subject::where('unit_pendidikan_id', $unitId)
                ->where(fn ($q) => $q->where('name', 'Pendidikan Pancasila')->orWhere('name', 'like', '%Pancasila%'))
                ->first();

            if (! $pancasilaSubj && $unitId) {
                $pancasilaSubj = \App\Models\Subject::firstOrCreate([
                    'unit_pendidikan_id' => $unitId,
                    'name' => 'Pendidikan Pancasila',
                ], [
                    'code' => 'MAPEL-PANCASILA-' . substr($unitId, 0, 8),
                    'kurikulum_id' => $kurikulumId,
                    'nama_mapel' => 'Pendidikan Pancasila',
                    'kelompok_mapel' => 'Kelompok A',
                    'kategori' => 'Wajib',
                    'status' => true,
                ]);
            }

            $kisiPai = $paiSubj ? LmsKisiKisi::firstOrCreate([
                'mata_pelajaran_id' => $paiSubj->id,
                'kelas_id' => $kls->id,
            ], [
                'kurikulum_id' => $kurikulumId,
                'semester_id' => $semester->id,
                'tahun_ajaran_id' => $academicYear->id,
                'guru_id' => $unitGuru?->id,
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
                'guru_id' => $unitGuru?->id,
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
                    'guru_id' => $unitGuru?->id,
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
                    'guru_id' => $unitGuru?->id,
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

            // Pastikan kisi-kisi memiliki butir soal lengkap di Bank Soal (dinamis sesuai unit & mapel)
            foreach ([$kisiPai, $kisiPancasila] as $kisiTarget) {
                if ($kisiTarget && $kisiTarget->bankSoal()->count() < $kisiTarget->jumlah_soal) {
                    $needed = $kisiTarget->jumlah_soal - $kisiTarget->bankSoal()->count();
                    $subjName = $kisiTarget->subject?->name ?? 'Mata Pelajaran';
                    $batch = [];
                    for ($i = 1; $i <= $needed; $i++) {
                        $batch[] = [
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'kisi_kisi_id' => $kisiTarget->id,
                            'mata_pelajaran_id' => $kisiTarget->mata_pelajaran_id,
                            'kode_soal' => 'SOAL-' . strtoupper(\Illuminate\Support\Str::slug($kisiTarget->subject?->code ?? 'MAPEL')) . '-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                            'pertanyaan' => "Butir Soal #{$i}: Pemahaman materi {$subjName} pada {$kisiTarget->judul_kisi}.",
                            'tipe_soal' => $i <= 20 ? 'pg' : 'esai',
                            'opsi_a' => 'Pernyataan A (Jawaban yang tepat)',
                            'opsi_b' => 'Pernyataan B (Pengecoh)',
                            'opsi_c' => 'Pernyataan C (Pengecoh)',
                            'opsi_d' => 'Pernyataan D (Pengecoh)',
                            'opsi_e' => 'Pernyataan E (Pengecoh)',
                            'kunci_jawaban' => $i <= 20 ? 'A' : 'Rubrik jawaban esai uraian terstruktur',
                            'pembahasan' => "Pembahasan butir soal #{$i} terkait materi {$subjName}.",
                            'poin' => 4.0,
                            'tingkat_kesulitan' => $i % 3 === 0 ? 'sulit' : ($i % 2 === 0 ? 'sedang' : 'mudah'),
                            'status' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    \App\Models\LmsBankSoal::insert($batch);
                }
            }
        }

        if (isset($ujianPai)) {
            $siswa = Student::where('current_class_id', $ujianPai->kelas_id)->first();
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
}
