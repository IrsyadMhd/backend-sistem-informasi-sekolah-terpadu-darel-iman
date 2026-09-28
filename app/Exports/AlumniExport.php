<?php

namespace App\Exports;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AlumniExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected Builder $query;
    private int $rowNumber = 0;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'No',
            'NIS',
            'NISN',
            'Nama Siswa',
            'Jenis Kelamin',
            'Unit Pendidikan',
            'Tahun Lulus',
            'Tujuan Kelulusan',
            'Status Lanjutan',
            'Catatan',
        ];
    }

    /**
     * @param Student $row
     */
    public function map($row): array
    {
        $this->rowNumber++;
        $meta = is_array($row->metadata) ? $row->metadata : (json_decode($row->metadata, true) ?: []);

        return [
            $this->rowNumber,
            $row->nis ?? '-',
            $row->nisn ?? '-',
            $row->full_name,
            $row->gender === 'female' ? 'Perempuan' : 'Laki-Laki',
            $row->educationUnit?->name ?? ($row->schoolClass?->unitPendidikan?->name ?? '-'),
            $meta['tahun_lulus'] ?? $row->tahun_masuk ?? '-',
            $meta['tujuan_kelulusan'] ?? $meta['perguruan_tinggi'] ?? '-',
            $meta['status_lanjutan'] ?? $meta['pekerjaan'] ?? 'Kuliah',
            $meta['catatan_alumni'] ?? ($meta['catatan'] ?? '-'),
        ];
    }
}
