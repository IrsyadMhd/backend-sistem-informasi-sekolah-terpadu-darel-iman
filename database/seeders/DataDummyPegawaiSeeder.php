<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\EmployeeTeaching;
use App\Models\Position;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DataDummyPegawaiSeeder extends Seeder
{
    public function run(): void
    {
        $units = EducationUnit::query()->orderBy('code')->get();
        $positions = Position::all();

        $defaultUnitId = $units->first()?->id;
        $unitSdit = $units->firstWhere('level', 'SDIT')?->id ?? $defaultUnitId;
        $unitSmpit = $units->firstWhere('level', 'SMPIT')?->id ?? $defaultUnitId;
        $unitSmait = $units->firstWhere('level', 'SMAIT')?->id ?? $defaultUnitId;
        $unitTkit = $units->firstWhere('level', 'TKIT')?->id ?? $defaultUnitId;

        $posKepsek = $positions->firstWhere('code', 'JBT-003')?->id
            ?? $positions->firstWhere('level_jabatan', 3)?->id
            ?? $positions->firstWhere('name', 'Kepala Sekolah')?->id;
        $posWakepsek = $positions->firstWhere('code', 'JBT-004')?->id;
        $posGuruKelas = $positions->firstWhere('code', 'JBT-009')?->id ?? $positions->firstWhere('code', 'JBT-010')?->id;
        $posGuruMapel = $positions->firstWhere('code', 'JBT-009')?->id;
        $posTU = $positions->firstWhere('code', 'JBT-006')?->id;
        $posOperator = $positions->firstWhere('code', 'JBT-007')?->id;
        $posDivPendidikan = $positions->firstWhere('code', 'JBT-005')?->id;
        $posKetuaYayasan = $positions->firstWhere('code', 'JBT-001')?->id;

        $dummyEmployees = [
            [
                'niy' => 'NIY-201101001',
                'nik' => '1371011508750001',
                'nama_lengkap' => 'Muhammad Elvi Syam',
                'nama_panggilan' => 'Ust. Elvi',
                'gelar_depan' => 'Ust. Dr.',
                'gelar_belakang' => 'LC., M.A.',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Padang',
                'tanggal_lahir' => '1975-08-15',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $defaultUnitId,
                'jabatan_id' => $posKetuaYayasan,
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2011-01-01',
                'status' => 'Aktif',
                'no_hp' => '08116601001',
                'email' => 'elvisyam@dareliman.sch.id',
                'alamat' => 'Jl. Khatib Sulaiman No. 1, Lolong Belanti',
                'provinsi' => 'Sumatera Barat',
                'kota' => 'Padang',
                'kecamatan' => 'Padang Utara',
                'kelurahan' => 'Lelong Belanti',
                'kode_pos' => '25136',
                'metadata' => [
                    'position_history' => [
                        ['jabatan' => 'Ketua Yayasan Dar El-Iman', 'tgl_mulai' => '2011-01-01', 'keterangan' => 'Pendiri & Ketua Yayasan'],
                    ],
                    'certifications' => [
                        ['nama' => 'Doktor Syariah Islamiyyah', 'penerbit' => 'Universitas Islam Madinah', 'tahun' => '2010', 'no_sertifikat' => 'UIM-2010-9988'],
                    ],
                    'documents' => [
                        ['nama' => 'KTP Pimpinan', 'file_name' => 'ktp_ketua_yayasan.pdf', 'tanggal' => '2024-01-05'],
                    ],
                ],
            ],
            [
                'niy' => 'NIY-201202012',
                'nik' => '1371021204820003',
                'nama_lengkap' => 'Fadli Rahman',
                'nama_panggilan' => 'Ust. Fadli',
                'gelar_depan' => 'Ust.',
                'gelar_belakang' => 'S.Pd., M.Pd.',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Bukittinggi',
                'tanggal_lahir' => '1982-04-12',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $unitSdit,
                'jabatan_id' => $posKepsek,
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2012-02-10',
                'status' => 'Aktif',
                'no_hp' => '08126789001',
                'email' => 'fadli.rahman@dareliman.sch.id',
                'alamat' => 'Jl. Gunung Pangilun No. 45',
                'provinsi' => 'Sumatera Barat',
                'kota' => 'Padang',
                'kecamatan' => 'Padang Utara',
                'kelurahan' => 'Gunung Pangilun',
                'kode_pos' => '25137',
                'metadata' => [
                    'position_history' => [
                        ['jabatan' => 'Guru Kelas SDIT', 'tgl_mulai' => '2012-02-10', 'keterangan' => 'Pengangkatan Guru'],
                        ['jabatan' => 'Kepala Sekolah SDIT 1', 'tgl_mulai' => '2018-07-01', 'keterangan' => 'SK Pimpinan Sekolah'],
                    ],
                    'certifications' => [
                        ['nama' => 'Sertifikat Pendidik (Serdik SD)', 'penerbit' => 'Kemdikbudristek', 'tahun' => '2016', 'no_sertifikat' => 'SRD-2016-77889'],
                        ['nama' => 'Pelatihan Manajerial Kepala Sekolah', 'penerbit' => 'LPMP Sumbar', 'tahun' => '2019', 'no_sertifikat' => 'LPMP-2019-112'],
                    ],
                    'documents' => [
                        ['nama' => 'KTP', 'file_name' => 'ktp_fadli.pdf', 'tanggal' => '2023-08-10'],
                        ['nama' => 'Ijazah S2 Pendidikan', 'file_name' => 'ijazah_s2_fadli.pdf', 'tanggal' => '2023-08-10'],
                        ['nama' => 'SK Kepala Sekolah', 'file_name' => 'sk_kepsek_sdit1.pdf', 'tanggal' => '2023-08-10'],
                    ],
                    'attendances' => [
                        ['tanggal' => '2026-07-26', 'jam_masuk' => '07:05', 'jam_pulang' => '16:30', 'status' => 'Hadir'],
                        ['tanggal' => '2026-07-25', 'jam_masuk' => '07:10', 'jam_pulang' => '16:15', 'status' => 'Hadir'],
                    ],
                ],
            ],
            [
                'niy' => 'NIY-201503025',
                'nik' => '1371032511880005',
                'nama_lengkap' => 'Ahmad Farhan',
                'nama_panggilan' => 'Farhan',
                'gelar_depan' => 'Ust.',
                'gelar_belakang' => 'S.Pd.I',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Payakumbuh',
                'tanggal_lahir' => '1988-11-25',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $unitSdit,
                'jabatan_id' => $posGuruKelas,
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2015-03-01',
                'status' => 'Aktif',
                'no_hp' => '081374551122',
                'email' => 'ahmad.farhan@dareliman.sch.id',
                'alamat' => 'Kompleks Surau Gadang No. 12',
                'provinsi' => 'Sumatera Barat',
                'kota' => 'Padang',
                'kecamatan' => 'Nanggalo',
                'kelurahan' => 'Surau Gadang',
                'kode_pos' => '25146',
                'metadata' => [
                    'teachings' => [
                        ['mapel' => 'Bahasa Arab', 'kelas' => 'Kelas 5A', 'tahun' => '2025/2026', 'semester' => 'Ganjil'],
                        ['mapel' => 'Tahfizh Al-Qur\'an', 'kelas' => 'Kelas 5A', 'tahun' => '2025/2026', 'semester' => 'Ganjil'],
                        ['mapel' => 'Fiqih Ibadaah', 'kelas' => 'Kelas 5B', 'tahun' => '2025/2026', 'semester' => 'Ganjil'],
                    ],
                    'position_history' => [
                        ['jabatan' => 'Guru Kelas SDIT', 'tgl_mulai' => '2015-03-01', 'keterangan' => 'Wali Kelas 5A'],
                    ],
                    'certifications' => [
                        ['nama' => 'Sertifikat Pendidik Guru Agama Islam', 'penerbit' => 'Kemenag RI', 'tahun' => '2019', 'no_sertifikat' => 'KMN-2019-33441'],
                        ['nama' => 'Syahadah Tahfizh 30 Juz', 'penerbit' => 'LPTQ Sumbar', 'tahun' => '2014', 'no_sertifikat' => 'LPTQ-30J-098'],
                    ],
                    'documents' => [
                        ['nama' => 'Ijazah S1 PAI', 'file_name' => 'ijazah_farhan.pdf', 'tanggal' => '2024-02-01'],
                        ['nama' => 'SK Tetap Yayasan', 'file_name' => 'sk_tetap_farhan.pdf', 'tanggal' => '2024-02-01'],
                    ],
                    'attendances' => [
                        ['tanggal' => '2026-07-26', 'jam_masuk' => '07:15', 'jam_pulang' => '16:00', 'status' => 'Hadir'],
                        ['tanggal' => '2026-07-25', 'jam_masuk' => '07:12', 'jam_pulang' => '16:00', 'status' => 'Hadir'],
                    ],
                ],
            ],
            [
                'niy' => 'NIY-201003002',
                'nik' => '1371021203850002',
                'nama_lengkap' => 'Abdullah',
                'nama_panggilan' => 'Ust. Abdullah',
                'gelar_depan' => 'Ust.',
                'gelar_belakang' => 'S.Pd.I',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Padang',
                'tanggal_lahir' => '1985-03-12',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $unitSdit,
                'jabatan_id' => $posKepsek,
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2010-03-12',
                'status' => 'Aktif',
                'no_hp' => '081267890101',
                'email' => 'abdullah.headmaster@simsit.sch.id',
                'alamat' => 'Jl. Lima Puluh Kota No. 12',
                'provinsi' => 'Sumatera Barat',
                'kota' => '50 Kota',
                'kecamatan' => 'Payakumbuh',
                'kelurahan' => 'Koto Nan Ampek',
                'kode_pos' => '26211',
            ],
            [
                'niy' => 'NIY-201508004',
                'nik' => '1371021508900004',
                'nama_lengkap' => 'Rahmawati',
                'nama_panggilan' => 'Ustdh. Rahmawati',
                'gelar_depan' => 'Ustdh.',
                'gelar_belakang' => 'S.Pd',
                'jenis_kelamin' => 'P',
                'tempat_lahir' => '50 Kota',
                'tanggal_lahir' => '1990-08-15',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $unitSdit,
                'jabatan_id' => $posGuruKelas,
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2015-08-15',
                'status' => 'Aktif',
                'no_hp' => '081345678902',
                'email' => 'rahmawati.teacher@simsit.sch.id',
                'alamat' => 'Jl. Lima Puluh Kota No. 45',
                'provinsi' => 'Sumatera Barat',
                'kota' => '50 Kota',
                'kecamatan' => 'Harau',
                'kelurahan' => 'Tanjung Pati',
                'kode_pos' => '26261',
            ],
            [
                'niy' => 'NIY-201201005',
                'nik' => '1371022011880005',
                'nama_lengkap' => 'Ahmad Ridwan',
                'nama_panggilan' => 'Ust. Ridwan',
                'gelar_depan' => 'Ust.',
                'gelar_belakang' => 'M.Ag',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Payakumbuh',
                'tanggal_lahir' => '1988-11-20',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $unitSdit,
                'jabatan_id' => $posGuruMapel,
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2012-01-20',
                'status' => 'Aktif',
                'no_hp' => '081123456789',
                'email' => 'ahmad.ridwan@simsit.sch.id',
                'alamat' => 'Jl. Sudirman No. 8',
                'provinsi' => 'Sumatera Barat',
                'kota' => 'Payakumbuh',
                'kecamatan' => 'Payakumbuh Barat',
                'kelurahan' => 'Pakan Sinayan',
                'kode_pos' => '26224',
            ],
            [
                'niy' => 'NIY-201803001',
                'nik' => '1371021002940001',
                'nama_lengkap' => 'Siti Sarah',
                'nama_panggilan' => 'Siti Sarah',
                'gelar_depan' => '',
                'gelar_belakang' => 'S.T',
                'jenis_kelamin' => 'P',
                'tempat_lahir' => 'Payakumbuh',
                'tanggal_lahir' => '1994-02-10',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $unitSdit,
                'jabatan_id' => $posOperator,
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2018-03-10',
                'status' => 'Aktif',
                'no_hp' => '082198765432',
                'email' => 'sitisarah.admin@simsit.sch.id',
                'alamat' => 'Jl. A. Yani No. 15',
                'provinsi' => 'Sumatera Barat',
                'kota' => 'Payakumbuh',
                'kecamatan' => 'Payakumbuh Utara',
                'kelurahan' => 'Tiakar',
                'kode_pos' => '26218',
            ],
        ];

        // 20 Data Guru & Pegawai Tambahan Khusus SDIT 1 Dar el-Iman - 50 Kota
        $additionalGuru = [
            ['nama' => 'Ust. Zulkifli, S.Pd.I', 'gender' => 'L', 'jabatan' => $posGuruMapel],
            ['nama' => 'Ustdh. Laila Fitri, S.Pd', 'gender' => 'P', 'jabatan' => $posGuruKelas],
            ['nama' => 'Ust. Ilham Rabbani, M.Pd', 'gender' => 'L', 'jabatan' => $posGuruKelas],
            ['nama' => 'Ustdh. Fatimah Syahidah, S.Pd.I', 'gender' => 'P', 'jabatan' => $posGuruKelas],
            ['nama' => 'Ust. Hamzah As-Suyuthi, S.Pd', 'gender' => 'L', 'jabatan' => $posGuruKelas],
            ['nama' => 'Ustdh. Aisyah Humaira, S.Si', 'gender' => 'P', 'jabatan' => $posGuruMapel],
            ['nama' => 'Ust. Salman Al-Farisi, S.Hum', 'gender' => 'L', 'jabatan' => $posGuruMapel],
            ['nama' => 'Ustdh. Maryam Khadijah, S.Pd', 'gender' => 'P', 'jabatan' => $posGuruKelas],
            ['nama' => 'Ust. Bilal Ramadan, S.Or', 'gender' => 'L', 'jabatan' => $posGuruMapel],
            ['nama' => 'Ustdh. Zahra Nurul, S.Pd.I', 'gender' => 'P', 'jabatan' => $posGuruKelas],
            ['nama' => 'Ust. Umar Khadafi, S.Pd', 'gender' => 'L', 'jabatan' => $posGuruKelas],
            ['nama' => 'Ustdh. Khadijah Laila, S.Pd', 'gender' => 'P', 'jabatan' => $posGuruKelas],
            ['nama' => 'Ust. Ali Imran, S.Pd.I', 'gender' => 'L', 'jabatan' => $posGuruMapel],
            ['nama' => 'Ustdh. Safiyah Azzahra, S.Sos', 'gender' => 'P', 'jabatan' => $posTU],
            ['nama' => 'Ust. Hasan Basri, S.Kom', 'gender' => 'L', 'jabatan' => $posOperator],
            ['nama' => 'Ustdh. Mutia Rahmi, S.Pd', 'gender' => 'P', 'jabatan' => $posGuruKelas],
            ['nama' => 'Ust. Ridwan Syah, S.Pd.I', 'gender' => 'L', 'jabatan' => $posGuruMapel],
            ['nama' => 'Ustdh. Nurul Aini, S.Pd', 'gender' => 'P', 'jabatan' => $posGuruKelas],
            ['nama' => 'Ust. Faruq Al-Hafizh, S.Pd.I', 'gender' => 'L', 'jabatan' => $posGuruMapel],
            ['nama' => 'Ustdh. Annisa Thahirah, S.Pd', 'gender' => 'P', 'jabatan' => $posWakepsek],
        ];

        foreach ($additionalGuru as $idx => $g) {
            $niyCode = 'NIY-202409'.str_pad($idx + 10, 3, '0', STR_PAD_LEFT);
            $dummyEmployees[] = [
                'niy' => $niyCode,
                'nik' => '137102'.str_pad($idx + 10, 10, '0', STR_PAD_LEFT),
                'nama_lengkap' => str_replace(['Ust. ', 'Ustdh. '], '', $g['nama']),
                'nama_panggilan' => $g['nama'],
                'gelar_depan' => str_contains($g['nama'], 'Ustdh.') ? 'Ustdh.' : 'Ust.',
                'gelar_belakang' => str_contains($g['nama'], 'S.Pd.I') ? 'S.Pd.I' : (str_contains($g['nama'], 'M.Pd') ? 'M.Pd' : 'S.Pd'),
                'jenis_kelamin' => $g['gender'],
                'tempat_lahir' => 'Payakumbuh',
                'tanggal_lahir' => '1992-05-15',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $unitSdit,
                'jabatan_id' => $g['jabatan'],
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2020-07-01',
                'status' => 'Aktif',
                'no_hp' => '08127000'.str_pad($idx + 10, 4, '0', STR_PAD_LEFT),
                'email' => strtolower(str_replace(['Ust. ', 'Ustdh. ', ' ', ',', '.'], '', $g['nama'])).'@dareliman.sch.id',
                'alamat' => 'Jl. Lima Puluh Kota No. '.($idx + 10),
                'provinsi' => 'Sumatera Barat',
                'kota' => '50 Kota',
                'kecamatan' => 'Harau',
                'kelurahan' => 'Tanjung Pati',
                'kode_pos' => '26261',
            ];
        }

        $moreEmployees = [
            [
                'niy' => 'NIY-201608040',
                'nik' => '1371041802900002',
                'nama_lengkap' => 'Siti Nurhaliza',
                'nama_panggilan' => 'Ustdh. Siti',
                'gelar_depan' => 'Ustdh.',
                'gelar_belakang' => 'S.Si.',
                'jenis_kelamin' => 'P',
                'tempat_lahir' => 'Padang Panjang',
                'tanggal_lahir' => '1990-02-18',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $unitSmpit,
                'jabatan_id' => $posGuruMapel,
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2016-08-15',
                'status' => 'Aktif',
                'no_hp' => '082188990011',
                'email' => 'siti.nurhaliza@dareliman.sch.id',
                'alamat' => 'Jl. Hamka No. 88, Air Tawar',
                'provinsi' => 'Sumatera Barat',
                'kota' => 'Padang',
                'kecamatan' => 'Padang Utara',
                'kelurahan' => 'Air Tawar Barat',
                'kode_pos' => '25131',
                'metadata' => [
                    'teachings' => [
                        ['mapel' => 'IPA Terpadu / Biologi', 'kelas' => 'Kelas 8A SMPIT', 'tahun' => '2025/2026', 'semester' => 'Ganjil'],
                        ['mapel' => 'IPA Terpadu / Fisika', 'kelas' => 'Kelas 8B SMPIT', 'tahun' => '2025/2026', 'semester' => 'Ganjil'],
                    ],
                    'position_history' => [
                        ['jabatan' => 'Guru Mapel IPA SMPIT', 'tgl_mulai' => '2016-08-15', 'keterangan' => 'Guru Mapel Sains'],
                    ],
                    'certifications' => [
                        ['nama' => 'Sertifikat Pendidik Sains / Biologi', 'penerbit' => 'Kemdikbudristek', 'tahun' => '2021', 'no_sertifikat' => 'SRD-2021-9901'],
                    ],
                    'documents' => [
                        ['nama' => 'Ijazah S1 Biologi UNP', 'file_name' => 'ijazah_siti.pdf', 'tanggal' => '2024-03-01'],
                    ],
                    'attendances' => [
                        ['tanggal' => '2026-07-26', 'jam_masuk' => '07:18', 'jam_pulang' => '16:05', 'status' => 'Hadir'],
                    ],
                ],
            ],
            [
                'niy' => 'NIY-201801052',
                'nik' => '1371050505930007',
                'nama_lengkap' => 'Rahmat Hidayat',
                'nama_panggilan' => 'Rahmat',
                'gelar_depan' => '',
                'gelar_belakang' => 'S.Kom.',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Solok',
                'tanggal_lahir' => '1993-05-05',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $defaultUnitId,
                'jabatan_id' => $posOperator,
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2018-01-10',
                'status' => 'Aktif',
                'no_hp' => '085263114455',
                'email' => 'rahmat.it@dareliman.sch.id',
                'alamat' => 'Jl. Dr. Sutomo No. 19, Marapalam',
                'provinsi' => 'Sumatera Barat',
                'kota' => 'Padang',
                'kecamatan' => 'Padang Timur',
                'kelurahan' => 'Kubu Marapalam',
                'kode_pos' => '25125',
                'metadata' => [
                    'position_history' => [
                        ['jabatan' => 'Operator Dapodik & IT ERP', 'tgl_mulai' => '2018-01-10', 'keterangan' => 'Tim Pengelola IT Sekolah'],
                    ],
                    'certifications' => [
                        ['nama' => 'Certified Network Administrator (MikroTik)', 'penerbit' => 'MikroTik Academy', 'tahun' => '2020', 'no_sertifikat' => 'MTCNA-2020-009'],
                        ['nama' => 'Pelatihan Operator Dapodik Master', 'penerbit' => 'Dinas Pendidikan Sumbar', 'tahun' => '2022', 'no_sertifikat' => 'DISDIK-2022-88'],
                    ],
                    'documents' => [
                        ['nama' => 'Ijazah S1 Sistem Informasi', 'file_name' => 'ijazah_rahmat.pdf', 'tanggal' => '2024-01-15'],
                    ],
                    'attendances' => [
                        ['tanggal' => '2026-07-26', 'jam_masuk' => '07:00', 'jam_pulang' => '17:00', 'status' => 'Hadir'],
                    ],
                ],
            ],
            [
                'niy' => 'NIY-201905068',
                'nik' => '1371062010940004',
                'nama_lengkap' => 'Aisyah Rahmah',
                'nama_panggilan' => 'Aisyah',
                'gelar_depan' => 'Ustdh.',
                'gelar_belakang' => 'S.E.',
                'jenis_kelamin' => 'P',
                'tempat_lahir' => 'Padang',
                'tanggal_lahir' => '1994-10-20',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $unitSdit,
                'jabatan_id' => $posTU,
                'status_pegawai' => 'Kontrak',
                'tanggal_masuk' => '2019-05-01',
                'status' => 'Aktif',
                'no_hp' => '082381223344',
                'email' => 'aisyah.tu@dareliman.sch.id',
                'alamat' => 'Jl. Proklamasi No. 5',
                'provinsi' => 'Sumatera Barat',
                'kota' => 'Padang',
                'kecamatan' => 'Padang Selatan',
                'kelurahan' => 'Ganting Parak Gadang',
                'kode_pos' => '25122',
                'metadata' => [
                    'position_history' => [
                        ['jabatan' => 'Staf Tata Usaha SDIT', 'tgl_mulai' => '2019-05-01', 'keterangan' => 'Administrasi Keuangan & Surat'],
                    ],
                    'certifications' => [
                        ['nama' => 'Sertifikat Kompetensi Administrasi Perkantoran', 'penerbit' => 'BNSP RI', 'tahun' => '2021', 'no_sertifikat' => 'BNSP-2021-776'],
                    ],
                    'documents' => [
                        ['nama' => 'Ijazah S1 Akuntansi', 'file_name' => 'ijazah_aisyah.pdf', 'tanggal' => '2024-02-10'],
                    ],
                    'attendances' => [
                        ['tanggal' => '2026-07-26', 'jam_masuk' => '07:22', 'jam_pulang' => '16:00', 'status' => 'Hadir'],
                    ],
                ],
            ],
            [
                'niy' => 'NIY-202007080',
                'nik' => '1371071109890001',
                'nama_lengkap' => 'Rizky Alamsyah',
                'nama_panggilan' => 'Ust. Rizky',
                'gelar_depan' => 'Ust.',
                'gelar_belakang' => 'Lc., M.H.',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Batusangkar',
                'tanggal_lahir' => '1989-09-11',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $unitSmait,
                'jabatan_id' => $posWakepsek,
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2020-07-15',
                'status' => 'Aktif',
                'no_hp' => '081277112233',
                'email' => 'rizky.alamsyah@dareliman.sch.id',
                'alamat' => 'Jl. Andalas No. 102',
                'provinsi' => 'Sumatera Barat',
                'kota' => 'Padang',
                'kecamatan' => 'Padang Timur',
                'kelurahan' => 'Andalas',
                'kode_pos' => '25126',
                'metadata' => [
                    'teachings' => [
                        ['mapel' => 'Ushul Fiqih & Hadits', 'kelas' => 'Kelas 11 SMAIT', 'tahun' => '2025/2026', 'semester' => 'Ganjil'],
                    ],
                    'position_history' => [
                        ['jabatan' => 'Wakil Kepala Sekolah Bidang Kesiswaan', 'tgl_mulai' => '2020-07-15', 'keterangan' => 'Waka Kesiswaan SMAIT'],
                    ],
                    'certifications' => [
                        ['nama' => 'Ijazah Lc Syariah', 'penerbit' => 'LIPIA Jakarta', 'tahun' => '2014', 'no_sertifikat' => 'LIPIA-2014-991'],
                    ],
                    'documents' => [
                        ['nama' => 'KTP', 'file_name' => 'ktp_rizky.pdf', 'tanggal' => '2024-01-20'],
                    ],
                    'attendances' => [
                        ['tanggal' => '2026-07-26', 'jam_masuk' => '07:10', 'jam_pulang' => '16:20', 'status' => 'Hadir'],
                    ],
                ],
            ],
            [
                'niy' => 'NIY-202109095',
                'nik' => '1371081512960006',
                'nama_lengkap' => 'Khadijah Azzahra',
                'nama_panggilan' => 'Khadijah',
                'gelar_depan' => 'Ustdh.',
                'gelar_belakang' => 'S.Pd.AUD',
                'jenis_kelamin' => 'P',
                'tempat_lahir' => 'Padang',
                'tanggal_lahir' => '1996-12-15',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $unitTkit,
                'jabatan_id' => $posGuruKelas,
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2021-09-01',
                'status' => 'Aktif',
                'no_hp' => '085377889900',
                'email' => 'khadijah.tkit@dareliman.sch.id',
                'alamat' => 'Jl. Jati Adabiah No. 14',
                'provinsi' => 'Sumatera Barat',
                'kota' => 'Padang',
                'kecamatan' => 'Padang Timur',
                'kelurahan' => 'Jati',
                'kode_pos' => '25121',
                'metadata' => [
                    'teachings' => [
                        ['mapel' => 'Pengenalan Huruf Hijaiyah & Doa', 'kelas' => 'TKIT B2', 'tahun' => '2025/2026', 'semester' => 'Ganjil'],
                    ],
                    'position_history' => [
                        ['jabatan' => 'Guru Kelas TKIT 1', 'tgl_mulai' => '2021-09-01', 'keterangan' => 'Guru Pendamping & Wali TKIT'],
                    ],
                    'certifications' => [
                        ['nama' => 'Sertifikat Pendidik PAUD / TK', 'penerbit' => 'Kemdikbudristek', 'tahun' => '2023', 'no_sertifikat' => 'PAUD-2023-4411'],
                    ],
                    'documents' => [
                        ['nama' => 'Ijazah S1 PG-PAUD UNP', 'file_name' => 'ijazah_khadijah.pdf', 'tanggal' => '2024-03-05'],
                    ],
                    'attendances' => [
                        ['tanggal' => '2026-07-26', 'jam_masuk' => '07:15', 'jam_pulang' => '15:30', 'status' => 'Hadir'],
                    ],
                ],
            ],
            [
                'niy' => 'NIY-202201102',
                'nik' => '1371090101860008',
                'nama_lengkap' => 'Hendri Wijaya',
                'nama_panggilan' => 'Ust. Hendri',
                'gelar_depan' => 'Ust.',
                'gelar_belakang' => 'M.Pd.',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Pariaman',
                'tanggal_lahir' => '1986-01-01',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $defaultUnitId,
                'jabatan_id' => $posDivPendidikan,
                'status_pegawai' => 'Tetap',
                'tanggal_masuk' => '2022-01-15',
                'status' => 'Aktif',
                'no_hp' => '081299887766',
                'email' => 'hendri.pendidikan@dareliman.sch.id',
                'alamat' => 'Jl. Bypass KM 12',
                'provinsi' => 'Sumatera Barat',
                'kota' => 'Padang',
                'kecamatan' => 'Kuranji',
                'kelurahan' => 'Korong Gadang',
                'kode_pos' => '25154',
                'metadata' => [
                    'position_history' => [
                        ['jabatan' => 'Tim Pengawas Mutu Divisi Pendidikan', 'tgl_mulai' => '2022-01-15', 'keterangan' => 'Pengawas Akademik & Kurikulum Yayasan'],
                    ],
                    'certifications' => [
                        ['nama' => 'Sertifikat Pengawas Sekolah Nasional', 'penerbit' => 'Kemdikbudristek', 'tahun' => '2020', 'no_sertifikat' => 'PW-2020-0012'],
                    ],
                    'documents' => [
                        ['nama' => 'SK Penjamin Mutu', 'file_name' => 'sk_div_pendidikan.pdf', 'tanggal' => '2024-01-10'],
                    ],
                    'attendances' => [
                        ['tanggal' => '2026-07-26', 'jam_masuk' => '07:30', 'jam_pulang' => '16:30', 'status' => 'Hadir'],
                    ],
                ],
            ],
            [
                'niy' => 'NIY-202306115',
                'nik' => '1371101004970009',
                'nama_lengkap' => 'Zubair Al-Farisi',
                'nama_panggilan' => 'Zubair',
                'gelar_depan' => 'Ust.',
                'gelar_belakang' => 'S.Pd.',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Padang',
                'tanggal_lahir' => '1997-04-10',
                'agama' => 'Islam',
                'foto' => '',
                'unit_id' => $unitSmpit,
                'jabatan_id' => $posGuruMapel,
                'status_pegawai' => 'Honorer',
                'tanggal_masuk' => '2023-06-01',
                'status' => 'Cuti',
                'no_hp' => '083188229911',
                'email' => 'zubair.smpit@dareliman.sch.id',
                'alamat' => 'Jl. Bandar Buat No. 7',
                'provinsi' => 'Sumatera Barat',
                'kota' => 'Padang',
                'kecamatan' => 'Lubuk Kilangan',
                'kelurahan' => 'Bandar Buat',
                'kode_pos' => '25231',
                'metadata' => [
                    'position_history' => [
                        ['jabatan' => 'Guru Penjaskes SMPIT', 'tgl_mulai' => '2023-06-01', 'keterangan' => 'Guru Honorer Olahraga'],
                    ],
                    'certifications' => [
                        ['nama' => 'Pelatihan Pelatih Olahraga Panahan & Renang', 'penerbit' => 'KONI Sumbar', 'tahun' => '2022', 'no_sertifikat' => 'KONI-2022-77'],
                    ],
                    'documents' => [
                        ['nama' => 'Surat Keterangan Cuti Studi', 'file_name' => 'surat_cuti_zubair.pdf', 'tanggal' => '2026-06-01'],
                    ],
                ],
            ],
        ];

        foreach ($moreEmployees as $item) {
            $dummyEmployees[] = $item;
        }

        foreach ($dummyEmployees as $data) {
            $teachings = $data['metadata']['teachings'] ?? [];

            $emp = Employee::updateOrCreate(
                ['niy' => $data['niy']],
                $data
            );

            // === Link akun login pegawai (idempotent; role dari positions.role_sistem_id) ===
            $role = $emp->position?->roleSistem;
            $isActive = in_array(strtolower((string) ($data['status'] ?? 'Aktif')), ['aktif', 'tetap'], true);

            $loginUser = $emp->user_id
                ? User::query()->find($emp->user_id)
                : User::query()->where('email', strtolower((string) $data['email']))->first();

            if (! $loginUser) {
                $loginUser = User::query()->create([
                    'name' => $emp->nama_lengkap,
                    'email' => strtolower((string) $data['email']),
                    'password' => 'Password123!',
                    'phone' => PhoneNormalizer::normalize((string) ($data['no_hp'] ?? '')),
                    'is_active' => $isActive,
                ]);
            } else {
                $loginUser->forceFill([
                    'name' => $emp->nama_lengkap,
                    'phone' => PhoneNormalizer::normalize((string) ($data['no_hp'] ?? '')),
                    'is_active' => $isActive,
                ])->save();
            }

            $emp->update(['user_id' => $loginUser->id]);

            if ($role) {
                $emp->forceFill(['role_id' => $role->id])->save();
                $loginUser->syncRoles([$role->name]);
            } else {
                $emp->forceFill(['role_id' => null])->save();
                $loginUser->syncRoles(['Guru']);
            }

            // Seed teachings relation table
            if (! empty($teachings)) {
                foreach ($teachings as $t) {
                    $mapel = is_array($t) ? ($t['mapel'] ?? null) : null;

                    if ($mapel === null) {
                        continue;
                    }

                    $existing = EmployeeTeaching::where('employee_id', $emp->id)
                        ->where('metadata->mapel', $mapel)
                        ->first();

                    if ($existing) {
                        $existing->update(['aktif' => true]);
                    } else {
                        EmployeeTeaching::create([
                            'employee_id' => $emp->id,
                            'metadata' => $t,
                            'aktif' => true,
                        ]);
                    }
                }
            }
        }

        // === Pastikan setiap dari 15 Unit Pendidikan memiliki Pegawai yang Mengisi Seluruh Posisi Jabatan (17 Posisi per Unit) ===
        $allUnits = EducationUnit::query()->orderBy('code')->get();
        $positions = Position::all();
        $guruRole = Role::where('name', 'Guru')->where('guard_name', 'web')->first();
        $defaultHashedPassword = Hash::make('Password123!');

        $positionTemplates = [
            ['code' => 'JBT-003', 'level' => 3, 'title' => 'Kepala Sekolah', 'role' => 'Kepala Sekolah'],
            ['code' => 'JBT-004', 'level' => 4, 'title' => 'Wakil Kurikulum', 'role' => 'Wakil Kepala Sekolah'],
            ['code' => 'JBT-020', 'level' => 4, 'title' => 'Wakil Kesiswaan/Kesantrian', 'role' => 'Wakil Kepala Sekolah'],
            ['code' => 'JBT-021', 'level' => 4, 'title' => 'Wakil Humas & Progsus', 'role' => 'Wakil Kepala Sekolah'],
            ['code' => 'JBT-025', 'level' => 5, 'title' => 'Kepala Divisi Unit', 'role' => 'Kepala Divisi'],
            ['code' => 'JBT-006', 'level' => 6, 'title' => 'Kepala Tata Usaha', 'role' => 'Tata Usaha'],
            ['code' => 'JBT-007', 'level' => 7, 'title' => 'Operator Sekolah', 'role' => 'Tata Usaha'],
            ['code' => 'JBT-010', 'level' => 8, 'title' => 'Wali Kelas', 'role' => 'Wali Kelas'],
            ['code' => 'JBT-009', 'level' => 8, 'title' => 'Guru Mapel Matematika', 'role' => 'Guru'],
            ['code' => 'JBT-009', 'level' => 8, 'title' => 'Guru Mapel Bahasa & Sains', 'role' => 'Guru'],
            ['code' => 'JBT-022', 'level' => 8, 'title' => 'Guru Bimbingan Konseling', 'role' => 'Guru'],
            ['code' => 'JBT-024', 'level' => 8, 'title' => 'Pembina Ekstrakurikuler', 'role' => 'Guru'],
            ['code' => 'JBT-011', 'level' => 9, 'title' => 'Guru Tahfizh', 'role' => 'Guru'],
            ['code' => 'JBT-023', 'level' => 9, 'title' => 'Musyrif Kesantrian / Asrama', 'role' => 'Guru'],
            ['code' => 'JBT-008', 'level' => 10, 'title' => 'Bendahara Sekolah', 'role' => 'Tata Usaha'],
            ['code' => 'JBT-012', 'level' => 10, 'title' => 'Staf Administrasi', 'role' => 'Tata Usaha'],
            ['code' => 'JBT-013', 'level' => 10, 'title' => 'Staf Keamanan / Satpam', 'role' => 'Tata Usaha'],
        ];

        $maleNameList = [
            'Rizky Pratama', 'Hafiz Azhar', 'Hamzah Saifullah', 'Irfan Hilmi',
            'Khairul Umam', 'Lukman Hakim', 'Muhammad Farhan', 'Naufal Azmi',
            'Rizky Ramadhan', 'Syahrul Ramadhan', 'Taufik Hidayat', 'Usman Al-Ghazali',
            'Yahya Ayyash', 'Zainuddin Fanani', 'Arif Rahman', 'Bayu Pratama',
            'Doni Kusuma', 'Eko Prasetyo', 'Gilang Ramadhan', 'Hadi Sucipto',
        ];

        $femaleNameList = [
            'Aisyah Ridwan', 'Annisa Zahra', 'Dewi Sartika', 'Fitri Handayani',
            'Hana Pertiwi', 'Indah Permata', 'Laila Majnun', 'Marlina Putri',
            'Nadia Syakira', 'Nurul Laili', 'Rahmawati', 'Siti Maryam',
            'Tari Kusuma', 'Umi Kulsum', 'Winda Asri', 'Yulia Safitri',
            'Zahra Amalia', 'Dian Lestari', 'Eka Rahayu', 'Gita Gutawa',
        ];

        foreach ($allUnits as $unitIndex => $unit) {
            for ($pIndex = 0; $pIndex < count($positionTemplates); $pIndex++) {
                $template = $positionTemplates[$pIndex];
                $posModel = $positions->firstWhere('code', $template['code'])
                    ?? $positions->firstWhere('level_jabatan', $template['level']);

                if ($template['level'] === 3 && ! empty($unit->metadata['principal_name'])) {
                    $principalName = $unit->metadata['principal_name'];
                    $isFemale = (str_contains($principalName, 'Ustzh') || str_contains($principalName, 'Maryam') || str_contains($principalName, 'Nurul') || str_contains($principalName, 'Yuliana') || str_contains($principalName, 'Siti'));
                    $fullName = $principalName;
                    $cleanName = preg_replace('/^(Ust\.|Ustzh\.|Dr\.)\s*/i', '', $principalName);
                    $panggilan = explode(' ', trim($cleanName))[0] ?? 'Kepsek';
                    $gelarDepan = $isFemale ? 'Ustzh.' : 'Ust.';
                } else {
                    $isFemale = ($pIndex % 2 === 1);
                    $namePool = $isFemale ? $femaleNameList : $maleNameList;
                    $personName = $namePool[($unitIndex * 17 + $pIndex) % count($namePool)];
                    $gelarDepan = $isFemale ? 'Ustzh.' : 'Ust.';
                    $fullName = "{$gelarDepan} {$personName}, S.Pd.";
                    $panggilan = explode(' ', $personName)[0];
                }

                $nikNum = '1371'.str_pad((string) ($unitIndex + 1), 2, '0', STR_PAD_LEFT).'00'.str_pad((string) ($pIndex + 1), 6, '0', STR_PAD_LEFT);
                $slugCode = strtolower(str_replace(['-', ' '], '', $unit->code));
                $niyCode = 'NIY-'.strtoupper($slugCode).'-'.str_pad((string) ($pIndex + 1), 2, '0', STR_PAD_LEFT);
                $email = 'pegawai.'.str_pad((string) ($pIndex + 1), 2, '0', STR_PAD_LEFT).".{$slugCode}@dareliman.sch.id";

                $roleTarget = Role::where('name', $template['role'])->where('guard_name', 'web')->first()
                    ?? $guruRole;

                $empRecord = Employee::updateOrCreate(
                    [
                        'email' => $email,
                    ],
                    [
                        'niy' => $niyCode,
                        'nik' => $nikNum,
                        'nama_lengkap' => $fullName,
                        'nama_panggilan' => $panggilan,
                        'gelar_depan' => $gelarDepan,
                        'gelar_belakang' => 'S.Pd.',
                        'jenis_kelamin' => $isFemale ? 'P' : 'L',
                        'tempat_lahir' => 'Padang',
                        'tanggal_lahir' => '1985-05-15',
                        'agama' => 'Islam',
                        'unit_id' => $unit->id,
                        'jabatan_id' => $posModel?->id,
                        'status_pegawai' => ($pIndex < 10) ? 'Tetap' : 'Kontrak',
                        'tanggal_masuk' => '2018-07-01',
                        'status' => 'Aktif',
                        'no_hp' => '0812'.str_pad((string) ($unitIndex * 17 + $pIndex + 1), 8, '0', STR_PAD_LEFT),
                        'email' => $email,
                        'alamat' => $unit->metadata['address'] ?? 'Kota Padang',
                        'provinsi' => 'Sumatera Barat',
                        'kota' => $unit->metadata['city'] ?? 'Padang',
                        'metadata' => [
                            'position_history' => [
                                ['jabatan' => "{$template['title']} {$unit->name}", 'tgl_mulai' => '2018-07-01', 'keterangan' => 'Pengangkatan Pegawai'],
                            ],
                        ],
                    ]
                );

                $loginUser = User::query()->where('email', strtolower($email))->first();
                if (! $loginUser) {
                    $loginUser = User::query()->create([
                        'name' => $empRecord->nama_lengkap,
                        'email' => strtolower($email),
                        'password' => $defaultHashedPassword,
                        'phone' => PhoneNormalizer::normalize((string) $empRecord->no_hp),
                        'is_active' => true,
                    ]);
                }

                $empRecord->update([
                    'user_id' => $loginUser->id,
                    'role_id' => $roleTarget?->id,
                ]);

                if ($roleTarget) {
                    $loginUser->syncRoles([$roleTarget->name]);
                }
            }
        }
    }
}
