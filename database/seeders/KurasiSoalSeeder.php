<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\KurasiSoal;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KurasiSoalSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Get Divisi / Admin User as kurator
        $kurator = User::where('email', 'like', '%superadmin%')
            ->orWhere('email', 'like', '%admin%')
            ->first() ?? User::first();

        // 2. Query target units
        $units = EducationUnit::whereIn('code', [
            'SDIT-01',
            'SDIT-02',
            'SMPIT-01',
            'SMPIT-02',
            'SMAIT-01',
            'PONPES-PA',
            'TKIT-01',
            'MIT-01',
        ])->get()->keyBy('code');

        if ($units->isEmpty()) {
            $units = EducationUnit::all()->keyBy('code');
        }

        // 3. Query subjects
        $subjects = Subject::all();

        // Define realistic curation packages
        $curationTemplates = [
            // 1. SMPIT - Fiqih
            [
                'unit_code' => 'SMPIT-01',
                'mapel_keyword' => 'Agama',
                'fallback_mapel' => 'Fiqih Ibadah & Muamalah',
                'judul' => 'Paket Ujian Bersama Semester Ganjil - Fiqih Ibadah & Muamalah',
                'jenjang' => 'SMP / MTs',
                'klasifikasi' => 'HOTS',
                'tingkat_kesukaran' => 'Sedang-Tinggi (Analisis Kasus)',
                'status' => 'disetujui',
                'catatan_kurator' => 'Validasi kurikulum tuntas. Butir soal nomor 12 dan 18 menguji penalaran studi kasus fiqih shalat musafir dengan sangat baik.',
                'days_ago' => 2,
            ],
            // 2. SDIT - Matematika
            [
                'unit_code' => 'SDIT-01',
                'mapel_keyword' => 'Matematika',
                'fallback_mapel' => 'Matematika Terpadu',
                'judul' => 'Bank Soal Asesmen Standar - Matematika Penalaran Konseptual',
                'jenjang' => 'SD / MI',
                'klasifikasi' => 'HOTS',
                'tingkat_kesukaran' => 'Tinggi',
                'status' => 'perlu_revisi',
                'catatan_kurator' => 'Diagram soal nomor 7 buram, butuh penyegaran kunci jawaban nomor 15 yang memiliki opsi ganda ambigu.',
                'days_ago' => 4,
            ],
            // 3. SMAIT - Biologi
            [
                'unit_code' => 'SMAIT-01',
                'mapel_keyword' => 'Biologi',
                'fallback_mapel' => 'Biologi Terapan',
                'judul' => 'Kisi-Kisi & Naskah Soal PTS - Genetika & Bioteknologi Modern',
                'jenjang' => 'SMA / MA / Ponpes',
                'klasifikasi' => 'HOTS',
                'tingkat_kesukaran' => 'Sedang-Tinggi',
                'status' => 'disetujui',
                'catatan_kurator' => 'Struktur soal stimulus naratif berbasis riset ilmiah sangat bagus sesuai standar UTBK & Yayasan.',
                'days_ago' => 6,
            ],
            // 4. Ponpes - Bahasa Arab
            [
                'unit_code' => 'PONPES-PA',
                'mapel_keyword' => 'Tahfidz',
                'fallback_mapel' => 'Bahasa Arab & Nahwu Shorof',
                'judul' => 'Bank Soal Ujian Tahriri - Kaidah Nahwu Shorof & Pemahaman Teks',
                'jenjang' => 'SMA / MA / Ponpes',
                'klasifikasi' => 'MOTS',
                'tingkat_kesukaran' => 'Sedang',
                'status' => 'menunggu_reviu',
                'catatan_kurator' => 'Berkas naskah baru masuk dari tim asatidzah pondok, menunggu telaah oleh Kurator Bahasa Arab.',
                'days_ago' => 1,
            ],
            // 5. SMPIT - IPA Fisika
            [
                'unit_code' => 'SMPIT-01',
                'mapel_keyword' => 'Fisika',
                'fallback_mapel' => 'IPA Terpadu (Fisika & Alam)',
                'judul' => 'Paket Ujian Akhir Semester - Gerak, Energi & Gelombang Bunyi',
                'jenjang' => 'SMP / MTs',
                'klasifikasi' => 'HOTS',
                'tingkat_kesukaran' => 'Sedang-Tinggi',
                'status' => 'disetujui',
                'catatan_kurator' => 'Kunci jawaban dan rubrik penskoran esai telah diverifikasi akurat oleh Tim Kurikulum Divisi.',
                'days_ago' => 5,
            ],
            // 6. SDIT 2 - Bahasa Indonesia
            [
                'unit_code' => 'SDIT-02',
                'mapel_keyword' => 'Umum',
                'fallback_mapel' => 'Bahasa Indonesia Literasi',
                'judul' => 'Paket Literasi Membaca & Menulis - Tematik Kelas 5',
                'jenjang' => 'SD / MI',
                'klasifikasi' => 'MOTS',
                'tingkat_kesukaran' => 'Sedang',
                'status' => 'disetujui',
                'catatan_kurator' => 'Teks bacaan islami sangat menginspirasi adab santun peserta didik. Format kisi-kisi rapi.',
                'days_ago' => 7,
            ],
            // 7. MIT SaQu - Tahsin & Tajwid
            [
                'unit_code' => 'MIT-01',
                'mapel_keyword' => 'Tahsin',
                'fallback_mapel' => 'Tahsin & Kaidah Tajwid Juz 30',
                'judul' => 'Asesmen Teori Tajwid & Makhorijul Huruf Semester Ganjil',
                'jenjang' => 'SD / MI',
                'klasifikasi' => 'LOTS',
                'tingkat_kesukaran' => 'Mudah-Sedang',
                'status' => 'menunggu_reviu',
                'catatan_kurator' => 'Menunggu pencocokan butir soal nomor 1-20 dengan standar buku pegangan Tahsin Yayasan.',
                'days_ago' => 2,
            ],
            // 8. SMAIT - Kimia
            [
                'unit_code' => 'SMAIT-01',
                'mapel_keyword' => 'Kimia',
                'fallback_mapel' => 'Kimia Analitik',
                'judul' => 'Naskah Ujian Bersama - Stoikiometri & Struktur Atom',
                'jenjang' => 'SMA / MA / Ponpes',
                'klasifikasi' => 'HOTS',
                'tingkat_kesukaran' => 'Tinggi',
                'status' => 'perlu_revisi',
                'catatan_kurator' => 'Soal nomor 8 memerlukan tabel periodik terlampir agar siswa tidak menghafal nomor atom yang rumit.',
                'days_ago' => 3,
            ],
            // 9. SMPIT 2 - Sejarah Kebudayaan Islam
            [
                'unit_code' => 'SMPIT-02',
                'mapel_keyword' => 'Agama',
                'fallback_mapel' => 'Sejarah Kebudayaan Islam (SKI)',
                'judul' => 'Paket Ujian Bersama - Dakwah Khulafaur Rasyidin & Peradaban Islam',
                'jenjang' => 'SMP / MTs',
                'klasifikasi' => 'MOTS',
                'tingkat_kesukaran' => 'Sedang',
                'status' => 'disetujui',
                'catatan_kurator' => 'Kronologi sejarah terverifikasi dari rujukan shahih. Kisi-kisi lengkap 40 butir pilihan ganda.',
                'days_ago' => 8,
            ],
            // 10. TKIT - Pengenalan Angka & Huruf Hijaiyah
            [
                'unit_code' => 'TKIT-01',
                'mapel_keyword' => 'Agama',
                'fallback_mapel' => 'Pondasi Hijaiyah & Angka Terpadu',
                'judul' => 'Lembar Kerja Evaluasi Capaian Pembelajaran Anak Usia Dini',
                'jenjang' => 'TK / PAUD',
                'klasifikasi' => 'LOTS',
                'tingkat_kesukaran' => 'Mudah',
                'status' => 'disetujui',
                'catatan_kurator' => 'Ilustrasi gambar ramah anak dan instruksi berbasis motorik visual sangat tepat.',
                'days_ago' => 10,
            ],
        ];

        foreach ($curationTemplates as $idx => $tmpl) {
            $unit = $units->get($tmpl['unit_code']) ?? $units->first();
            if (! $unit) continue;

            // Find matching subject or pick closest
            $matchedSubject = $subjects->first(function ($s) use ($tmpl) {
                return str_contains(strtolower($s->name), strtolower($tmpl['mapel_keyword']));
            });

            // Find an employee in that unit
            $teacher = Employee::where('unit_id', $unit->id)->inRandomOrder()->first();

            $kodeKurasi = sprintf('KRS-2026-%03d', $idx + 1);

            KurasiSoal::updateOrCreate(
                ['kode_kurasi' => $kodeKurasi],
                [
                    'judul' => $tmpl['judul'],
                    'education_unit_id' => $unit->id,
                    'subject_id' => $matchedSubject?->id,
                    'penyusun_employee_id' => $teacher?->id,
                    'nama_penyusun' => $teacher?->nama_lengkap ?? 'Tim MGMP ' . $unit->name,
                    'nama_mapel' => $matchedSubject?->name ?? $tmpl['fallback_mapel'],
                    'jenjang' => $tmpl['jenjang'],
                    'klasifikasi' => $tmpl['klasifikasi'],
                    'tingkat_kesukaran' => $tmpl['tingkat_kesukaran'],
                    'status' => $tmpl['status'],
                    'tanggal_reviu' => Carbon::now()->subDays($tmpl['days_ago'])->toDateString(),
                    'kurator_user_id' => $kurator?->id,
                    'nama_kurator' => $kurator?->name ?? 'Divisi Pendidikan Yayasan',
                    'catatan_kurator' => $tmpl['catatan_kurator'],
                    'rubrik_kurasi' => [
                        'kesesuaian_kisi_kisi' => $tmpl['status'] === 'disetujui' ? 95 : 75,
                        'tingkat_kesukaran' => $tmpl['tingkat_kesukaran'],
                        'kejelasan_opsi' => $tmpl['status'] === 'perlu_revisi' ? 60 : 90,
                        'kunci_jawaban_valid' => $tmpl['status'] === 'perlu_revisi' ? false : true,
                    ],
                ]
            );
        }
    }
}
