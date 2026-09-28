<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ParentExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
            'Nama Lengkap',
            'NIK',
            'Hubungan',
            'Nama Siswa',
            'Kelas Siswa',
            'Unit Pendidikan',
            'No. HP / WhatsApp',
            'Email',
            'Pekerjaan',
            'Alamat',
        ];
    }

    public function map($parent): array
    {
        $this->rowNumber++;
        $primaryStudent = $parent->students->first() ?? $parent->studentsPivot->first();
        $hubungan = 'Wali';
        if (! empty($parent->father_nik)) {
            $hubungan = 'Ayah';
        } elseif (! empty($parent->mother_nik)) {
            $hubungan = 'Ibu';
        }

        return [
            $this->rowNumber,
            $parent->full_name,
            $parent->nik ?? '-',
            $hubungan,
            $primaryStudent?->full_name ?? '-',
            $primaryStudent?->kelas?->nama_kelas ?? '-',
            $primaryStudent?->educationUnit?->name ?? '-',
            $parent->phone ?? '-',
            $parent->email ?? '-',
            $parent->occupation ?? '-',
            $parent->address ?? '-',
        ];
    }
}
