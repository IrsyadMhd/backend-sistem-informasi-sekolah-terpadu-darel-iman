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
        return ['Indikator Laporan', 'Nilai / Jumlah'];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->summary as $key => $val) {
            $label = ucwords(str_replace('_', ' ', $key));
            $rows[] = [$label, is_array($val) ? json_encode($val) : $val];
        }
        return $rows;
    }
}

class UnitRecapSheet implements FromArray, WithTitle, WithHeadings
{
    protected array $recaps;
    protected ?array $total;

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
        if (empty($this->recaps)) return [];
        $first = (array) reset($this->recaps);
        return array_map(fn ($k) => ucwords(str_replace('_', ' ', $k)), array_keys($first));
    }

    public function array(): array
    {
        $rows = array_map(fn ($item) => (array) $item, $this->recaps);
        if ($this->total) {
            $rows[] = (array) $this->total;
        }
        return $rows;
    }
}

class DetailsSheet implements FromArray, WithTitle, WithHeadings
{
    protected array $details;

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
        if (empty($this->details)) return [];
        $first = (array) reset($this->details);
        return array_map(fn ($k) => ucwords(str_replace('_', ' ', $k)), array_keys($first));
    }

    public function array(): array
    {
        return array_map(fn ($item) => (array) $item, $this->details);
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
