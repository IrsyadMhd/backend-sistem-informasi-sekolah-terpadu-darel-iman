<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Subject;
use App\Models\SupervisiKbm;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupervisiKbmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('supervisi_kbms')) {
            $this->command?->warn('Tabel supervisi_kbms belum ada.');
            return;
        }

        $penginput = User::where('email', 'superadmin@dareliman.sch.id')->first()
            ?? User::where('is_active', true)->first()
            ?? User::first();

        // Ambil guru riil dari tabel employees
        $teachers = Employee::query()
            ->where(function ($q) {
                $q->whereHas('teacher')
                  ->orWhereHas('teachings')
                  ->orWhere('status_pegawai', 'like', '%Guru%')
                  ->orWhereHas('position', function ($p) {
                      $p->where('name', 'like', '%Guru%')
                        ->orWhere('name', 'like', '%Pendidik%')
                        ->orWhere('name', 'like', '%Pengajar%');
                  });
            })
            ->with(['unit', 'position'])
            ->limit(15)
            ->get();

        if ($teachers->isEmpty()) {
            $teachers = Employee::with(['unit', 'position'])->limit(15)->get();
        }

        if ($teachers->isEmpty()) {
            $this->command?->warn('SupervisiKbmSeeder: Tidak ada data pegawai di database.');
            return;
        }

        $sampleMapel = [
            'Pendidikan Agama Islam & Budi Pekerti',
            'Matematika Terpadu',
            'Bahasa Arab Fusha',
            'Ilmu Pengetahuan Alam (Sains Islam)',
            'Bahasa Indonesia',
            'Tahfizh & Tajwid Al-Qur\'an',
            'Aqidah & Akhlak',
            'Bahasa Inggris',
        ];

        $supervisors = [
            'Tim Supervisi Divisi Pendidikan',
            'Pengawas Kurikulum Yayasan',
            'Divisi Penjaminan Mutu Akademik',
            'Koordinator Supervisi KBM',
        ];

        $rubrikTemplates = [
            [
                'status' => 'selesai',
                'rubrik' => ['pendahuluan' => 4, 'penguasaan_materi' => 4, 'pengelolaan_kelas' => 4, 'integrasi_islam' => 4, 'media_pembelajaran' => 4, 'penutup_asesmen' => 3],
                'apresiasi' => 'Penguasaan materi sangat mendalam. Pengintegrasian nilai-nilai keislaman dan ayat kauniyah ke dalam konsep materi tersampaikan secara natural dan menggugah nalar kritis siswa.',
                'rekomendasi' => 'Berikan alokasi waktu 5-7 menit di akhir sesi untuk refleksi mandiri oleh perwakilan santri.',
            ],
            [
                'status' => 'selesai',
                'rubrik' => ['pendahuluan' => 4, 'penguasaan_materi' => 4, 'pengelolaan_kelas' => 3, 'integrasi_islam' => 4, 'media_pembelajaran' => 3, 'penutup_asesmen' => 4],
                'apresiasi' => 'Apersepsi dan pembukaan kelas diawali dengan doa dan tadabbur ayat relevan. Interaksi tanya jawab hidup dan merata di seluruh barisan kelas.',
                'rekomendasi' => 'Tingkatkan variasi pemanfaatan media visual/slide interaktif pada penugasan kelompok.',
            ],
            [
                'status' => 'selesai',
                'rubrik' => ['pendahuluan' => 3, 'penguasaan_materi' => 4, 'pengelolaan_kelas' => 4, 'integrasi_islam' => 3, 'media_pembelajaran' => 4, 'penutup_asesmen' => 4],
                'apresiasi' => 'Manajemen kelas sangat kondusif dan santun. Siswa aktif berkolaborasi memecahkan lembar kerja terstruktur.',
                'rekomendasi' => 'Perkuat penyampaian dalil/hikmah keislaman pada fase penarikan kesimpulan akhir pembelajaran.',
            ],
            [
                'status' => 'pendampingan',
                'rubrik' => ['pendahuluan' => 3, 'penguasaan_materi' => 3, 'pengelolaan_kelas' => 2, 'integrasi_islam' => 3, 'media_pembelajaran' => 2, 'penutup_asesmen' => 2],
                'apresiasi' => 'Pendidik bersemangat dan ramah dalam membimbing murid di bangku depan.',
                'rekomendasi' => 'Perlu pendampingan berkala tim kurikulum unit dalam penyusunan rubrik asesmen formatif dan pengondisian konsentrasi siswa di barisan belakang.',
            ],
            [
                'status' => 'terjadwal',
                'rubrik' => ['pendahuluan' => 0, 'penguasaan_materi' => 0, 'pengelolaan_kelas' => 0, 'integrasi_islam' => 0, 'media_pembelajaran' => 0, 'penutup_asesmen' => 0],
                'apresiasi' => null,
                'rekomendasi' => 'Agenda visitasi kelas pekan ini. Konfirmasi modul ajar dan RPP telah diverifikasi kepala unit.',
            ],
        ];

        DB::transaction(function () use ($teachers, $sampleMapel, $supervisors, $rubrikTemplates, $penginput) {
            foreach ($teachers as $idx => $teacher) {
                $unit = $teacher->unit ?? EducationUnit::first();
                if (! $unit) continue;

                // Cari kelas riil di unit tersebut
                $kelasName = 'Kelas ' . ($idx % 6 + 1) . ' Thoriq';
                if (Schema::hasTable('tbl_kelas')) {
                    $dbKelas = DB::table('tbl_kelas')->where('unit_pendidikan_id', $unit->id)->inRandomOrder()->first();
                    if ($dbKelas) {
                        $kelasName = $dbKelas->nama_kelas ?? $dbKelas->kode_kelas ?? $kelasName;
                    }
                }

                $template = $rubrikTemplates[$idx % count($rubrikTemplates)];
                $mapel = $sampleMapel[$idx % count($sampleMapel)];
                $supervisor = $supervisors[$idx % count($supervisors)];

                $daysAgo = ($idx * 3) - 2; // sebar beberapa hari lalu hingga mendatang
                $tanggal = Carbon::now()->subDays($daysAgo)->format('Y-m-d');

                $rubrik = $template['rubrik'];
                $skorTotal = 0;
                if ($template['status'] === 'selesai' || $template['status'] === 'pendampingan') {
                    $sum = array_sum($rubrik);
                    $skorTotal = (int) round(($sum / 24) * 100);
                }

                SupervisiKbm::updateOrCreate(
                    [
                        'guru_id' => $teacher->id,
                        'unit_id' => $unit->id,
                        'mapel' => $mapel,
                    ],
                    [
                        'kelas' => $kelasName,
                        'tanggal' => $tanggal,
                        'supervisor' => $supervisor,
                        'status' => $template['status'],
                        'skor_total' => $skorTotal,
                        'rubrik' => $rubrik,
                        'apresiasi' => $template['apresiasi'],
                        'rekomendasi' => $template['rekomendasi'],
                        'id_penginput' => $penginput?->id,
                    ]
                );
            }
        });

        $count = SupervisiKbm::count();
        $this->command?->info("SupervisiKbmSeeder berhasil: {$count} data supervisi KBM berbasis database riil tersimpan.");
    }
}
