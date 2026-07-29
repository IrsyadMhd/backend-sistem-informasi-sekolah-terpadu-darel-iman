<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\EmployeeTeaching;
use App\Models\Position;
use Illuminate\Database\Seeder;

class DataDummyPegawaiSeeder extends Seeder
{
    public function run(): void
    {
        $units = EducationUnit::all();
        $positions = Position::all();

        $defaultUnitId = $units->first()?->id;
        $unitSdit = $units->firstWhere('level', 'SDIT')?->id ?? $defaultUnitId;
        $unitSmpit = $units->firstWhere('level', 'SMPIT')?->id ?? $defaultUnitId;
        $unitSmait = $units->firstWhere('level', 'SMAIT')?->id ?? $defaultUnitId;
        $unitTkit = $units->firstWhere('level', 'TKIT')?->id ?? $defaultUnitId;

        $posKepsek = $positions->firstWhere('code', 'JBT-003')?->id;
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

        foreach ($dummyEmployees as $data) {
            $teachings = $data['metadata']['teachings'] ?? [];
            
            $emp = Employee::updateOrCreate(
                ['niy' => $data['niy']],
                $data
            );

            // Seed teachings relation table
            if (!empty($teachings)) {
                EmployeeTeaching::where('employee_id', $emp->id)->delete();
                foreach ($teachings as $t) {
                    EmployeeTeaching::create([
                        'employee_id' => $emp->id,
                        'aktif' => true,
                        'metadata' => $t,
                    ]);
                }
            }
        }
    }
}
