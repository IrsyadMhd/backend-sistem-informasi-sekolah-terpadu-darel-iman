<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Division;
use App\Models\Employee;
use App\Models\PemantauanDivisi;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PemantauanDivisiSeeder extends Seeder
{
    /**
     * Seed database pemantauan divisi secara dinamis dari data database yang ada.
     */
    public function run(): void
    {
        if (! Schema::hasTable('pemantauan_divisis')) {
            $this->command?->warn('Tabel pemantauan_divisis belum ada. Jalankan migration terlebih dahulu.');

            return;
        }

        // 1. Tarik user penginput secara dinamis dari database (Superadmin/Pimpinan/User pertama)
        $penginput = User::where('email', 'superadmin@simsit.sch.id')->first()
            ?? User::where('is_active', true)->first()
            ?? User::first();

        if (! $penginput) {
            $this->command?->warn('PemantauanDivisiSeeder: Tidak ditemukan user penginput di database.');

            return;
        }

        // 2. Tarik Tahun Ajaran & Semester aktif secara dinamis dari database
        $tahunAjaran = AcademicYear::where('is_active', true)->first()
            ?? AcademicYear::latest()->first();

        $semester = Semester::where('is_active', true)->first()
            ?? Semester::latest()->first();

        // 3. Tarik master divisi secara dinamis dari database
        $divisions = Division::where('is_active', true)->get();

        // Jika tabel master divisions belum terisi, ekstrak nama divisi/unit dari tabel positions atau education_units di DB
        if ($divisions->isEmpty()) {
            $extractedNames = [];

            if (Schema::hasTable('positions')) {
                $extractedNames = DB::table('positions')
                    ->whereNotNull('name')
                    ->where('name', '!=', '')
                    ->pluck('name')
                    ->toArray();
            }

            if (empty($extractedNames) && Schema::hasTable('education_units')) {
                $extractedNames = DB::table('education_units')
                    ->whereNotNull('name')
                    ->pluck('name')
                    ->toArray();
            }

            if (empty($extractedNames)) {
                $extractedNames = [
                    'Divisi Pendidikan',
                    'Divisi Kurikulum',
                    'Divisi Kesiswaan',
                    'Divisi Tahfizh',
                    'Divisi Bahasa',
                    'Tata Usaha',
                    'HRD & Kepegawaian',
                ];
            }

            // Masukkan divisi dinamis ke tabel master divisions di DB
            foreach (array_unique($extractedNames) as $index => $name) {
                $code = 'DIV-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 8)) . '-' . ($index + 1);
                Division::firstOrCreate(
                    ['name' => $name],
                    [
                        'code' => $code,
                        'description' => "Divisi & Unit Operasional {$name}",
                        'is_active' => true,
                        'created_by' => $penginput->id,
                    ]
                );
            }

            $divisions = Division::where('is_active', true)->get();
        }

        // 4. Generate records Pemantauan Divisi berbasis data divisi & pegawai di database
        DB::transaction(function () use ($divisions, $tahunAjaran, $semester, $penginput) {
            foreach ($divisions as $div) {
                // Cek data pegawai riil yang terhubung dengan divisi di database
                $employeeCount = Employee::where('division_id', $div->id)->count();
                $firstEmployeeName = Employee::where('division_id', $div->id)->value('nama_lengkap')
                    ?? Employee::inRandomOrder()->value('nama_lengkap');

                $aspekList = [
                    [
                        'aspek' => 'Evaluasi Program Kerja & Target Kinerja ' . $div->name,
                        'capaian' => 92.50,
                        'status' => 'tercapai',
                        'catatan' => 'Indikator kinerja utama divisi ' . $div->name . ' tercapai dengan baik sesuai target operasional.',
                    ],
                    [
                        'aspek' => 'Supervisi Kepatuhan SOP & Pelaporan ' . $div->name,
                        'capaian' => 78.00,
                        'status' => 'proses',
                        'catatan' => $firstEmployeeName
                            ? 'Pendampingan supervisi berjalan bersama ' . $firstEmployeeName . ' dan tim divisi.'
                            : 'Kelengkapan administrasi dan pelaporan SOP sedang dalam tahap pemantauan berkala.',
                    ],
                    [
                        'aspek' => 'Rapat Evaluasi Berkala & Koordinasi Tim',
                        'capaian' => 65.00,
                        'status' => 'proses',
                        'catatan' => 'Koordinasi berkala divisi ' . $div->name . ' telah diselenggarakan dengan beberapa catatan perbaikan.',
                    ],
                ];

                foreach ($aspekList as $idx => $aspek) {
                    $tanggal = now()->subDays(($idx * 3) + rand(1, 4))->format('Y-m-d');

                    PemantauanDivisi::updateOrCreate(
                        [
                            'nama_divisi' => $div->name,
                            'aspek_pemantauan' => $aspek['aspek'],
                            'tanggal_pemantauan' => $tanggal,
                        ],
                        [
                            'id_tahun_ajaran' => $tahunAjaran?->id,
                            'id_semester' => $semester?->id,
                            'persentase_capaian' => $aspek['capaian'],
                            'status_pemantauan' => $aspek['status'],
                            'catatan' => $aspek['catatan'],
                            'id_penginput' => $penginput->id,
                            'data_tambahan' => [
                                'division_id' => $div->id,
                                'jumlah_pegawai_divisi' => $employeeCount,
                                'sumber_data' => 'Dinamis dari Database',
                                'divisi_code' => $div->code,
                            ],
                        ]
                    );
                }
            }
        });
    }
}
