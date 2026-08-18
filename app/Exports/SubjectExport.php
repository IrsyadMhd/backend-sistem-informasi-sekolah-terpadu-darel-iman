<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SubjectExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Collection $subjects) {}

    public function headings(): array
    {
        return [
            'Kode Mapel',
            'Nama Mapel',
            'Nama Singkat',
            'Unit Pendidikan',
            'Kurikulum',
            'Kelompok',
            'Kategori',
            'Jenjang',
            'Jam Pelajaran',
            'KKM',
            'Status',
        ];
    }

    public function collection(): Collection
    {
        return $this->subjects->map(fn ($subject) => [
            $subject->kode_mapel ?? $subject->code,
            $subject->nama_mapel ?? $subject->name,
            $subject->nama_singkat,
            $subject->unitPendidikan?->name,
            $subject->kurikulum?->nama_kurikulum,
            $subject->kelompok_mapel,
            $subject->kategori,
            $subject->jenjang,
            $subject->jam_pelajaran,
            $subject->kkm,
            $subject->status ? 'Aktif' : 'Nonaktif',
        ]);
    }
}
