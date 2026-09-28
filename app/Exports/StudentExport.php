<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    private int $rowNumber = 0;

    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'No',
            'No Pendaftaran',
            'NIK',
            'No Registrasi Akta Lahir',
            'No KK',
            'Nama Lengkap',
            'Tanggal Lahir',
            'Tempat Lahir',
            'Jenis Kelamin',
            'Agama',
            'Email',
            'Anak Ke',
            'Jumlah Saudara',
            'Jumlah Saudara tiri',
            'Berat Badan',
            'Tinggi Badan',
            'Riwayat Penyakit',
            'Kewarganegaraan',
            'Alamat Siswa',
            'RT',
            'RW',
            'Dusun',
            'Kelurahan',
            'Kecamatan',
            'Kode Pos',
            'Kota/Kabupaten',
            'Provinsi',
            'Jenis Tempat Tinggal',
            'Jarak tempuh ke sekolah',
            'Modal Transportasi',
            'Sekolah Asal',
            'Status sekolah Asal (Formal/Tidak)',
            'Kecamatan Sekolah Asal',
            'Kota/Kab Sekolah Asal',
            'Nomor Hp/Wa Sekolah Asal',
            'Hobi',
            'Cita-cita',
            'Nominal Spp',
            'Nominal Ortu Asuh',
            'Penerima KPS/PKH (ya/tidak)',
            'Apakah Punya KIP (ya/tidak)',
            'Apakah layak Menerima PIP (ya/tidak)',
            'Alasan Menolak PIP (Sudah mampu/dilarang pemda/menerima bantuan serupa)',

            // Data Ayah
            'NIK Ayah',
            'Nama Ayah',
            'Tempat Lahir Ayah',
            'Tgl Lahir Ayah',
            'Telfon Ayah',
            'HP Ayah',
            'Pendidikan Terakhir Ayah',
            'Pekerjaan Ayah',
            'Instansi Pekerjaan Ayah',
            'Jabatan Pekerjaan Ayah',
            'Alamat Instansi Ayah',
            'Keahlian Ayah',
            'Penghasilan Ayah',
            'Alamat Ayah',
            'Nomor WA Ayah',
            'Medsos Ayah',

            // Data Ibu
            'Nama Ibu',
            'NIK Ibu',
            'Tempat Lahir Ibu',
            'Tgl Lahir Ibu',
            'Telfon Ibu',
            'HP Ibu',
            'Pendidikan Terakhir Ibu',
            'Pekerjaan Ibu',
            'Instansi Pekerjaan Ibu',
            'Jabatan Pekerjaan Ibu',
            'Alamat Instansi Ibu',
            'Keahlian Ibu',
            'Penghasilan Ibu',
            'Alamat Ibu',
            'Nomor WA Ibu',
            'Medsos Ibu',

            // Data Wali
            'Status Pernikahan',
            'Tanggungan Anak',
            'NIK Wali',
            'Nama Wali',
            'Tempat Lahir Wali',
            'Tgl Lahir Wali',
            'Telfon Wali',
            'HP Wali',
            'Pendidikan Terakhir Wali',
            'Pekerjaan Wali',
            'Instansi Pekerjaan Wali',
            'Jabatan Pekerjaan Wali',
            'Alamat Instansi Wali',
            'Keahlian Wali',
            'Penghasilan Wali',
            'Alamat Wali',
            'Nomor WA Wali',
            'Medsos Wali',

            // Data Akademik & Penempatan
            'Unit Pendidikan',
            'NIS (Sekolah)',
            'NISN (Nasional)',
            'NIP (Pembayaran)',
            'Tahun Ajaran Masuk',
            'Kelas',
            'Keterangan Kelas',
            'Tahun Ajaran Berjalan',
            'Status Siswa (aktif atau tidak)',
            'Status Orang Tua (Umum atau pegawai)',
            'NIY Ortu Jika Pegawai',
            'Wali Kelas',
            'NIY Wali Kelas',
            'email',
        ];
    }

    public function map($std): array
    {
        $this->rowNumber++;
        $meta = is_array($std->metadata) ? $std->metadata : (json_decode($std->metadata ?? '[]', true) ?: []);
        $orangTua = $meta['orang_tua'] ?? [];
        $ayah = $meta['ayah'] ?? [];
        $ibu = $meta['ibu'] ?? [];
        $wali = $meta['wali'] ?? [];
        $akademik = $meta['akademik'] ?? [];

        // Helper boolean to ya/tidak
        $toYaTidak = function ($val) {
            if ($val === true || $val === 1 || $val === '1' || $val === 'ya' || $val === 'true') {
                return 'ya';
            }
            return 'tidak';
        };

        // Format tanggal lahir
        $birthDate = null;
        if ($std->birth_date) {
            try {
                $birthDate = Carbon::parse($std->birth_date)->format('Y-m-d');
            } catch (\Throwable $e) {
                $birthDate = (string) $std->birth_date;
            }
        } elseif (! empty($meta['birth_date']) || ! empty($meta['tanggal_lahir'])) {
            $birthDate = $meta['birth_date'] ?? $meta['tanggal_lahir'];
        }

        $gender = in_array(strtolower((string) ($std->gender ?? $meta['gender'] ?? '')), ['female', 'p', 'perempuan'])
            ? 'Perempuan'
            : 'Laki-Laki';

        return [
            $this->rowNumber,
            $meta['no_pendaftaran'] ?? '-',
            $meta['nik'] ?? $meta['nik_siswa'] ?? '-',
            $meta['no_registrasi_akta_lahir'] ?? $meta['akta_lahir'] ?? '-',
            $meta['no_kk'] ?? $meta['nomor_kk'] ?? '-',
            $std->full_name ?? $std->name ?? '-',
            $birthDate ?? '-',
            $std->birth_place ?? $meta['birth_place'] ?? $meta['tempat_lahir'] ?? '-',
            $gender,
            $meta['agama'] ?? $meta['religion'] ?? 'Islam',
            $std->user?->email ?? $meta['email'] ?? '-',
            $meta['anak_ke'] ?? '-',
            $meta['jumlah_saudara'] ?? '-',
            $meta['jumlah_saudara_tiri'] ?? '-',
            $meta['berat_badan'] ?? '-',
            $meta['tinggi_badan'] ?? '-',
            $meta['riwayat_penyakit'] ?? '-',
            $meta['kewarganegaraan'] ?? 'WNI',
            $std->address ?? $meta['alamat_siswa'] ?? $meta['alamat'] ?? '-',
            $meta['rt'] ?? '-',
            $meta['rw'] ?? '-',
            $meta['dusun'] ?? '-',
            $meta['kelurahan'] ?? '-',
            $meta['kecamatan'] ?? '-',
            $meta['kode_pos'] ?? '-',
            $meta['kota_kabupaten'] ?? $meta['kota'] ?? '-',
            $meta['provinsi'] ?? '-',
            $meta['jenis_tempat_tinggal'] ?? '-',
            $meta['jarak_tempuh_ke_sekolah'] ?? $meta['jarak_ke_sekolah'] ?? '-',
            $meta['moda_transportasi'] ?? '-',
            $meta['sekolah_asal'] ?? '-',
            $meta['status_sekolah_asal'] ?? '-',
            $meta['kecamatan_sekolah_asal'] ?? '-',
            $meta['kota_kab_sekolah_asal'] ?? '-',
            $meta['nomor_hp_wa_sekolah_asal'] ?? $meta['hp_wa_sekolah_asal'] ?? '-',
            $meta['hobi'] ?? '-',
            $meta['cita_cita'] ?? '-',
            $meta['nominal_spp'] ?? '-',
            $meta['nominal_ortu_asuh'] ?? '-',
            $toYaTidak($meta['penerima_kps_pkh'] ?? null),
            $toYaTidak($meta['apakah_punya_kip'] ?? null),
            $toYaTidak($meta['apakah_layak_menerima_pip'] ?? null),
            $meta['alasan_menolak_pip'] ?? '-',

            // Data Ayah
            $meta['nik_ayah'] ?? $ayah['nik'] ?? '-',
            $meta['nama_ayah'] ?? $ayah['nama'] ?? $orangTua['nama_ayah'] ?? $std->parent?->full_name ?? '-',
            $meta['tempat_lahir_ayah'] ?? $ayah['tempat_lahir'] ?? '-',
            $meta['tgl_lahir_ayah'] ?? $ayah['tgl_lahir'] ?? '-',
            $meta['telfon_ayah'] ?? $ayah['telfon'] ?? '-',
            $meta['hp_ayah'] ?? $ayah['hp'] ?? $meta['no_hp_ayah'] ?? $orangTua['no_hp'] ?? $std->parent?->phone ?? '-',
            $meta['pendidikan_terakhir_ayah'] ?? $ayah['pendidikan_terakhir'] ?? '-',
            $meta['pekerjaan_ayah'] ?? $ayah['pekerjaan'] ?? '-',
            $meta['instansi_pekerjaan_ayah'] ?? $ayah['instansi'] ?? '-',
            $meta['jabatan_pekerjaan_ayah'] ?? $ayah['jabatan'] ?? '-',
            $meta['alamat_instansi_ayah'] ?? $ayah['alamat_instansi'] ?? '-',
            $meta['keahlian_ayah'] ?? $ayah['keahlian'] ?? '-',
            $meta['penghasilan_ayah'] ?? $ayah['penghasilan'] ?? '-',
            $meta['alamat_ayah'] ?? $ayah['alamat'] ?? '-',
            $meta['nomor_wa_ayah'] ?? $meta['wa_ayah'] ?? '-',
            $meta['medsos_ayah'] ?? '-',

            // Data Ibu
            $meta['nama_ibu'] ?? $ibu['nama'] ?? $orangTua['nama_ibu'] ?? '-',
            $meta['nik_ibu'] ?? $ibu['nik'] ?? '-',
            $meta['tempat_lahir_ibu'] ?? $ibu['tempat_lahir'] ?? '-',
            $meta['tgl_lahir_ibu'] ?? $ibu['tgl_lahir'] ?? '-',
            $meta['telfon_ibu'] ?? $ibu['telfon'] ?? '-',
            $meta['hp_ibu'] ?? $ibu['hp'] ?? $meta['no_hp_ibu'] ?? '-',
            $meta['pendidikan_terakhir_ibu'] ?? $ibu['pendidikan_terakhir'] ?? '-',
            $meta['pekerjaan_ibu'] ?? $ibu['pekerjaan'] ?? '-',
            $meta['instansi_pekerjaan_ibu'] ?? $ibu['instansi'] ?? '-',
            $meta['jabatan_pekerjaan_ibu'] ?? $ibu['jabatan'] ?? '-',
            $meta['alamat_instansi_ibu'] ?? $ibu['alamat_instansi'] ?? '-',
            $meta['keahlian_ibu'] ?? $ibu['keahlian'] ?? '-',
            $meta['penghasilan_ibu'] ?? $ibu['penghasilan'] ?? '-',
            $meta['alamat_ibu'] ?? $ibu['alamat'] ?? '-',
            $meta['nomor_wa_ibu'] ?? $meta['wa_ibu'] ?? '-',
            $meta['medsos_ibu'] ?? '-',

            // Data Wali
            $meta['status_pernikahan_wali'] ?? $meta['status_pernikahan'] ?? '-',
            $meta['tanggungan_anak_wali'] ?? $meta['tanggungan_anak'] ?? '-',
            $meta['nik_wali'] ?? $wali['nik'] ?? '-',
            $meta['nama_wali'] ?? $wali['nama'] ?? $orangTua['nama_wali'] ?? '-',
            $meta['tempat_lahir_wali'] ?? $wali['tempat_lahir'] ?? '-',
            $meta['tgl_lahir_wali'] ?? $wali['tgl_lahir'] ?? '-',
            $meta['telfon_wali'] ?? $wali['telfon'] ?? '-',
            $meta['hp_wali'] ?? $wali['hp'] ?? $meta['no_hp_wali'] ?? '-',
            $meta['pendidikan_terakhir_wali'] ?? $wali['pendidikan_terakhir'] ?? '-',
            $meta['pekerjaan_wali'] ?? $wali['pekerjaan'] ?? '-',
            $meta['instansi_pekerjaan_wali'] ?? $wali['instansi'] ?? '-',
            $meta['jabatan_pekerjaan_wali'] ?? $wali['jabatan'] ?? '-',
            $meta['alamat_instansi_wali'] ?? $wali['alamat_instansi'] ?? '-',
            $meta['keahlian_wali'] ?? $wali['keahlian'] ?? '-',
            $meta['penghasilan_wali'] ?? $wali['penghasilan'] ?? '-',
            $meta['alamat_wali'] ?? $wali['alamat'] ?? '-',
            $meta['nomor_wa_wali'] ?? $meta['wa_wali'] ?? '-',
            $meta['medsos_wali'] ?? '-',

            // Data Akademik & Penempatan
            $std->educationUnit?->name ?? $akademik['unit_pendidikan'] ?? $meta['unit_pendidikan'] ?? '-',
            $std->nis ?? '-',
            $std->nisn ?? $meta['nisn'] ?? '-',
            $meta['nis_pembayaran'] ?? $meta['nip_pembayaran'] ?? '-',
            $meta['tahun_ajaran_masuk'] ?? $std->tahun_masuk ?? '-',
            $std->kelas?->nama_kelas ?? $std->schoolClass?->nama_kelas ?? $std->schoolClass?->name ?? $meta['kelas'] ?? '-',
            $meta['keterangan_kelas'] ?? '-',
            $meta['tahun_ajaran_berjalan'] ?? '-',
            $std->is_active ? 'aktif' : 'tidak',
            $meta['status_orang_tua'] ?? 'Umum',
            $meta['niy_ortu_jika_pegawai'] ?? '-',
            $std->kelas?->waliKelas?->nama_lengkap ?? $std->kelas?->wali_kelas_nama ?? $meta['wali_kelas'] ?? '-',
            $std->kelas?->waliKelas?->niy ?? $std->kelas?->wali_kelas_niy ?? $meta['niy_wali_kelas'] ?? '-',
            $std->user?->email ?? $meta['email'] ?? '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0E5C44'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
