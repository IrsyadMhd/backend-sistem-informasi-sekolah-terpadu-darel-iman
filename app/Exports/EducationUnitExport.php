<?php

namespace App\Exports;

use App\Models\EducationUnit;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EducationUnitExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
            'Kode Unit',
            'Nama Unit Pendidikan',
            'Jenjang',
            'NPSN',
            'Alamat',
            'Kota',
            'Provinsi',
            'Nama Pimpinan',
            'Total Guru',
            'Total Siswa',
            'Status',
        ];
    }

    public function map($unit): array
    {
        $this->rowNumber++;
        $meta = $unit->metadata ?? [];

        return [
            $this->rowNumber,
            $unit->code ?? '-',
            $unit->name,
            $unit->level ?? '-',
            $meta['npsn'] ?? '-',
            $meta['address'] ?? '-',
            $meta['city'] ?? '-',
            $meta['province'] ?? '-',
            $meta['principal_name'] ?? $meta['kepala_unit'] ?? '-',
            $unit->total_guru ?? 0,
            $unit->total_siswa ?? 0,
            $unit->is_active ? 'Aktif' : 'Nonaktif',
        ];
    }
}
