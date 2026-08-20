<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FoundationReportExport implements WithMultipleSheets
{
    protected string $type;
    protected array $data;

    public function __construct(string $type, array $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1: Summary KPI
        $sheets[] = new SummarySheet($this->data['summary'] ?? []);

        // Sheet 2: Rekap Per Unit
        if (!empty($this->data['unit_recaps'])) {
            $sheets[] = new UnitRecapSheet($this->data['unit_recaps'], $this->data['unit_recaps_total'] ?? null);
        } elseif (!empty($this->data['main_comparison'])) {
            $sheets[] = new UnitRecapSheet($this->data['main_comparison'], $this->data['comparison_total'] ?? null);
        }

        // Sheet 3: Data Rinci
        if (!empty($this->data['details'])) {
            $sheets[] = new DetailsSheet($this->data['details']);
        }

        // Sheet 4: Filter & Info Laporan
        $sheets[] = new ReportInfoSheet($this->data['report'] ?? []);

        return $sheets;
    }
}

class SummarySheet implements FromArray, WithTitle, WithHeadings
{
    protected array $summary;

    public function __construct(array|\Illuminate\Support\Collection $summary)
    {
        $this->summary = $summary instanceof \Illuminate\Support\Collection ? $summary->toArray() : $summary;
    }

    public function title(): string
    {
        return 'Ringkasan KPI';
    }

    public function headings(): array
    {
        return ['Indikator Utama Laporan', 'Nilai / Jumlah'];
    }

    public function array(): array
    {
        $kpiLabels = [
            'total_sdm' => 'Total SDM Pegawai',
            'total_guru' => 'Total Guru / Pendidik',
            'total_non_guru' => 'Pegawai Non-Guru',
            'sdm_aktif' => 'SDM Aktif',
            'sdm_nonaktif' => 'SDM Nonaktif',
            'guru_tetap' => 'Guru Tetap',
            'guru_tidak_tetap' => 'Guru Tidak Tetap',
            'pegawai_tetap' => 'Pegawai Tetap',
            'pegawai_tidak_tetap' => 'Pegawai Tidak Tetap',
            'laki_laki' => 'Laki-Laki',
            'perempuan' => 'Perempuan',
            'sdm_baru' => 'SDM Baru (Periode)',
            'sdm_keluar' => 'SDM Keluar',
        ];

        $rows = [];
        foreach ($this->summary as $key => $val) {
            $label = $kpiLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
            $rows[] = [$label, is_array($val) ? json_encode($val) : $val];
        }
        return $rows;
    }
}

class UnitRecapSheet implements FromArray, WithTitle, WithHeadings
{
    protected array $recaps;
    protected ?array $total;

    protected array $headerMap = [
        'unit_name' => 'Unit Pendidikan',
        'guru' => 'Guru',
        'non_guru' => 'Pegawai Non-Guru',
        'total_sdm' => 'Total SDM',
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
        'laki_laki' => 'Laki-Laki',
        'perempuan' => 'Perempuan',
    ];

    public function __construct(array|\Illuminate\Support\Collection $recaps, ?array $total = null)
    {
        $this->recaps = $recaps instanceof \Illuminate\Support\Collection ? $recaps->toArray() : (array) $recaps;
        $this->total = $total;
    }

    public function title(): string
    {
        return 'Rekap Per Unit';
    }

    public function headings(): array
    {
        return array_values($this->headerMap);
    }

    public function array(): array
    {
        $keys = array_keys($this->headerMap);
        $rows = [];
        foreach ($this->recaps as $item) {
            $arr = (array) $item;
            $row = [];
            foreach ($keys as $k) {
                $row[] = $arr[$k] ?? '-';
            }
            $rows[] = $row;
        }

        if ($this->total) {
            $arrTotal = (array) $this->total;
            $rowTotal = [];
            foreach ($keys as $k) {
                $rowTotal[] = $arrTotal[$k] ?? '-';
            }
            $rows[] = $rowTotal;
        }

        return $rows;
    }
}

class DetailsSheet implements FromArray, WithTitle, WithHeadings
{
    protected array $details;

    protected array $headerMap = [
        'niy' => 'NIY / NIK',
        'nama' => 'Nama Lengkap',
        'jenis_sdm' => 'Jenis SDM',
        'unit' => 'Unit Pendidikan',
        'jabatan' => 'Jabatan',
        'divisi_mapel' => 'Divisi / Mapel',
        'status_kepegawaian' => 'Status Kepegawaian',
        'tanggal_masuk' => 'Tanggal Masuk',
        'status' => 'Status',
    ];

    public function __construct(array|\Illuminate\Support\Collection $details)
    {
        $this->details = $details instanceof \Illuminate\Support\Collection ? $details->toArray() : (array) $details;
    }

    public function title(): string
    {
        return 'Data Rinci';
    }

    public function headings(): array
    {
        if (empty($this->details)) return array_values($this->headerMap);
        $first = (array) reset($this->details);
        $validKeys = array_filter(array_keys($first), fn ($k) => !in_array($k, ['id', 'unit_id', 'created_at', 'updated_at']));
        return array_map(fn ($k) => $this->headerMap[$k] ?? ucwords(str_replace('_', ' ', $k)), $validKeys);
    }

    public function array(): array
    {
        if (empty($this->details)) return [];
        $first = (array) reset($this->details);
        $validKeys = array_filter(array_keys($first), fn ($k) => !in_array($k, ['id', 'unit_id', 'created_at', 'updated_at']));

        $rows = [];
        foreach ($this->details as $item) {
            $arr = (array) $item;
            $row = [];
            foreach ($validKeys as $k) {
                $v = $arr[$k] ?? '-';
                $row[] = is_array($v) ? json_encode($v) : $v;
            }
            $rows[] = $row;
        }
        return $rows;
    }
}

class ReportInfoSheet implements FromArray, WithTitle, WithHeadings
{
    protected array $reportInfo;

    public function __construct(array $reportInfo)
    {
        $this->reportInfo = $reportInfo;
    }

    public function title(): string
    {
        return 'Informasi Laporan';
    }

    public function headings(): array
    {
        return ['Parameter', 'Keterangan'];
    }

    public function array(): array
    {
        return [
            ['Judul Laporan', $this->reportInfo['title'] ?? '-'],
            ['Deskripsi', $this->reportInfo['description'] ?? '-'],
            ['Periode Laporan', $this->reportInfo['period']['label'] ?? '-'],
            ['Tanggal Mulai', $this->reportInfo['period']['start_date'] ?? '-'],
            ['Tanggal Selesai', $this->reportInfo['period']['end_date'] ?? '-'],
            ['Waktu Dibuat', $this->reportInfo['generated_at'] ?? now()->toIso8601String()],
            ['Sistem', 'Sistem Manajemen Sekolah Terpadu - Yayasan'],
        ];
    }
}
